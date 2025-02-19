<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Testing checkout in payment gateway paygw_payone
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\payment\service_provider;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\local\cartstore;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\CardInfo;
use OnlinePayments\Sdk\Domain\CreatedPaymentOutput;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\GetHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\PaymentOutput;
use OnlinePayments\Sdk\Domain\PaymentResponse;
use OnlinePayments\Sdk\Domain\PaymentStatusOutput;
use OnlinePayments\Sdk\Domain\RedirectPaymentMethodSpecificOutput;
use paygw_payone\external\get_config_for_js;
use paygw_payone\payone_sdk;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/payment/gateway/payone/thirdparty/vendor/autoload.php');

/**
 * Testing checkout in payment gateway paygw_payone
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
final class checkout_process_test extends \advanced_testcase {
    /** @var \core_payment\account account */
    private $account;

    /**
     * Setup function.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('country', 'AT');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $this->account = $generator->create_payment_account(['name' => 'PayOne1']);

        $record = new stdClass();
        $record->accountid = $this->account->get('id');
        $record->gateway = 'payone';
        $record->enabled = 1;
        $record->timecreated = time();
        $record->timemodified = time();

        $config = new stdClass();
        $config->environment = 'sandbox';
        // Load the credentials from Github.
        $config->brandname = getenv('BRANDNAME') ?: 'fakename';
        $config->clientid = getenv('CLIENTID') ?: 'fakeclientid';
        $config->secret = getenv('PAYONE_SECRET') ?: 'fakesecret';

        $record->config = json_encode($config);

        $accountgateway1 = \core_payment\helper::save_payment_gateway($record);

        // Mock responsedata from payment gateway
        $responsedata = $this->createMock(CreateHostedCheckoutResponse::class);
        $responsedata->method('getHostedCheckoutId')
            ->willReturnCallback(function () {
                return str_pad(rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            });
        $responsedata->method('getRedirectUrl')->willReturn('https://payment.preprod.payone.com/hostedcheckout/PaymentMethods/');

        $amoutofmoney = $this->createMock(AmountOfMoney::class);
        $amoutofmoney->method('getAmount')->willReturn(4410);
        $amoutofmoney->method('getCurrencyCode')->willReturn('EUR');

        $statusoutput = $this->createMock(PaymentStatusOutput::class);
        $statusoutput->method('getStatusCode')->willReturn('800.100.100');

        $redirectspecificoutput = $this->createMock(RedirectPaymentMethodSpecificOutput::class);
        $redirectspecificoutput->method('getPaymentProductId')->willReturn('VC');

        // Mock orderdetails
        $paymentoutput = $this->createMock(PaymentOutput::class);
        $paymentoutput->method('getAmountOfMoney')->willReturn($amoutofmoney);
        $paymentoutput->method('getRedirectPaymentMethodSpecificOutput')->willReturn($redirectspecificoutput);

        $cardpaymentmethod = $this->createMock(CardInfo::class);
        $cardpaymentmethod->method('getPaymentProductId')->willReturn('test_product_id');

        $paymentresponse = $this->createMock(PaymentResponse::class);
        $paymentresponse->method('getPaymentOutput')->willReturn($paymentoutput);
        $paymentresponse->method('getStatusOutput')->willReturn($statusoutput);
        $paymentresponse->method('getStatus')->willReturn('CAPTURED');

        $createdpaymentoutput = $this->createMock(CreatedPaymentOutput::class);
        $createdpaymentoutput->method('getPayment')->willReturn($paymentresponse);
        $createdpaymentoutput->method('getPaymentStatusCategory')->willReturn('SUCCESSFUL');

        $orderdetails = $this->createMock(GetHostedCheckoutResponse::class);
        $orderdetails->method('getStatus')->willReturn('PAYMENT_CREATED');
        $orderdetails->method('getCreatedPaymentOutput')->willReturn($createdpaymentoutput);

        $sdkmock = $this->getMockBuilder(payone_sdk::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_redirect_link_for_payment', 'check_status'])
            ->getMock();

        $sdkmock->method('get_redirect_link_for_payment')
            ->willReturn($responsedata);

        $sdkmock->method('check_status')
            ->willReturn($orderdetails);

        payone_sdk::$factory = function () use ($sdkmock) {
            return $sdkmock;
        };
    }

    /**
     * Test transaction complete process
     *
     * @dataProvider checkoutprocessdataprovider
     * @param array $config Config settings for the test
     * @param array $changedinputsteps JSON input for the checkout process
     * @param array $assertions Assertion function for the test
     * @covers \checkoutprocess_manager
     */
    public function test_checkout_process(array $config, array $changedinputsteps, array $assertions): void {
        global $DB;

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $this->setUser($student1);

        // Validate payment account if it has a config.
        $record1 = $DB->get_record('payment_accounts', ['id' => $this->account->get('id')]);
        $this->assertEquals('PayOne1', $record1->name);
        $this->assertCount(1, $DB->get_records('payment_gateways', ['accountid' => $this->account->get('id')]));

        // Set local_shopping_cart to use the payment account.
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            1,
            $student1->id
        );

        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);

        // Everything above is setup.

        foreach ($config as $key => $value) {
            set_config($key, $value, 'local_shopping_cart');
        }

        $addresids = $this->generate_fake_addresses($student1);

        $managercache = [];
        foreach ($changedinputsteps as $step => &$stepdata) {
            while (str_contains($stepdata['changedinput'], 'REPLACE_WITH_ADDRESSID')) {
                $replacement = array_shift($addresids);
                $replacecount = 1;
                $stepdata['changedinput'] = str_replace('REPLACE_WITH_ADDRESSID', $replacement, $stepdata['changedinput'], $replacecount);
            }
            $checkoutmanager = new checkout_manager($data, $stepdata['controlparameter']);
            $checkoutmanagerrenderedoverview = $checkoutmanager->render_overview();
            $managercache = $checkoutmanager->check_preprocess($stepdata['changedinput']);
        }
        unset($stepdata);

        service_provider::get_payable('', $data['identifier']);
        service_provider::deliver_order('', $data['identifier'], 1, $student1->id);

        $res = get_config_for_js::execute('local_shopping_cart', 'main', $data['identifier']);
        $historyrecords = $DB->get_records('local_shopping_cart_history');

        $cartstore = cartstore::instance($student1->id);

        foreach ($assertions as $step => $assertion) {
            $this->$assertion($managercache, $historyrecords);
        }
    }

    /**
     * Generate fake addresses for a given user.
     *
     * @param object $user
     * @return array
     */
    private function generate_fake_addresses(object $user): array {
        global $DB;
        $addressids = [];
        for ($i = 0; $i < 3; $i++) {
            $record = new stdClass();
            $record->userid = $user->id;
            $record->name = $user->firstname . ' ' . $user->lastname;
            $record->state = 'AT';
            $record->address = 'Fakestreet ' . $i;
            $record->city = 'Fakecity ' . $i;
            $record->zip = '12345' . $i;
            $record->phone = '0043 93453234' . $i;
            $addressids[] = $DB->insert_record('local_shopping_cart_address', $record);
        }
        return $addressids;
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertcartstorevatnumber($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertNotNull($historyrecord->taxcountrycode);
            $this->assertNotNull($historyrecord->vatnumber);
        }
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertcartstorevatnumbernull($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertNull($historyrecord->taxcountrycode);
            $this->assertNull($historyrecord->vatnumber);
        }
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertvalidcheckout($managercache, $historyrecords): void {
        $this->assertTrue($managercache['checkout_validation']);
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertinvalidcheckout($managercache, $historyrecords): void {
        $this->assertFalse($managercache['checkout_validation']);
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertcartstoretax($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertNotNull($historyrecord->taxpercentage);
            $this->assertNotNull($historyrecord->tax);
        }
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertcartstoretaxnull($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertEquals((int)$historyrecord->taxpercentage, 0);
            $this->assertEquals((int)$historyrecord->tax, 0);
        }
    }

    /**
     * Data provider for checkout process tests.
     *
     * @return array[]
     */
    public function checkoutprocessdataprovider(): array {
        return [
            'Only vatnumber mandatory, valid' => [
                [
                    'showvatnrchecker' => '1',
                    'owncountrycode' => 'DE',
                    'onlywithvatnrnumber' => '1',
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                ],
                [
                    [
                        'changedinput' => '{"vatCodeCountry":"AT,ATU74259768"}',
                        'controlparameter' => [
                            "currentstep" => 0,
                            "action" => null,
                        ],
                    ],
                ],
                [
                    'assertvalidcheckout',
                    'assertcartstorevatnumber',
                    'assertcartstoretaxnull',
                ],
            ],
            'Only vatnumber mandatory, invalid' => [
                [
                    'showvatnrchecker' => '1',
                    'owncountrycode' => 'DE',
                    'onlywithvatnrnumber' => '1',
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                ],
                [
                    [
                        'changedinput' => '{"vatCodeCountry":"AT,ATU742597688"}',
                        'controlparameter' => [
                            "currentstep" => 0,
                            "action" => null,
                        ],
                    ],
                ],
                [
                    'assertinvalidcheckout',
                    'assertcartstorevatnumbernull',
                    'assertcartstoretax',
                ],
            ],
            'Only billing address mandatory' => [
                [
                    'addresses_required' => 'billing',
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                ],
                [
                    [
                        'changedinput' => '[{"name":"selectedaddress_billing","value":"REPLACE_WITH_ADDRESSID"}]',
                        'controlparameter' => [
                            "currentstep" => 0,
                            "action" => null,
                        ],
                    ],
                ],
                [
                    'assertvalidcheckout',
                    'assertcartstorevatnumbernull',
                    'assertcartstoretax',
                ],
            ],
            'Only one shipping address mandatory' => [
                [
                    'addresses_required' => 'shipping',
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                ],
                [
                    [
                        'changedinput' => '[{"name":"selectedaddress_shipping","value":"REPLACE_WITH_ADDRESSID"}]',
                        'controlparameter' => [
                            "currentstep" => 0,
                            "action" => null,
                        ],
                    ],
                ],
                [
                    'assertvalidcheckout',
                    'assertcartstorevatnumbernull',
                    'assertcartstoretax',
                ],
            ],
            'Both addresses mandatory' => [
                [
                    'addresses_required' => 'billing,shipping',
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                ],
                [
                    [
                        'changedinput' =>
                            '[{"name":"selectedaddress_billing","value":"REPLACE_WITH_ADDRESSID"},
                            {"name":"selectedaddress_shipping","value":"REPLACE_WITH_ADDRESSID"}]',
                        'controlparameter' => [
                            "currentstep" => 0,
                            "action" => null,
                        ],
                    ],
                ],
                [
                    'assertvalidcheckout',
                    'assertcartstorevatnumbernull',
                    'assertcartstoretax',
                ],
            ],
        ];
    }
}
