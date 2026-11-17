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
 * Tests for extending the cancellation deadline of existing purchases.
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\event\canceluntil_changed;
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Tests for extending the cancellation deadline of existing purchases.
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class extend_canceluntil_test extends advanced_testcase {
    /** @var int the item whose deadline is extended */
    private const ITEMID = 4711;

    /**
     * Tests set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Inserts a purchase into the history.
     *
     * @param int $userid the buyer
     * @param int|null $canceluntil
     * @param int $paymentstatus
     * @param array $overrides other fields of the record
     * @return int id of the history record
     */
    private function create_purchase(
        int $userid,
        ?int $canceluntil,
        int $paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        array $overrides = []
    ): int {
        global $DB;

        return $DB->insert_record('local_shopping_cart_history', (object)array_merge([
            'userid' => $userid,
            'itemid' => self::ITEMID,
            'itemname' => 'Test item',
            'price' => 42.00,
            'currency' => 'EUR',
            'componentname' => 'mod_booking',
            'area' => 'option',
            'identifier' => 1000000 + $userid,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => $paymentstatus,
            'usermodified' => $userid,
            'timecreated' => 1000,
            'timemodified' => 1000,
            'canceluntil' => $canceluntil,
        ], $overrides));
    }

    /**
     * Returns the stored deadline of a purchase.
     *
     * @param int $historyid
     * @return int
     */
    private function get_canceluntil(int $historyid): int {
        global $DB;
        return (int)$DB->get_field('local_shopping_cart_history', 'canceluntil', ['id' => $historyid]);
    }

    /**
     * Paid and pending purchases with an earlier deadline are extended, everything else is left alone.
     *
     * @covers \local_shopping_cart\shopping_cart_history::extend_canceluntil_for_item
     * @covers \local_shopping_cart\shopping_cart_history::get_purchases_with_earlier_canceluntil
     */
    public function test_only_matching_purchases_with_earlier_deadline_are_extended(): void {
        global $DB;

        $this->setAdminUser();
        $buyer = $this->getDataGenerator()->create_user();
        $old = time() - DAYSECS;
        $new = time() + 30 * DAYSECS;
        $later = $new + DAYSECS;

        $paid = $this->create_purchase($buyer->id, $old);
        $pending = $this->create_purchase($buyer->id, $old, LOCAL_SHOPPING_CART_PAYMENT_PENDING);
        $canceled = $this->create_purchase($buyer->id, $old, LOCAL_SHOPPING_CART_PAYMENT_CANCELED);
        $aborted = $this->create_purchase($buyer->id, $old, LOCAL_SHOPPING_CART_PAYMENT_ABORTED);
        $laterdeadline = $this->create_purchase($buyer->id, $later);
        $nodeadline = $this->create_purchase($buyer->id, 0);
        $nulldeadline = $this->create_purchase($buyer->id, null);
        $otheritem = $this->create_purchase($buyer->id, $old, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, ['itemid' => 4712]);
        $otherarea = $this->create_purchase($buyer->id, $old, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, ['area' => 'subbooking']);
        $othercomponent = $this->create_purchase(
            $buyer->id,
            $old,
            LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            ['componentname' => 'local_shopping_cart']
        );

        $this->assertCount(
            2,
            shopping_cart_history::get_purchases_with_earlier_canceluntil('mod_booking', 'option', self::ITEMID, $new)
        );

        $changed = shopping_cart_history::extend_canceluntil_for_item('mod_booking', 'option', self::ITEMID, $new);

        $this->assertSame(2, $changed);
        $this->assertSame($new, $this->get_canceluntil($paid));
        $this->assertSame($new, $this->get_canceluntil($pending));
        $this->assertSame($old, $this->get_canceluntil($canceled));
        $this->assertSame($old, $this->get_canceluntil($aborted));
        $this->assertSame($later, $this->get_canceluntil($laterdeadline), 'A later deadline is never shortened.');
        $this->assertSame(0, $this->get_canceluntil($nodeadline), 'No deadline is never replaced by a date.');
        $this->assertNull($DB->get_field('local_shopping_cart_history', 'canceluntil', ['id' => $nulldeadline]));
        $this->assertSame($old, $this->get_canceluntil($otheritem));
        $this->assertSame($old, $this->get_canceluntil($otherarea));
        $this->assertSame($old, $this->get_canceluntil($othercomponent));

        // The fields that document the payment stay as they are.
        $record = $DB->get_record('local_shopping_cart_history', ['id' => $paid]);
        $this->assertEquals($buyer->id, $record->usermodified);
        $this->assertEquals(1000, $record->timemodified);

        // A second run finds nothing to do.
        $this->assertSame(0, shopping_cart_history::extend_canceluntil_for_item('mod_booking', 'option', self::ITEMID, $new));
    }

    /**
     * Removing the deadline (0) is the longest possible extension.
     *
     * @covers \local_shopping_cart\shopping_cart_history::extend_canceluntil_for_item
     */
    public function test_no_deadline_extends_every_purchase_with_a_deadline(): void {
        $this->setAdminUser();
        $buyer = $this->getDataGenerator()->create_user();

        $past = $this->create_purchase($buyer->id, time() - DAYSECS);
        $future = $this->create_purchase($buyer->id, time() + 300 * DAYSECS);

        $this->assertSame(2, shopping_cart_history::extend_canceluntil_for_item('mod_booking', 'option', self::ITEMID, 0));
        $this->assertSame(0, $this->get_canceluntil($past));
        $this->assertSame(0, $this->get_canceluntil($future));
    }

    /**
     * Every changed purchase is logged with the user who changed it, the buyer, the old and the new date.
     *
     * @covers \local_shopping_cart\shopping_cart_history::extend_canceluntil_for_item
     * @covers \local_shopping_cart\event\canceluntil_changed
     */
    public function test_every_changed_purchase_is_logged(): void {
        global $USER;

        $this->setAdminUser();
        $buyera = $this->getDataGenerator()->create_user();
        $buyerb = $this->getDataGenerator()->create_user();
        $olda = time() - 2 * DAYSECS;
        $oldb = time() - DAYSECS;
        $new = time() + 30 * DAYSECS;

        $historya = $this->create_purchase($buyera->id, $olda);
        $historyb = $this->create_purchase($buyerb->id, $oldb);
        // Not changed, so not logged.
        $this->create_purchase($buyerb->id, $new + DAYSECS);

        $sink = $this->redirectEvents();
        shopping_cart_history::extend_canceluntil_for_item('mod_booking', 'option', self::ITEMID, $new);
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof canceluntil_changed
        ));
        $sink->close();

        $this->assertCount(2, $events);

        $expected = [
            [$historya, $buyera->id, $olda],
            [$historyb, $buyerb->id, $oldb],
        ];
        foreach ($expected as $index => [$historyid, $buyerid, $old]) {
            $event = $events[$index];
            $this->assertEquals($USER->id, $event->userid);
            $this->assertEquals($buyerid, $event->relateduserid);
            $this->assertEquals($historyid, $event->objectid);
            $this->assertSame('local_shopping_cart_history', $event->objecttable);
            $this->assertSame($historyid, $event->other['historyid']);
            $this->assertSame(self::ITEMID, $event->other['itemid']);
            $this->assertSame('mod_booking', $event->other['component']);
            $this->assertSame('option', $event->other['area']);
            $this->assertSame($old, $event->other['oldcanceluntil']);
            $this->assertSame($new, $event->other['newcanceluntil']);
            $this->assertStringContainsString(
                userdate($new, get_string('strftimedatetime', 'langconfig')),
                $event->get_description()
            );
        }
    }

    /**
     * Without the cashier capability nothing is changed.
     *
     * @covers \local_shopping_cart\shopping_cart_history::extend_canceluntil_for_item
     */
    public function test_cashier_capability_is_required(): void {
        $buyer = $this->getDataGenerator()->create_user();
        $old = time() - DAYSECS;
        $historyid = $this->create_purchase($buyer->id, $old);

        // Not even the buyer may extend the deadline of the own purchase.
        $this->setUser($buyer);

        try {
            shopping_cart_history::extend_canceluntil_for_item('mod_booking', 'option', self::ITEMID, time() + DAYSECS);
            $this->fail('A user without the cashier capability must not change deadlines.');
        } catch (required_capability_exception $e) {
            $this->assertSame($old, $this->get_canceluntil($historyid));
        }
    }
}
