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
 * Tests that the automatic release of a reservation waits for a running payment.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\openorders;
use local_shopping_cart\payment\service_provider;
use stdClass;
use tool_mocktesttime\time_mock;

/**
 * Tests that a reservation is only released on a final answer of the payment provider.
 *
 * The provider is asked live through the gateway's status check. Only an order that is still open
 * after that question holds the reservation, and only against the automatic clean-up - the user
 * may always remove items by hand. Orders nobody can ask about never hold anything.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
final class reservation_hold_during_payment_test extends \advanced_testcase {
    /** @var \core_payment\account account */
    private $account;

    /** @var int Minutes an item stays in the cart before a checkout. */
    private const EXPIRATIONTIME = 30;

    /** @var int Minutes the reservation is held once the payment process has started. */
    private const PROLONGEDTIME = 120;

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
        $config->brandname = 'fakename';
        $config->clientid = 'fakeclientid';
        $config->secret = 'fakesecret';
        $record->config = json_encode($config);

        \core_payment\helper::save_payment_gateway($record);

        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');
        set_config('expirationtime', self::EXPIRATIONTIME, 'local_shopping_cart');
        set_config('prolongedpaymenttime', self::PROLONGEDTIME, 'local_shopping_cart');

        time_mock::init();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        openorders::set_status_check_for_testing(null);
        openorders::reset_tables();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * Fills a cart with two items and starts a payment for it.
     *
     * @param int $userid
     * @return int the cart identifier
     */
    private function start_payment(int $userid): int {
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $userid);

        $cartstore = cartstore::instance($userid);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);

        $identifier = (int) $data['identifier'];
        service_provider::get_payable('', $identifier);

        return $identifier;
    }

    /**
     * Writes the open order the gateway creates when it sends the user to the provider.
     *
     * @param int $userid
     * @param int $identifier
     * @param int $status
     * @param string $table
     * @return int
     */
    private function create_open_order(int $userid, int $identifier, int $status, string $table = 'paygw_payone_openorders'): int {
        global $DB;

        $record = new stdClass();
        $record->tid = '1000' . $identifier;
        $record->itemid = $identifier;
        $record->userid = $userid;
        $record->price = 30.30;
        $record->status = $status;
        $record->timecreated = time();
        $record->timemodified = time();

        return (int) $DB->insert_record($table, $record);
    }

    /**
     * Builds the clean-up task for test item 1 of the user, the way the checkout schedules it.
     *
     * @param int $userid
     * @return \local_shopping_cart\task\delete_item_task
     */
    private function cleanup_task(int $userid): \local_shopping_cart\task\delete_item_task {
        $task = new \local_shopping_cart\task\delete_item_task();
        $task->set_userid($userid);
        $task->set_custom_data([
            'itemid' => 1,
            'userid' => $userid,
            'componentname' => 'local_shopping_cart',
            'area' => 'testitem',
        ]);
        return $task;
    }

    /**
     * Runs the clean-up task and tells whether test item 1 is still in the cart afterwards.
     *
     * @param \local_shopping_cart\task\delete_item_task $task
     * @param int $userid
     * @return bool
     */
    private function run_task_and_item_is_kept(\local_shopping_cart\task\delete_item_task $task, int $userid): bool {
        ob_start();
        $task->execute();
        ob_end_clean();
        $data = cartstore::instance($userid)->get_data();
        return array_key_exists('local_shopping_cart-testitem-1', $data['items'] ?? []);
    }

    /**
     * Writes a pending checkout for the cart identifier, the way the checkout page does.
     *
     * @param int $userid
     * @param int $identifier
     * @return void
     */
    private function assert_history_pending(int $userid, int $identifier): void {
        global $DB;
        $this->assertTrue(
            $DB->record_exists_select(
                'local_shopping_cart_history',
                'userid = :userid AND identifier = :identifier AND paymentstatus IN (0, 1)',
                ['userid' => $userid, 'identifier' => $identifier]
            ),
            'The checkout is not waiting for its payment.'
        );
    }

    /**
     * Only a gateway with a status check can be asked; payone is one, core paypal and stripe are not.
     *
     * @covers \local_shopping_cart\local\openorders::can_ask
     * @covers \local_shopping_cart\local\openorders::get_tables
     * @return void
     */
    public function test_gateways_with_a_status_check_are_recognised(): void {
        $this->assertTrue(openorders::can_ask('payone'));
        $this->assertFalse(openorders::can_ask('paypal'));
        $this->assertFalse(openorders::can_ask('stripe'));

        $tables = openorders::get_tables();
        $this->assertSame('paygw_payone_openorders', $tables['payone']);
    }

    /**
     * The clean-up keeps the cart while the provider, asked right now, says the payment is running.
     *
     * @covers \local_shopping_cart\local\openorders::is_payment_ongoing
     * @covers \local_shopping_cart\local\cartstore::get_data
     * @return void
     */
    public function test_cart_is_held_while_the_provider_reports_a_running_payment(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $starttime = time();
        $identifier = $this->start_payment($user->id);
        $this->assert_history_pending($user->id, $identifier);
        $this->create_open_order($user->id, $identifier, 0);

        // The provider is asked and leaves the order open: still running.
        $asked = 0;
        openorders::set_status_check_for_testing(function ($gateway, $openorder) use (&$asked) {
            $asked++;
        });

        $this->assertTrue(openorders::is_payment_ongoing($user->id));
        $this->assertSame(1, $asked, 'The provider was not asked.');

        // Beyond the prolonged payment time the expiration clean-up would release the seat,
        // but the payment is running, so the cart is kept.
        time_mock::set_mock_time($starttime + (self::PROLONGEDTIME + 10) * 60);

        $data = cartstore::instance($user->id)->get_data();

        $this->assertCount(2, $data['items'], 'The reservation was released while the payment was still running.');
    }

    /**
     * A definitive answer of the provider - completed or failed - releases the hold.
     *
     * @covers \local_shopping_cart\local\openorders::is_payment_ongoing
     * @return void
     */
    public function test_a_resolved_payment_does_not_hold_the_cart(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_payment($user->id);
        $ooid = $this->create_open_order($user->id, $identifier, 0);

        // The provider answers "failed" when asked; the gateway writes that to the open order.
        openorders::set_status_check_for_testing(function ($gateway, $openorder) use ($DB, $ooid) {
            $DB->set_field('paygw_payone_openorders', 'status', 2, ['id' => $ooid]);
        });

        $this->assertFalse(openorders::is_payment_ongoing($user->id));
    }

    /**
     * An open order that is not asked about does not hold the cart.
     *
     * Orders older than the check window, orders of a checkout that is not waiting for its payment
     * any more, and orders of gateways without a status check are never a running payment - the
     * user walked away, and nobody could tell otherwise. Otherwise a closed payment window would
     * keep the items in the cart forever (moodle-local_shopping_cart#213).
     *
     * @covers \local_shopping_cart\local\openorders::is_payment_ongoing
     * @return void
     */
    public function test_an_abandoned_payment_does_not_hold_the_cart_forever(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $asked = 0;
        openorders::set_status_check_for_testing(function () use (&$asked) {
            $asked++;
        });

        $identifier = $this->start_payment($user->id);
        $ooid = $this->create_open_order($user->id, $identifier, 0);

        // Older than the check window: not asked, not running.
        $DB->set_field('paygw_payone_openorders', 'timecreated', time() - 25 * 60 * 60, ['id' => $ooid]);
        $this->assertFalse(openorders::is_payment_ongoing($user->id));

        // Recent, but the checkout it belongs to is not waiting for its payment: not running.
        $DB->set_field('paygw_payone_openorders', 'timecreated', time(), ['id' => $ooid]);
        $DB->set_field('local_shopping_cart_history', 'paymentstatus', 3, ['identifier' => $identifier]);
        $this->assertFalse(openorders::is_payment_ongoing($user->id));

        $this->assertSame(0, $asked, 'An order outside the window or of a finished checkout was asked about.');

        // An order of a gateway without a status check (stripe, paypal) is never asked about, see
        // test_gateways_with_a_status_check_are_recognised: only the tables listed there are read.
    }

    /**
     * Removing an item by hand always works, even while a payment is running.
     *
     * @covers \local_shopping_cart\shopping_cart::delete_item_from_cart
     * @return void
     */
    public function test_the_user_can_always_remove_an_item_by_hand(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_payment($user->id);
        $this->create_open_order($user->id, $identifier, 0);
        openorders::set_status_check_for_testing(function () {
            // The provider says: still running.
        });
        $this->assertTrue(openorders::is_payment_ongoing($user->id));

        $this->assertTrue(
            shopping_cart::delete_item_from_cart('local_shopping_cart', 'testitem', 1, $user->id),
            'The user could not remove an item by hand.'
        );
        $data = cartstore::instance($user->id)->get_data();
        $this->assertCount(1, $data['items']);
        $this->assertArrayNotHasKey('local_shopping_cart-testitem-1', $data['items']);
    }

    /**
     * The clean-up task keeps the item and asks again later while the payment is running.
     *
     * @covers \local_shopping_cart\task\delete_item_task::execute
     * @return void
     */
    public function test_the_cleanup_task_keeps_the_item_while_the_payment_is_running(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_payment($user->id);
        $ooid = $this->create_open_order($user->id, $identifier, 0);
        openorders::set_status_check_for_testing(function () {
            // Still running.
        });

        $task = new \local_shopping_cart\task\delete_item_task();
        $task->set_userid($user->id);
        $task->set_custom_data([
            'itemid' => 1,
            'userid' => $user->id,
            'componentname' => 'local_shopping_cart',
            'area' => 'testitem',
        ]);
        $before = $DB->count_records('task_adhoc', ['classname' => '\\' . get_class($task)]);

        ob_start();
        $task->execute();
        ob_end_clean();

        $data = cartstore::instance($user->id)->get_data();
        $this->assertArrayHasKey('local_shopping_cart-testitem-1', $data['items'], 'The task released a running payment.');
        $this->assertSame(
            $before + 1,
            $DB->count_records('task_adhoc', ['classname' => '\\' . get_class($task)]),
            'The task did not queue itself again.'
        );

        // Once the provider has answered, the task removes the item.
        $DB->set_field('paygw_payone_openorders', 'status', 2, ['id' => $ooid]);
        ob_start();
        $task->execute();
        ob_end_clean();
        $data = cartstore::instance($user->id)->get_data();
        $this->assertArrayNotHasKey('local_shopping_cart-testitem-1', $data['items'] ?? []);
    }
    /**
     * Once the check window has closed, the task releases the item although the order is still open.
     *
     * A provider that never answers must not hold a seat forever: after 24 hours the open order is
     * not asked about any more and the clean-up runs as it always did.
     *
     * @covers \local_shopping_cart\task\delete_item_task::execute
     * @covers \local_shopping_cart\local\openorders::is_payment_ongoing
     * @return void
     */
    public function test_the_cleanup_task_releases_the_item_once_the_check_window_has_closed(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_payment($user->id);
        $ooid = $this->create_open_order($user->id, $identifier, 0);
        $asked = 0;
        openorders::set_status_check_for_testing(function () use (&$asked) {
            // The provider never resolves the order.
            $asked++;
        });

        $task = $this->cleanup_task($user->id);
        $this->assertTrue(
            $this->run_task_and_item_is_kept($task, $user->id),
            'The item was released while the payment was running.'
        );
        $this->assertSame(1, $asked);

        // 24 hours later the order is still open, but nobody asks any more.
        $DB->set_field('paygw_payone_openorders', 'timecreated', time() - 24 * 60 * 60 - 1, ['id' => $ooid]);

        $this->assertFalse($this->run_task_and_item_is_kept($task, $user->id), 'The item was kept beyond the check window.');
        $this->assertSame(1, $asked, 'An order outside the check window was asked about.');
        $this->assertSame(0, (int) $DB->get_field('paygw_payone_openorders', 'status', ['id' => $ooid]));
    }

    /**
     * An open order of a gateway without a status check never delays the release.
     *
     * Nobody can ask such a provider whether the user is still there, so the task must not wait:
     * otherwise every abandoned checkout through such a gateway would keep its items until the
     * window closes. paygw_aau has an open order table, but its transaction_complete does not
     * implement the interface.
     *
     * @covers \local_shopping_cart\task\delete_item_task::execute
     * @covers \local_shopping_cart\local\openorders::can_ask
     * @return void
     */
    public function test_a_gateway_without_status_check_never_delays_the_release(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('paygw_aau_openorders')) {
            $this->markTestSkipped('paygw_aau is not installed, no gateway without status check available.');
        }

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_payment($user->id);
        $this->create_open_order($user->id, $identifier, 0, 'paygw_aau_openorders');
        $this->assertFalse(openorders::can_ask('aau'));
        $asked = 0;
        openorders::set_status_check_for_testing(function () use (&$asked) {
            $asked++;
        });

        $before = $DB->count_records('task_adhoc', ['classname' => '\\local_shopping_cart\\task\\delete_item_task']);
        $task = $this->cleanup_task($user->id);

        $this->assertFalse($this->run_task_and_item_is_kept($task, $user->id), 'A gateway without status check held the item.');
        $this->assertSame(0, $asked, 'A gateway without status check was asked.');
        $this->assertSame(
            $before,
            $DB->count_records('task_adhoc', ['classname' => '\\local_shopping_cart\\task\\delete_item_task']),
            'The task queued itself again although nothing was running.'
        );
    }
}
