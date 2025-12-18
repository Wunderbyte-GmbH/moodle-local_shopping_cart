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
 * Testing checkout wiht installment
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
use tool_mocktesttime\time_mock;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/payment/gateway/payone/thirdparty/vendor/autoload.php');
require_once($CFG->dirroot . '/local/shopping_cart/tests/checkout_process_test_setup.php');

/**
 * Testing checkout wiht installment
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
final class checkout_installment_test extends \local_shopping_cart\checkout_process_test_setup {
    /**
     * Test transaction complete process
     *
     * @dataProvider checkoutprocessdataprovider
     * @param array $config Config settings for the test
     * @param array $changedinputsteps JSON input for the checkout process
     * @param array $assertions Assertion function for the test
     * @covers \local_shopping_cart\local\checkout_process\checkout_manager
     */
    public function test_checkout_process(array $config, array $changedinputsteps, array $assertions): void {
        global $DB, $OUTPUT;

        time_mock::set_mock_time(strtotime('-10 days', time()));
        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $this->setUser($student1);

        // Validate payment account if it has a config.
        $record1 = $DB->get_record('payment_accounts', ['id' => $this->account->get('id')]);
        $this->assertEquals('PayOne1', $record1->name);
        $this->assertCount(1, $DB->get_records('payment_gateways', ['accountid' => $this->account->get('id')]));

        // Set local_shopping_cart to use the payment account.
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');

        // Add the item with no installment neither taxcategory to the cart.
        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'main',
            4,
            $student1->id
        );
        // Add the only item with installment to the cart.
        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'main',
            5,
            $student1->id
        );

        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();

        foreach ($config as $key => $value) {
            set_config($key, $value, 'local_shopping_cart');
        }

        $addresids = $this->generate_fake_addresses($student1);

        $managercache = [];
        foreach ($changedinputsteps as $step => &$stepdata) {
            while (str_contains($stepdata['changedinput'], 'REPLACE_WITH_ADDRESSID')) {
                $replacement = array_shift($addresids);
                $replacecount = 1;
                $stepdata['changedinput'] = str_replace(
                    'REPLACE_WITH_ADDRESSID',
                    $replacement,
                    $stepdata['changedinput'],
                    $replacecount
                );
            }
            $checkoutmanager = new checkout_manager($data, $stepdata['controlparameter']);
            $checkoutmanagerrenderedoverview = $checkoutmanager->render_overview();
            $managercache = $checkoutmanager->check_preprocess($stepdata['changedinput']);

            $balance = shopping_cart_credits::add_credit($student1->id, $stepdata['generatedcredits'], 'EUR', '');
            shopping_cart::save_used_credit_state($student1->id, $stepdata['usecredit']);
            shopping_cart::save_used_installments_state($student1->id, $stepdata['useinstallments']);

            // The price is calculated from the cache, but there is a fallback to DB, if no cache is available.
            $cartstore = cartstore::instance($student1->id);
            $data = $cartstore->get_localized_data();

            if ($stepdata['purgecache']) {
                \cache_helper::purge_all();
            }
        }
        unset($stepdata);

        // Invoke checkout::prepare_checkout() only after all customization steps are done.
        $cartstore->get_expanded_checkout_data($data);
        service_provider::get_payable('', $data['identifier']);
        service_provider::deliver_order('', $data['identifier'], 1, $student1->id);

        $historyrecords = $DB->get_records('local_shopping_cart_history', [], 'id ASC');

        foreach ($assertions as $step => $assertiontype) {
            if ($step == 'checkoutmanager') {
                foreach ($assertiontype as $typekey => $assertion) {
                    if ($typekey === 'assertcartstoreexacttax') {
                        $this->assertcartstoreexacttax($managercache, $historyrecords, $assertion[0]);
                    } else {
                        $this->$assertion($managercache, $historyrecords);
                    }
                }
            } else {
                foreach ($assertiontype as $assertion) {
                    $this->$assertion($historyrecords, $cartstore, $student1->id);
                }
            }
        }

        // Reset cart and move +5 day forward - we should pay 1st installment.
        cartstore::reset();
        time_mock::set_mock_time(strtotime('+5 days', time()));

        // Re-init shoppng cart.
        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();

        // Get infor about installments.
        $open = $cartstore->get_open_installments();
        $this->assertCount(3, $open);
        $due = $cartstore->get_due_installments();
        $this->assertCount(1, $due);
        // Validatee reminder message as per https://github.com/Wunderbyte-GmbH/moodle-mod_booking/issues/505.
        $html = local_shopping_cart_render_navbar_output($OUTPUT);
        $notess = \core\notification::fetch();
        $this->assertStringContainsString('Don\'t forget: Test item 5, 11.21 EUR.', $notess[0]->get_message());

        // Required: add installment to the cart.
        foreach ($due as $dueitem) {
            shopping_cart::add_item_to_cart(
                $dueitem['componentname'],
                $dueitem['area'],
                $dueitem['itemid'],
                $student1->id
            );
        }

        // Prepere checkout, confirm payment and get history.
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);
        $pay = shopping_cart::confirm_payment($student1->id, LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE, $data);
        $historyrecords = $DB->get_records('local_shopping_cart_history', [], 'id ASC');

        // Validate 1sr installment payment.
        foreach ($assertions as $step => $assertiontype) {
            if ($step == 'checkoutmanager') {
                foreach ($assertiontype as $typekey => $assertion) {
                    if ($typekey === 'assertcartstoreexacttax') {
                        $this->assertcartstoreexacttax($managercache, $historyrecords, $assertion[1]);
                    } else {
                        $this->$assertion($managercache, $historyrecords);
                    }
                }
            } else {
                foreach ($assertiontype as $assertion) {
                    $this->$assertion($historyrecords, $cartstore, $student1->id);
                }
            }
        }

        // Reset cart and move +5 day forward - we should pay 2nd installment.
        cartstore::reset();
        time_mock::set_mock_time(strtotime('+5 days', time()));

        // Re-init shoppng cart.
        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();

        // Get infor about installments.
        $open = $cartstore->get_open_installments();
        $this->assertCount(2, $open);
        $due = $cartstore->get_due_installments();
        $this->assertCount(1, $due);
        // Validatee reminder message as per https://github.com/Wunderbyte-GmbH/moodle-mod_booking/issues/505.
        $html = local_shopping_cart_render_navbar_output($OUTPUT);
        $notess = \core\notification::fetch();
        $this->assertStringContainsString('Don\'t forget: Test item 5, 11.21 EUR.', $notess[0]->get_message());

        // Required: add 2nd installment to the cart.
        foreach ($due as $dueitem) {
            shopping_cart::add_item_to_cart(
                $dueitem['componentname'],
                $dueitem['area'],
                $dueitem['itemid'],
                $student1->id
            );
        }

        // Prepere checkout, confirm payment and get history.
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);
        $pay = shopping_cart::confirm_payment($student1->id, LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE, $data);
        $historyrecords = $DB->get_records('local_shopping_cart_history', [], 'id ASC');

        // Validate 2nd installment payment.
        foreach ($assertions as $step => $assertiontype) {
            if ($step == 'checkoutmanager') {
                foreach ($assertiontype as $typekey => $assertion) {
                    if ($typekey === 'assertcartstoreexacttax') {
                        $this->assertcartstoreexacttax($managercache, $historyrecords, $assertion[2]);
                    } else {
                        $this->$assertion($managercache, $historyrecords);
                    }
                }
            } else {
                foreach ($assertiontype as $assertion) {
                    $this->$assertion($historyrecords, $cartstore, $student1->id);
                }
            }
        }
    }

    /**
     * Test the installments and check the price to pay with perpective of admin and student.
     * @covers \local_shopping_cart\local\pricemodifier\modifiers\installments
     * @covers \local_shopping_cart\external\get_price
     * @covers \local_shopping_cart\local\pricemodifier\modifiers\get_shopping_cart_items
     * @return void
     */
    public function test_installment_downpayment(): void {
        global $DB, $USER;

        $componentname = 'local_shopping_cart';
        $area = 'main';
        $item1id = 101;
        $item2id = 102;

        $this->setAdminUser();

        // Enabel installemnts.
        set_config('enableinstallments', 1, 'local_shopping_cart');
        $enabled = get_config('local_shopping_cart', 'enableinstallments');
        $this->assertEquals("1", $enabled);

        // We need to add installment record of item in local_shopping_cart_iteminfo table.
        define('DEFAULTDOWNPAYMENTFORITEM1', 2);
        $record = [
            "itemid" => $item1id,
            "componentname" => "local_shopping_cart",
            "area" => "main",
            "allowinstallment" => 1,
            "json" => json_encode([
                "allowinstallment" => "1",
                "downpayment" => DEFAULTDOWNPAYMENTFORITEM1,
                "numberofpayments" => "2",
                "duedaysbeforecoursestart" => 0,
                "duedatevariable" => 2,
            ]),
            "usermodified" => 2,
        ];
        $DB->insert_record('local_shopping_cart_iteminfo', $record);

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();

        // Add the items to the cart.
        shopping_cart::add_item_to_cart($componentname, $area, $item1id, $student1->id);
        shopping_cart::add_item_to_cart($componentname, $area, $item2id, $student1->id);

        // Check if there is no records for down payments.
        // To check that we need to make sure the newdownpayments key not exists in the array
        // or it is emopty.
        $data = \local_shopping_cart\external\get_shopping_cart_items::execute($student1->id);
        $condition = !array_key_exists('newdownpayments', $data) || empty($data['newdownpayments']);
        $this->assertTrue($condition);

        // Get the current (default) values of the useinstallement ans usecredit.
        // -1 always means no change in current value.
        $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
        $useinstallmentdefault = $price['useinstallments'];
        $usecreditdefault = $price['usecredit'];
        // Normally the usecredit is enabled and useinstallment is disabled by default.
        $this->assertEquals(0, $useinstallmentdefault);
        $this->assertEquals(1, $usecreditdefault);

        // Admin enables intallments payment. For this approach we need to call get_price external API.
        // -1 always means no change in current value. So here, we enable useinstallments but we make no change in user credit.
        $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, 1);
        $this->assertEquals(1, $price['useinstallments']);
        $this->assertEquals($usecreditdefault, $price['usecredit']);

        // We check the default value of down payment for item 1.
        $items = \local_shopping_cart\external\get_shopping_cart_items::execute($student1->id);
        $this->assertCount(1, $items['installments']); // Only item1 is allowed for the installments.
        $this->assertEquals(DEFAULTDOWNPAYMENTFORITEM1, $items['installments'][0]['initialpayment']);

        // Check the price to pay before appliying new down payment.
        $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
        $expectedamounttopay = round(DEFAULTDOWNPAYMENTFORITEM1 + 12.12, 2); // Item 1 downpayment + item 2 price.
        $this->assertEquals($expectedamounttopay, $price['price']);

        // Now we set new down payment for item 1.
        define('DESIREDDOWNPAYMENTFORITEM1', 6);
        $cartstore = cartstore::instance((int)$student1->id);
        $cartstore->add_discount_to_item(
            $componentname,
            $area,
            $item1id,
            0,
            0,
            DESIREDDOWNPAYMENTFORITEM1
        );

        $users = [
            'admin' => $USER,
            'student' => $student1,
        ];

        foreach ($users as $usertitle => $userobj) {
            $this->setUser($userobj);
            // Now we get shopping cart items from perspective of each user to make sure that we receive installments as well.
            // 1 - Check the installment new down payments. It should have one record for item1.
            // 2 - Check the new down payment value.
            // 3 - Check installments. It should have only 1 item as we enabled installements only for item1.
            // 4 - The amount of down payment sould be equal to new value of down payment.
            // 5 - The total amout to pay buy user should be item1 down payment + initial price of item2.
            $items = \local_shopping_cart\external\get_shopping_cart_items::execute($student1->id);
            $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
            $this->assertNotEmpty($items['newdownpayments'][$componentname][$area][$item1id]);
            $this->assertEquals(
                DESIREDDOWNPAYMENTFORITEM1,
                $items['newdownpayments'][$componentname][$area][$item1id]['newdownpayment']
            );
            $this->assertCount(1, $items['installments']); // Only item1 is allowed for the installments.
            $this->assertEquals(DESIREDDOWNPAYMENTFORITEM1, $items['installments'][0]['initialpayment']);
            $expectedamounttopay = round(DESIREDDOWNPAYMENTFORITEM1 + 12.12, 2); // Item 1 downpayment + item 2 price.
            $this->assertEquals($expectedamounttopay, $price['price']);
        }

        $this->setAdminUser();
        // Now we enable the installment for item 2.
        define('DEFAULTDOWNPAYMENTFORITEM2', 3);
        $record = [
            "itemid" => $item2id,
            "componentname" => "local_shopping_cart",
            "area" => "main",
            "allowinstallment" => 1,
            "json" => json_encode([
                "allowinstallment" => "1",
                "downpayment" => DEFAULTDOWNPAYMENTFORITEM2,
                "numberofpayments" => "2",
                "duedaysbeforecoursestart" => 0,
                "duedatevariable" => 2,
            ]),
            "usermodified" => $USER->id,
        ];
        $DB->insert_record('local_shopping_cart_iteminfo', $record);

        // Now we should be able to see 2 items in the installments.
        foreach ($users as $usertitle => $userobj) {
            $this->setUser($userobj);
            // Now we get shopping cart items from perspective of each user to make sure that we receive installments as well.
            // 1 - Check the installment new down payments. It should still have one record for item1.
            // We did not applied new value for down payment of r item 2.
            // 2 - Check the new down payment value.
            // 3 - Check installments. It should have 2 item as we enabled installements for both items.
            // 4 - The amount of down payment sould be equal to new value of down payment for item 1
            // and the amount of dow payment for item 2 should be equal to defalut.
            // 5 - The total amout to pay buy user should be item1 down payment + item2 down payment.
            $items = \local_shopping_cart\external\get_shopping_cart_items::execute($student1->id);
            $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
            $this->assertNotEmpty($items['newdownpayments'][$componentname][$area][$item1id]);
            // We did not applied a new desired down payment.
            $item2hasrecordinnewdownpayments = isset($items['newdownpayments'][$componentname][$area][$item2id]);
            $this->assertFalse($item2hasrecordinnewdownpayments);
            $this->assertEquals(
                DESIREDDOWNPAYMENTFORITEM1,
                $items['newdownpayments'][$componentname][$area][$item1id]['newdownpayment']
            );
            $this->assertCount(2, $items['installments']); // Only item1 is allowed for the installments.
            $this->assertEquals(DESIREDDOWNPAYMENTFORITEM1, $items['installments'][0]['initialpayment']);
            $this->assertEquals(DEFAULTDOWNPAYMENTFORITEM2, $items['installments'][1]['initialpayment']);
             // Item 1 downpayment + item 2 down payment.
            $expectedamounttopay = round(DESIREDDOWNPAYMENTFORITEM1 + DEFAULTDOWNPAYMENTFORITEM2, 2);
            $this->assertEquals($expectedamounttopay, $price['price']);
        }

        // Now we disable the installments.
        $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, 0);
        foreach ($users as $usertitle => $userobj) {
            $this->setUser($userobj);
            // Now we get shopping cart items from perspective of each user.
            // - There shoulb be no installmenets.
            // - The total amout to pay buy user should be item1 initial price + item2 initial price.
            $items = \local_shopping_cart\external\get_shopping_cart_items::execute($student1->id);
            $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
            $this->assertCount(0, $items['installments']);
             // Item 1 initial value + item 2 initial value.
            $expectedamounttopay = round(12.12 + 12.12, 2);
            $this->assertEquals($expectedamounttopay, $price['price']);
        }

        // Now we remove items.
        shopping_cart::delete_item_from_cart($componentname, $area, $item1id, $student1->id);
        shopping_cart::delete_item_from_cart($componentname, $area, $item2id, $student1->id);

        $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
        foreach ($users as $usertitle => $userobj) {
            $this->setUser($userobj);
            // Now we get shopping cart items from perspective of each user.
            // - There shoulb be no installmenets.
            // - There shoulb be no items.
            // - The total amout to pay is 0.
            $items = \local_shopping_cart\external\get_shopping_cart_items::execute($student1->id);
            $price = \local_shopping_cart\external\get_price::execute($student1->id, -1, -1);
            $this->assertCount(0, $items['items']);
            $this->assertCount(0, $items['installments']);
            $newdownpaymentexists = isset($items['newdownpayment']);
            $this->assertFalse($newdownpaymentexists);
            $this->assertEquals(0, $price['price']);
        }
    }

    /**
     * Data provider for checkout process tests.
     *
     * @return array[]
     */
    public static function checkoutprocessdataprovider(): array {
        return [
            'Installment, shipping address mandatory, default tax category, user uses credits, cache cleared' => [
                [
                    'addresses_required' => 'shipping',
                    'taxcategories' => 'default A:25 B:25 C:15
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                    'defaulttaxcategory' => 'C', // The "default C" will be used as sefault tax category if not provided.
                    'defaultcostcenterforcredits' => 'IT140030',
                    'costcenterstrings' => 'IT140030',
                    'enableinstallments' => '1',
                    'timebetweenpayments' => '2',
                    'reminderdaysbefore' => '1',
                ],
                [
                    [
                        'changedinput' => '[{"name":"selectedaddress_shipping","value":"REPLACE_WITH_ADDRESSID"}]',
                        'controlparameter' => [
                            "currentstep" => 0,
                            "action" => null,
                        ],
                        'generatedcredits' => '5',
                        'usecredit' => true,
                        'useinstallments' => true,
                        'purgecache' => true,
                    ],
                ],
                [
                    'checkoutmanager' => [
                        'assertvalidcheckout',
                        'assertcartstoretax',
                        'assertcartstoreexacttax' => [
                            [
                                [
                                    'itemid' => "4",
                                    'price' => "12.12",
                                    'tax' => "1.580",
                                    'taxpercentage' => "0.1500", // The "default C" is used as no category provided.
                                    'taxcategory' => "",
                                    'usecredit' => 1,
                                ],
                                // After downpayment.
                                [
                                    'itemid' => "5",
                                    'price' => "20.00",
                                    'tax' => "4.000",
                                    'taxpercentage' => "0.2500",
                                    'taxcategory' => "B",
                                    'usecredit' => 1,
                                    'useinstallment' => 1,
                                    'useinstallments' => "2",
                                ],
                            ],
                            [
                                [
                                    'itemid' => "4",
                                    'price' => "12.12",
                                    'tax' => "1.580",
                                    'taxpercentage' => "0.1500", // The "default C" is used as no category provided.
                                    'taxcategory' => "",
                                    'usecredit' => 1,
                                ],
                                // After 1st installment.
                                [
                                    'itemid' => "5",
                                    'price' => "31.21",
                                    'tax' => "6.240",
                                    'taxpercentage' => "0.2500",
                                    'taxcategory' => "B",
                                    'usecredit' => 1,
                                    'useinstallment' => 1,
                                    'useinstallments' => "2",
                                ],
                            ],
                            [
                                [
                                    'itemid' => "4",
                                    'price' => "12.12",
                                    'tax' => "1.580",
                                    'taxpercentage' => "0.1500", // The "default C" is used as no category provided.
                                    'taxcategory' => "",
                                    'usecredit' => 1,
                                ],
                                // After 2nd installment.
                                [
                                    'itemid' => "5",
                                    'price' => "42.42",
                                    'tax' => "8.480",
                                    'taxpercentage' => "0.2500",
                                    'taxcategory' => "B",
                                    'usecredit' => 1,
                                    'useinstallments' => "0",
                                ],
                            ],
                        ],
                    ],
                    'shoppingcart' => [
                        'assertbalanceisnull',
                    ],
                ],
            ],
        ];
    }
}
