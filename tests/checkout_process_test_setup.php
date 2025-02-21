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
}
