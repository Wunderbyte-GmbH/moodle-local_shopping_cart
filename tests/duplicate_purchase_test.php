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
 * Tests the book keeping of an item the user already owns when the order is paid.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\callback\service_provider as callback_service_provider;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\mock\mockitems;
use local_shopping_cart\payment\service_provider;
use stdClass;

/**
 * Tests the book keeping of an item the user already owns when the order is paid.
 *
 * One call of checkout.php creates one identifier, so a user can have several checkouts open for
 * the same cart. That is intended. When two of them are paid, the money for both of them arrives,
 * but the component delivers only once: the second delivery reports that the user already owns the
 * item. That second amount must never be booked as a sale. It is given back as credit, it does not
 * make the order fail, and it is announced with its own event so that a rule can inform the office.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
final class duplicate_purchase_test extends \advanced_testcase {
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
        mockitems::reset_already_owned_itemids();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        mockitems::reset_failing_itemids();
        mockitems::reset_already_owned_itemids();
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
     * Returns the ledger rows of one order.
     *
     * @param int $identifier
     * @return array
     */
    private function ledger_rows(int $identifier): array {
        global $DB;
        return $DB->get_records('local_shopping_cart_ledger', ['identifier' => $identifier]);
    }

    /**
     * Collects the itemids per event name of one checkout run.
     *
     * @param int $userid
     * @return array event name without namespace => list of itemids
     */
    private function events_of_a_checkout(int $userid): array {
        $sink = $this->redirectEvents();
        $this->buy_three_items($userid);
        $events = $sink->get_events();
        $sink->close();

        $collected = [];
        foreach ($events as $event) {
            $data = $event->get_data();
            $name = substr(strrchr($data['eventname'], '\\'), 1);
            $collected[$name][] = (int) ($data['other']['itemid'] ?? 0);
        }

        foreach ($collected as $name => $itemids) {
            sort($itemids);
            $collected[$name] = $itemids;
        }

        return $collected;
    }

    /**
     * The amount paid for an item the user already owns comes back as credit, not as a sale.
     *
     * @covers \local_shopping_cart\shopping_cart::confirm_payment
     * @return void
     */
    public function test_an_already_owned_item_is_credited_instead_of_being_sold(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // The user already owns the second item of the cart, the component delivers nothing for it.
        mockitems::set_already_owned_itemids([2]);

        $identifier = $this->buy_three_items($user->id);

        $history = $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier], '', 'itemid, paymentstatus');
        $this->assertCount(3, $history);
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $history[1]->paymentstatus);
        $this->assertEquals(
            LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
            (int) $history[2]->paymentstatus,
            'An item the user already owns must not be recorded as a sale.'
        );
        $this->assertEquals(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $history[3]->paymentstatus);

        $paid = 0.0;
        $credited = 0.0;
        foreach ($this->ledger_rows($identifier) as $row) {
            $paid += (float) $row->price;
            $credited += (float) $row->credits;
        }

        // The cash column still has to match what the payment provider collected.
        $this->assertEqualsWithDelta(self::PRICE1 + self::PRICE2 + self::PRICE3, $paid, 0.001);
        $this->assertEqualsWithDelta(self::PRICE2, $credited, 0.001);

        [$balance] = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta(self::PRICE2, $balance, 0.001);
    }

    /**
     * A duplicate purchase gets its own event, so a rule can react on it.
     *
     * @covers \local_shopping_cart\shopping_cart::successful_checkout
     * @return void
     */
    public function test_duplicate_purchase_event_is_triggered_instead_of_item_bought(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        mockitems::set_already_owned_itemids([2]);

        $events = $this->events_of_a_checkout($user->id);

        $this->assertEquals([1, 3], $events['item_bought'] ?? [], 'Only delivered items count as bought.');
        $this->assertEquals([2], $events['duplicate_purchase'] ?? []);
        $this->assertArrayNotHasKey(
            'item_notbought',
            $events,
            'A duplicate is not a failed delivery, it must not be reported as one.'
        );
    }

    /**
     * A duplicate does not turn the order into a failed checkout.
     *
     * @covers \local_shopping_cart\shopping_cart::confirm_payment
     * @return void
     */
    public function test_an_already_owned_item_does_not_make_the_order_fail(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        mockitems::set_already_owned_itemids([2]);

        $events = $this->events_of_a_checkout($user->id);

        // The whole order was paid and everything that could be delivered was delivered, so the
        // order counts as confirmed. The user must not see an error for the duplicate.
        $this->assertArrayHasKey(
            'payment_confirmed',
            $events,
            'The order was paid in full, so it has to be confirmed.'
        );
    }

    /**
     * A failing delivery keeps its own, different handling.
     *
     * @covers \local_shopping_cart\shopping_cart::confirm_payment
     * @return void
     */
    public function test_a_failing_item_is_still_reported_as_not_bought(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        mockitems::set_failing_itemids([2]);

        $events = $this->events_of_a_checkout($user->id);

        $this->assertEquals([2], $events['item_notbought'] ?? []);
        $this->assertArrayNotHasKey(
            'duplicate_purchase',
            $events,
            'An item that could not be delivered is not a duplicate purchase.'
        );
    }

    /**
     * A component that does not know the new optional callback has to behave exactly as before.
     *
     * @covers \local_shopping_cart\shopping_cart::successful_checkout
     * @return void
     */
    public function test_a_plain_bool_from_the_component_keeps_its_meaning(): void {
        $this->assertEquals(
            callback_service_provider::DELIVERY_DELIVERED,
            shopping_cart::normalize_delivery_result(true)
        );
        $this->assertEquals(
            callback_service_provider::DELIVERY_FAILED,
            shopping_cart::normalize_delivery_result(false)
        );
        $this->assertEquals(
            callback_service_provider::DELIVERY_FAILED,
            shopping_cart::normalize_delivery_result(null),
            'A component that has no callback at all delivers nothing.'
        );
    }
}
