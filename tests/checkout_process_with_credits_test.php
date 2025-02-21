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

use local_shopping_cart\payment\service_provider;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\local\cartstore;
use paygw_payone\external\get_config_for_js;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/payment/gateway/payone/thirdparty/vendor/autoload.php');
require_once($CFG->dirroot . '/local/shopping_cart/tests/checkout_process_test_setup.php');

/**
 * Testing checkout in payment gateway paygw_payone
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
final class checkout_process_with_credits_test extends checkout_process_test_setup {
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
        service_provider::get_payable('', $data['identifier']);

        foreach ($config as $key => $value) {
            set_config($key, $value, 'local_shopping_cart');
        }

        foreach ($changedinputsteps as $step => $stepdata) {
            $balance = shopping_cart_credits::add_credit($student1->id, $stepdata['generatedcredits'], 'EUR', '');

            shopping_cart::save_used_credit_state($student1->id, $stepdata['usecredit']);

            service_provider::get_payable('', $data['identifier']);
            if ($stepdata['purgecache']) {
                \cache_helper::purge_all();
            }
        }

        service_provider::deliver_order('', $data['identifier'], 1, $student1->id);
        $res = get_config_for_js::execute('local_shopping_cart', 'main', $data['identifier']);

        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $cartstore = cartstore::instance($student1->id);

        foreach ($assertions as $step => $assertion) {
            $this->$assertion($historyrecords, $cartstore, $student1->id);
        }
    }

    /**
     * Data provider for checkout process tests.
     *
     * @return array[]
     */
    public static function checkoutprocessdataprovider(): array {
        return [
            'User has cerdits, but does not uses credits, cache cleared' => [
                [
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                    'defaultcostcenterforcredits' => 'IT140030',
                    'costcenterstrings' => 'IT140030',
                ],
                [
                    [
                        'generatedcredits' => '5',
                        'usecredit' => false,
                        'purgecache' => true,
                    ],
                ],
                [
                    'assertbalanceisnotnull',
                    'payedpriceissame',
                ],
            ],
            'User has cerdits, and uses credits, cache cleared' => [
                [
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                    'defaultcostcenterforcredits' => 'IT140030',
                    'costcenterstrings' => 'IT140030',
                ],
                [
                    [
                        'generatedcredits' => '5',
                        'usecredit' => true,
                        'purgecache' => true,
                    ],
                ],
                [
                    'assertbalanceisnull',
                    'payedpriceisless',
                ],
            ],
            'User has cerdits, but does not uses credits, cache not cleared' => [
                [
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                    'defaultcostcenterforcredits' => 'IT140030',
                    'costcenterstrings' => 'IT140030',
                ],
                [
                    [
                        'generatedcredits' => '5',
                        'usecredit' => false,
                        'purgecache' => false,
                    ],
                ],
                [
                    'assertbalanceisnotnull',
                    'payedpriceissame',
                ],
            ],
            'User has cerdits, and uses credits, cache not cleared' => [
                [
                    'taxcategories' => 'default A:20 B:20 C:10
                        AT A:20 B:10 C:0
                        DE A:19 B:10 C:0',
                    'enabletax' => '1',
                    'defaultcostcenterforcredits' => 'IT140030',
                    'costcenterstrings' => 'IT140030',
                ],
                [
                    [
                        'generatedcredits' => '5',
                        'usecredit' => true,
                        'purgecache' => false,
                    ],
                ],
                [
                    'assertbalanceisnull',
                    'payedpriceisless',
                ],
            ],
        ];
    }
}
