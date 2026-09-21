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
 * Tests the book keeping of an order in which one item could not be delivered.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\cartstore;
use local_shopping_cart\mock\mockitems;
use local_shopping_cart\payment\service_provider;
use stdClass;

/**
 * Tests the book keeping of an order in which one item could not be delivered.
 *
 * The money has arrived at the payment provider, so every item of that order has to appear in the
 * ledger, no matter whether the item behind it could be delivered. An item that was paid but not
 * delivered is given back as credit, so the sum of the ledger still matches what was paid.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
final class partial_checkout_ledger_test extends \advanced_testcase {
    /** @var \core_payment\account account */
    private $account;

    /** @var float Price of mock item 1. */
    private const PRICE1 = 10.00;

    /** @var float Price of mock item 2. */
    private const PRICE2 = 20.30;

    /** @var float Price of mock item 3. */
    private const PRICE3 = 13.80;

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
        set_config('bookingfee', 0, 'local_shopping_cart');

        mockitems::reset_failing_itemids();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        mockitems::reset_failing_itemids();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * Puts three items into the cart and runs the whole online checkout for them.
     *
     * @param int $userid
     * @return int the cart identifier
     */
    private function buy_three_items(int $userid): int {
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 3, $userid);

        $cartstore = cartstore::instance($userid);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);

        $identifier = (int) $data['identifier'];

        service_provider::get_payable('', $identifier);
        service_provider::deliver_order('', $identifier, 1, $userid);

        return $identifier;
    }

    /**
     * Returns the ledger rows of one order, keyed by itemid.
     *
     * @param int $identifier
     * @return array
     */
    private function ledger_rows(int $identifier): array {
        global $DB;
        return $DB->get_records('local_shopping_cart_ledger', ['identifier' => $identifier]);
    }

    /**
     * An item that could not be delivered must not keep the other items out of the ledger.
     *
     * @covers \local_shopping_cart\shopping_cart::confirm_payment
     * @return void
     */
    public function test_a_failing_item_does_not_void_the_other_items(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // The first item of the cart is the one that cannot be delivered.
        mockitems::set_failing_itemids([1]);

        $identifier = $this->buy_three_items($user->id);

        $history = $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier], '', 'itemid, paymentstatus');
        $this->assertCount(3, $history);
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_CANCELED, (int) $history[1]->paymentstatus);
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $history[2]->paymentstatus);
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $history[3]->paymentstatus);

        $rows = $this->ledger_rows($identifier);

        $paid = 0.0;
        $credited = 0.0;
        $itemids = [];
        foreach ($rows as $row) {
            $paid += (float) $row->price;
            $credited += (float) $row->credits;
            if (!empty($row->itemid)) {
                $itemids[] = (int) $row->itemid;
            }
        }

        sort($itemids);
        $this->assertEquals([1, 2, 3], $itemids, 'Every paid item needs its own ledger row.');

        // The cash column has to match what the payment provider collected.
        $this->assertEqualsWithDelta(self::PRICE1 + self::PRICE2 + self::PRICE3, $paid, 0.001);

        // The item that was paid but not delivered comes back as credit.
        $this->assertEqualsWithDelta(self::PRICE1, $credited, 0.001);

        [$balance] = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta(self::PRICE1, $balance, 0.001);
    }

    /**
     * The same has to hold when the failing item is not the first one of the cart.
     *
     * @covers \local_shopping_cart\shopping_cart::confirm_payment
     * @return void
     */
    public function test_a_failing_item_in_the_middle_does_not_void_the_following_items(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        mockitems::set_failing_itemids([2]);

        $identifier = $this->buy_three_items($user->id);

        $history = $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier], '', 'itemid, paymentstatus');
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $history[1]->paymentstatus);
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_CANCELED, (int) $history[2]->paymentstatus);
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $history[3]->paymentstatus);

        $rows = $this->ledger_rows($identifier);
        $paid = 0.0;
        foreach ($rows as $row) {
            $paid += (float) $row->price;
        }
        $this->assertEqualsWithDelta(self::PRICE1 + self::PRICE2 + self::PRICE3, $paid, 0.001);

        [$balance] = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta(self::PRICE2, $balance, 0.001);
    }

    /**
     * An item that was not delivered must not be reported as bought.
     *
     * @covers \local_shopping_cart\shopping_cart::successful_checkout
     * @return void
     */
    public function test_item_bought_is_not_triggered_for_a_failing_item(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        mockitems::set_failing_itemids([2]);

        $sink = $this->redirectEvents();
        $this->buy_three_items($user->id);
        $events = $sink->get_events();
        $sink->close();

        $bought = [];
        $notbought = [];
        foreach ($events as $event) {
            $data = $event->get_data();
            if ($data['eventname'] === '\local_shopping_cart\event\item_bought') {
                $bought[] = (int) $data['other']['itemid'];
            } else if ($data['eventname'] === '\local_shopping_cart\event\item_notbought') {
                $notbought[] = (int) $data['other']['itemid'];
            }
        }

        sort($bought);
        $this->assertEquals([1, 3], $bought, 'Only delivered items count as bought.');
        $this->assertEquals([2], $notbought);
    }

    /**
     * Delivering the same order twice must not double the book keeping.
     *
     * @covers \local_shopping_cart\payment\service_provider::deliver_order
     * @return void
     */
    public function test_delivering_an_order_twice_does_not_double_the_ledger(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->buy_three_items($user->id);

        $this->assertCount(3, $this->ledger_rows($identifier));

        // This is what check_for_ongoing_payment() does on top of the gateway callback.
        service_provider::deliver_order('', $identifier, 1, $user->id);

        $this->assertCount(3, $this->ledger_rows($identifier), 'The order was booked twice.');
        $this->assertCount(3, $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier]));
    }
}
