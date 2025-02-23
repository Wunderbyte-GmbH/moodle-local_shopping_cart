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
 * Testing checkout in payment gateway local_shopping_cart
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use core_component;
use Exception;
use local_shopping_cart\payment\service_provider;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\output\shoppingcart_history_list;
use tool_mocktesttime\time_mock;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Testing checkout in payment gateway local_shopping_cart
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shopping_cart_expiration_date extends \advanced_testcase {
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

        time_mock::init();
    }


    /**
     * Test transaction complete process
     *
     * @covers \local_shopping_cart\gateway
     * @covers \local_shopping_cart\payment\service_provider::get_payable()
     * @throws \coding_exception
     */
    public function test_add_item_set_expiration_date_delete_item_task(): void {
        global $DB, $CFG;

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $this->setUser($student1);
        // Validate payment account if it has a config.
        $record1 = $DB->get_record('payment_accounts', ['id' => $this->account->get('id')]);
        $this->assertEquals('PayOne1', $record1->name);
        $this->assertCount(1, $DB->get_records('payment_gateways', ['accountid' => $this->account->get('id')]));

        // Set local_shopping_cart to use the payment account.
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');
        set_config('prolongedpaymenttime', 30, 'local_shopping_cart');

        // No history items should have been created until now.
        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(0, count($historyrecords));

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\local_shopping_cart\task\delete_item_task']);
        $this->assertEquals(0, count($tasks));

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            1,
            $student1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            2,
            $student1->id
        );

        $tasks = $DB->get_records('task_adhoc', ['classname' => '\local_shopping_cart\task\delete_item_task']);
        $this->assertEquals(2, count($tasks));

        // With this code, we instantiate the checkout for this user:
        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();

        $starttime = time();

        $expirationtime = $data['expirationtime'];
        $delta = get_config('local_shopping_cart', 'expirationtime') * 60;
        $expectedtime = time() + $delta;

        $this->assertEquals($expectedtime, $expirationtime);

        // No history items should have been created until now.
        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(0, count($historyrecords));

        // With this code, we instantiate the checkout for this user:
        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);

        $identifier = $data['identifier'];

        // No history items should have been created until now.
        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(0, count($historyrecords));

        service_provider::get_payable('', $identifier);

        // No history items should have been created until now.
        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(2, count($historyrecords));

        // Look in the delete_item_task task.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\local_shopping_cart\task\delete_item_task']);
        $this->assertEquals(2, count($tasks));
        $task = reset($tasks);

        // When we adding the items to the shopping cart, we will use the prolonged time.
        $expectedtime = $starttime + get_config('local_shopping_cart', 'prolongedpaymenttime') * 60;

        $this->assertEquals($expectedtime, $task->nextruntime);

        $timeafterchange = strtotime(' + 10 minutes', $starttime);
        // 10 minutes have passed.
        time_mock::set_mock_time($timeafterchange);

        $this->assertEquals(strtotime(' + 10 minutes', $starttime), time());

        // After sleeping we add another item and expect that expiration time shifted because of this to x +1.
        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            3,
            $student1->id
        );

        $data = $cartstore->get_localized_data();

        // With adding another item, the expiration time should move only by $delta.
        // But the prolonged time we got via checkout (get_payable) is bigger then time plus delta.
        // So we keep it.

        // New expected expiration time is $timeafterchange plus $delta.
        $this->assertEquals($data['expirationtime'], $expectedtime);

        // Look in the check_status task.
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\local_shopping_cart\task\delete_item_task']);
        $this->assertEquals(3, count($tasks));
        $task = reset($tasks);

        // We check if all tasks have the right runtime.
        while (count($tasks) > 1) {
            $task = array_shift($tasks);
            $this->assertEquals($expectedtime, $task->nextruntime);
        }

        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(2, count($historyrecords));

        service_provider::get_payable('', $identifier);

        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(3, count($historyrecords));

        service_provider::deliver_order('', $identifier, 0, $student1->id);

        // We look in the ledger items.
        $schistorylist = new shoppingcart_history_list($student1->id, $identifier, true);
        $historylist = $schistorylist->return_list();
        $this->assertEquals(3, count($historylist['historyitems']));
    }
}
