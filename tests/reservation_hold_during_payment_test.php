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
 * Tests that a reservation is only released on a final answer of the payment provider.
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
 * As long as an open order of this user is neither completed nor definitively failed, the seat
 * stays reserved. Neither the expiration clean-up nor the user may give it away, because the money
 * may still be on its way.
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
     * @return int
     */
    private function create_open_order(int $userid, int $identifier, int $status): int {
        global $DB;

        $record = new stdClass();
        $record->tid = '1000' . $identifier;
        $record->itemid = $identifier;
        $record->userid = $userid;
        $record->price = 30.30;
        $record->status = $status;
        $record->timecreated = time();
        $record->timemodified = time();

        return (int) $DB->insert_record('paygw_payone_openorders', $record);
    }

    /**
     * While the provider has not answered, the cart must not be cleaned up.
     *
     * @covers \local_shopping_cart\local\openorders::has_pending_order
     * @covers \local_shopping_cart\local\cartstore::get_data
     * @return void
     */
    public function test_cart_is_held_while_the_provider_has_not_answered(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $starttime = time();
        $identifier = $this->start_payment($user->id);
        $this->create_open_order($user->id, $identifier, 0);

        $this->assertTrue(openorders::has_pending_order($user->id));

        // Even beyond the prolonged payment time the seat is not given away.
        time_mock::set_mock_time($starttime + (self::PROLONGEDTIME + 10) * 60);

        $data = cartstore::instance($user->id)->get_data();

        $this->assertCount(2, $data['items'], 'The reservation was released while the payment was still open.');
    }

    /**
     * A definitive failure of the provider releases the seat.
     *
     * @covers \local_shopping_cart\local\openorders::has_pending_order
     * @covers \local_shopping_cart\local\cartstore::get_data
     * @return void
     */
    public function test_cart_is_released_when_the_provider_reports_a_failure(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $starttime = time();
        $identifier = $this->start_payment($user->id);
        $ooid = $this->create_open_order($user->id, $identifier, 0);

        // The provider has rejected the payment.
        $DB->set_field('paygw_payone_openorders', 'status', 2, ['id' => $ooid]);

        $this->assertFalse(openorders::has_pending_order($user->id));

        time_mock::set_mock_time($starttime + (self::PROLONGEDTIME + 10) * 60);

        $data = cartstore::instance($user->id)->get_data();

        $this->assertCount(0, $data['items'], 'A rejected payment has to release the reservation.');
    }

    /**
     * The user must not be able to give the seat away while the payment is on its way.
     *
     * @covers \local_shopping_cart\shopping_cart::delete_item_from_cart
     * @return void
     */
    public function test_items_are_not_deleted_while_a_payment_is_pending(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_payment($user->id);
        $this->create_open_order($user->id, $identifier, 0);

        shopping_cart::delete_item_from_cart('local_shopping_cart', 'testitem', 1, $user->id);

        $data = cartstore::instance($user->id)->get_data();
        $this->assertCount(2, $data['items'], 'An item was released while the payment was still open.');
    }
}
