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
use paygw_payone\external\get_config_for_js;
use local_shopping_cart\external\get_price;

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

        $res = get_config_for_js::execute('local_shopping_cart', 'main', $data['identifier']);
        $historyrecords = $DB->get_records('local_shopping_cart_history');

        foreach ($assertions as $step => $assertiontype) {
            if ($step == 'checkoutmanager') {
                foreach ($assertiontype as $typekey => $assertion) {
                    if ($typekey === 'assertcartstoreexacttax') {
                        $this->assertcartstoreexacttax($managercache, $historyrecords, $assertion);
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
                                'itemid' => "4",
                                'price' => "12.12",
                                'tax' => "1.580",
                                'taxpercentage' => "0.1500", // The "default C" is used as no category provided.
                                'taxcategory' => "",
                                'usecredit' => 1,
                            ],
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
                    ],
                    'shoppingcart' => [
                        'assertbalanceisnull',
                    ],
                ],
            ],
        ];
    }
}
