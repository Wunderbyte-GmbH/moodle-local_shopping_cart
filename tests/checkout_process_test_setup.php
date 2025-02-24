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

use local_shopping_cart\shopping_cart;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\CardInfo;
use OnlinePayments\Sdk\Domain\CreatedPaymentOutput;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\GetHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\PaymentOutput;
use OnlinePayments\Sdk\Domain\PaymentResponse;
use OnlinePayments\Sdk\Domain\PaymentStatusOutput;
use OnlinePayments\Sdk\Domain\RedirectPaymentMethodSpecificOutput;
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
 */
abstract class checkout_process_test_setup extends \advanced_testcase {
    /** @var \core_payment\account account */
    protected $account;

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
     * Generate fake addresses for a given user.
     *
     * @param object $user
     * @return array
     */
    protected function generate_fake_addresses(object $user): array {
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
     * @param object $historyrecords
     * @param object $cartstore
     * @param int $userid
     *
     */
    public function assertbalanceisnull($historyrecords, $cartstore, $userid): void {
        $balanceafter = shopping_cart_credits::get_balance($userid);
        $this->assertEquals((float)0, (float)$balanceafter[0], 'assertbalanceisnull');
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $historyrecords
     * @param object $cartstore
     * @param int $userid
     */
    public function assertbalanceisnotnull($historyrecords, $cartstore, $userid): void {
        $balanceafter = shopping_cart_credits::get_balance($userid);
        $this->assertNotEquals((float)0, (float)$balanceafter[0], 'assertbalanceisnotnull');
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $historyrecords
     * @param object $cartstore
     */
    public function payedpriceisless($historyrecords, $cartstore): void {
        global $DB;
        $paidprice = 0;
        $itemprice = 0;
        foreach ($historyrecords as $item) {
            $itemprice += (float)$item->price;
        }

        $cartinformation = $DB->get_records('local_shopping_cart_ledger');
        foreach ($cartinformation as $ledgeritem) {
            $paidprice += (float)$ledgeritem->price;
        }
        $this->assertNotEquals($paidprice, $itemprice, 'payedpriceisless');
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $historyrecords
     * @param object $cartstore
     */
    public function payedpriceissame($historyrecords, $cartstore): void {
        global $DB;
        $paidprice = 0;
        $itemprice = 0;
        foreach ($historyrecords as $item) {
            $itemprice += (float)$item->price;
        }

        $cartinformation = $DB->get_records('local_shopping_cart_ledger');
        foreach ($cartinformation as $ledgeritem) {
            $paidprice += (float)$ledgeritem->price;
        }
        $this->assertEquals($paidprice, $itemprice, 'payedpriceissame');
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $managercache
     * @param object $historyrecords
     */
    public function assertcartstorevatnumber($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertNotNull($historyrecord->taxcountrycode, 'assertcartstorevatnumber_taxcountrycode');
            $this->assertNotNull($historyrecord->vatnumber, 'assertcartstorevatnumber_vatnumber');
        }
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $managercache
     * @param object $historyrecords
     */
    public function assertcartstorevatnumbernull($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertNull($historyrecord->taxcountrycode, 'assertcartstorevatnumbernull_taxcountrycode');
            $this->assertNull($historyrecord->vatnumber, 'assertcartstorevatnumbernull_vatnumber');
        }
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $managercache
     * @param object $historyrecords
     */
    public function assertvalidcheckout($managercache, $historyrecords): void {
        $this->assertTrue($managercache['checkout_validation'], 'assertvalidcheckout');
    }

    /**
     * Assertion: The transaction should be valid.
     */
    public function assertinvalidcheckout($managercache, $historyrecords): void {
        $this->assertFalse($managercache['checkout_validation'], 'assertinvalidcheckout');
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $managercache
     * @param object $historyrecords
     */
    public function assertcartstoretax($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertNotNull($historyrecord->taxpercentage, 'assertcartstoretax_taxpercentage');
            $this->assertNotNull($historyrecord->tax, 'assertcartstoretax_tax');
        }
    }

    /**
     * Assertion: The transaction should be valid.
     * @param object $managercache
     * @param object $historyrecords
     */
    public function assertcartstoretaxnull($managercache, $historyrecords): void {
        foreach ($historyrecords as $historyrecord) {
            $this->assertEquals((int)$historyrecord->taxpercentage, 0, 'assertcartstoretaxnull_taxpercentage');
            $this->assertEquals((int)$historyrecord->tax, 0, 'assertcartstoretaxnull_tax');
        }
    }
}
