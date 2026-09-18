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
 * Tests that a coupon is recorded in the ledger only on the items it was actually applied to.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\local\cart_coupon_manager;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\coupon;
use local_shopping_cart\payment\service_provider;

/**
 * Coupon usage in the ledger and in the history.
 *
 * Mock items (component local_shopping_cart, area testitem):
 *   Item 1 → 10.00 EUR
 *   Item 2 → 20.30 EUR
 *   Item 3 → 13.80 EUR
 *
 * The ledger is the source of truth for counting coupon usages, so an item that did not
 * receive any coupon discount must not carry the coupon id, neither in the ledger nor in
 * the history.
 *
 * @covers \local_shopping_cart\shopping_cart::confirm_payment
 */
final class coupon_ledger_usage_test extends advanced_testcase {
    /** @var int the buyer */
    private int $userid;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $this->userid = (int) $user->id;

        set_config('couponenabled', 1, 'local_shopping_cart');
        set_config('bookingfee', 0, 'local_shopping_cart');
        set_config('bookingfeevariable', 0, 'local_shopping_cart');
        set_config('rounddiscounts', 0, 'local_shopping_cart');
        set_config('enabletax', '0', 'local_shopping_cart');
    }

    /**
     * Mandatory clean-up after each test.
     */
    protected function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * Opt-out coupon with one excluded item: only the two discounted items carry the coupon.
     */
    public function test_optout_coupon_is_recorded_only_on_discounted_items(): void {
        $couponid = $this->create_coupon('OPTOUT10', 10.0, 0.0, 'couponoptout');
        $this->set_iteminfo_json(2, ['couponoptout' => (string) $couponid]);

        $this->fill_cart_and_apply('OPTOUT10');
        $this->checkout_cash();

        $this->assert_coupon_recorded($couponid, [1, 3], [2]);
    }

    /**
     * Opt-in coupon with a single opted-in item: only that item carries the coupon.
     */
    public function test_optin_coupon_is_recorded_only_on_opted_in_item(): void {
        $couponid = $this->create_coupon('OPTIN10', 10.0, 0.0, 'couponoptin');
        $this->set_iteminfo_json(1, ['couponoptin' => (string) $couponid]);

        $this->fill_cart_and_apply('OPTIN10');
        $this->checkout_cash();

        $this->assert_coupon_recorded($couponid, [1], [2, 3]);
    }

    /**
     * Opt-in coupon without any opted-in item: accepted, but no item carries the coupon.
     */
    public function test_optin_coupon_without_items_is_not_recorded(): void {
        $couponid = $this->create_coupon('OPTINNONE', 10.0, 0.0, 'couponoptin');

        $this->fill_cart_and_apply('OPTINNONE');
        $this->checkout_cash();

        $this->assert_coupon_recorded($couponid, [], [1, 2, 3]);
    }

    /**
     * An absolute coupon that is used up by the first item is recorded on that item only.
     */
    public function test_absolute_coupon_is_recorded_only_where_it_reduced_the_price(): void {
        $couponid = $this->create_coupon('FIVE', 0.0, 5.0, 'couponoptout');

        $this->fill_cart_and_apply('FIVE');
        $this->checkout_cash();

        $this->assert_coupon_recorded($couponid, [1], [2, 3]);
    }

    /**
     * Coupon applied to every item: every item carries it.
     */
    public function test_coupon_on_all_items_is_recorded_on_all_items(): void {
        $couponid = $this->create_coupon('ALL10', 10.0, 0.0, 'couponoptout');

        $this->fill_cart_and_apply('ALL10');
        $this->checkout_cash();

        $this->assert_coupon_recorded($couponid, [1, 2, 3], []);
    }

    /**
     * After a real checkout the ledger yields the usage counts the limits are checked against:
     * one per checkout, or one per discounted item, overall and per user.
     *
     * @covers \local_shopping_cart\local\coupon::count_usages
     */
    public function test_usage_counts_after_checkout(): void {
        $couponid = $this->create_coupon('COUNT10', 10.0, 0.0, 'couponoptout');
        $this->set_iteminfo_json(2, ['couponoptout' => (string) $couponid]);
        $otheruser = $this->getDataGenerator()->create_user();

        $this->fill_cart_and_apply('COUNT10');
        $this->checkout_cash();

        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
        $this->assertSame(2, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, $this->userid));
        $this->assertSame(2, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM, $this->userid));
        $this->assertSame(0, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, (int) $otheruser->id));
        $this->assertSame(0, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM, (int) $otheruser->id));

        // A second checkout of the same buyer adds one checkout and two items.
        $this->fill_cart_and_apply('COUNT10');
        $this->checkout_cash();

        $this->assertSame(2, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
        $this->assertSame(4, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
    }

    /**
     * A per-user limit of one checkout is enforced against the ledger after a real purchase.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_per_user_limit_after_checkout(): void {
        coupon::add_edit_coupon(0, 'ONCE', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptout', 1);

        $this->fill_cart_and_apply('ONCE');
        $this->checkout_cash();

        // The used coupon does not carry over into the next cart of the buyer.
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $this->userid);
        $cartstore = cartstore::instance($this->userid);
        $this->assertFalse((new cart_coupon_manager($cartstore))->coupon_applied());
        $this->assertEqualsWithDelta(0.0, (float) ($cartstore->get_data()['coupondiscount'] ?? 0), 0.01);

        [$success, $message] = (new coupon($this->userid))->apply_coupon_code('ONCE');
        $this->assertFalse($success);
        $this->assertSame(
            get_string('couponcouldnotbeapplied', 'local_shopping_cart', 'ONCE')
                . ' ' . get_string('couponmaxusesperuserreached', 'local_shopping_cart'),
            $message
        );

        $otheruser = $this->getDataGenerator()->create_user();
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, (int) $otheruser->id);
        [$success, $message] = (new coupon((int) $otheruser->id))->apply_coupon_code('ONCE');
        $this->assertTrue($success, $message);
    }

    /**
     * Data provider for the booking fee independence test.
     *
     * Items 1, 2, 3 cost 10.00 + 20.30 + 13.80 EUR, the booking fee is 3.00 EUR.
     *
     * @return array
     */
    public static function bookingfee_provider(): array {
        return [
            // 10 % of the three items only: 1.00 + 2.03 + 1.38.
            'percentage' => [10.0, 0.0, 4.41],
            // 15 EUR are spent on the items only: item 1 takes 10.00, item 2 takes 5.00.
            'absolute' => [0.0, 15.0, 15.0],
            // More than all items together: capped at the items, the fee is still paid.
            'absolute exceeding items' => [0.0, 100.0, 44.10],
        ];
    }

    /**
     * The booking fee is independent of any coupon: it is never discounted, never absorbs
     * part of an absolute coupon and never counts as a coupon usage.
     *
     * @dataProvider bookingfee_provider
     * @param float $percent
     * @param float $absolute
     * @param float $expecteddiscount
     */
    public function test_bookingfee_is_independent_of_coupon(float $percent, float $absolute, float $expecteddiscount): void {
        global $DB;
        set_config('bookingfee', 3, 'local_shopping_cart');
        set_config('bookingfeeonlyonce', 0, 'local_shopping_cart');

        $couponid = $this->create_coupon('FEE', $percent, $absolute, 'couponoptout');
        $this->fill_cart_and_apply('FEE');

        $data = cartstore::instance($this->userid)->get_data();
        $fees = array_filter($data['items'], fn($item) => $item['area'] === 'bookingfee');
        $this->assertCount(1, $fees, 'The booking fee must be in the cart');
        $fee = reset($fees);
        $this->assertEqualsWithDelta(3.0, (float) $fee['price'], 0.001);
        $this->assertEmpty($fee['coupondiscount'] ?? 0);
        $this->assertEqualsWithDelta($expecteddiscount, (float) $data['coupondiscount'], 0.001);
        $this->assertEqualsWithDelta(44.10 + 3.0 - $expecteddiscount, (float) $data['price'], 0.001);

        $this->checkout_cash();

        $feerecord = $DB->get_record('local_shopping_cart_ledger', [
            'userid' => $this->userid,
            'area' => 'bookingfee',
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        ], '*', MUST_EXIST);
        $this->assertEmpty($feerecord->coupon);
        $this->assertEmpty((float) $feerecord->discount);
        $this->assertEqualsWithDelta(3.0, (float) $feerecord->price, 0.001);

        $discounteditems = $absolute > 0 && $absolute < 44.10 ? 2 : 3;
        $this->assertSame($discounteditems, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
    }

    /**
     * A cancelled item gives its coupon usage back: per item right away, per checkout once
     * every discounted item of that checkout is cancelled.
     *
     * @covers \local_shopping_cart\local\coupon::count_usages
     */
    public function test_cancellation_frees_coupon_usage(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'STORNO', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptout', 1);
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'STORNO']);

        foreach ([1, 2] as $itemid) {
            shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $itemid, $this->userid);
        }
        [$success, $message] = (new coupon($this->userid))->apply_coupon_code('STORNO');
        $this->assertTrue($success, $message);
        $this->checkout_cash();

        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, $this->userid));
        $this->assertSame(2, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM, $this->userid));

        // Cancelling one of two items frees one item usage, the checkout still counts.
        $this->cancel_as_cashier(2);
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, $this->userid));
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM, $this->userid));
        $this->assert_apply_refused_for_user_limit('STORNO');

        // Cancelling the last item frees the checkout as well: the user may use the coupon again.
        $this->cancel_as_cashier(1);
        $this->assertSame(0, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, $this->userid));
        $this->assertSame(0, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM, $this->userid));
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 3, $this->userid);
        [$success, $message] = (new coupon($this->userid))->apply_coupon_code('STORNO');
        $this->assertTrue($success, $message);
    }

    /**
     * A partial refund keeps the purchase active and therefore keeps the coupon usage.
     *
     * @covers \local_shopping_cart\local\coupon::count_usages
     */
    public function test_partial_refund_keeps_coupon_usage(): void {
        global $DB;
        $couponid = $this->create_coupon('PARTIAL', 10.0, 0.0, 'couponoptout');
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 2, $this->userid);
        [$success, $message] = (new coupon($this->userid))->apply_coupon_code('PARTIAL');
        $this->assertTrue($success, $message);
        $this->checkout_cash();

        $result = shopping_cart::add_partial_refund('local_shopping_cart', 'main', 2, $this->userid, 5.0, 'Partial refund');
        $this->assertEquals(1, $result['success'], $result['error'] ?? '');

        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
    }

    /**
     * The same guarantee holds for the online payment path (payment gateway callback).
     */
    public function test_online_checkout_records_coupon_only_on_discounted_items(): void {
        $couponid = $this->create_coupon('ONLINE10', 10.0, 0.0, 'couponoptout');
        $this->set_iteminfo_json(2, ['couponoptout' => (string) $couponid]);

        $this->setUser($this->userid);
        $this->fill_cart_and_apply('ONLINE10');

        $cartstore = cartstore::instance($this->userid);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data, false);

        service_provider::get_payable('', (int) $data['identifier']);
        service_provider::deliver_order('', (int) $data['identifier'], 1, $this->userid);

        $this->assert_coupon_recorded($couponid, [1, 3], [2]);
    }

    /**
     * Create a coupon and return its id.
     *
     * @param string $code
     * @param float $percent
     * @param float $absolute
     * @param string $type
     * @return int
     */
    private function create_coupon(string $code, float $percent, float $absolute, string $type): int {
        global $DB;
        coupon::add_edit_coupon(0, $code, $percent, $absolute, 'EUR', 0, 1, 0, 0, 2, $type);
        return (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => $code], MUST_EXIST);
    }

    /**
     * Add items 1, 2, 3 to the buyer's cart and apply the coupon code.
     *
     * @param string $code
     */
    private function fill_cart_and_apply(string $code): void {
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $this->userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $this->userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 3, $this->userid);

        [$success, $message] = (new coupon($this->userid))->apply_coupon_code($code);
        $this->assertTrue($success, $message);
    }

    /**
     * Cashier checkout (admin pays cash for the buyer).
     */
    private function checkout_cash(): void {
        $this->setAdminUser();
        $result = shopping_cart::confirm_payment($this->userid, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH);
        $this->assertEquals(1, $result['status'], $result['error'] ?? '');
    }

    /**
     * Cancel a purchased "main" item of the buyer as cashier.
     *
     * @param int $itemid
     */
    private function cancel_as_cashier(int $itemid): void {
        global $DB;
        $this->setAdminUser();
        $historyid = (int) $DB->get_field('local_shopping_cart_history', 'id', [
            'userid' => $this->userid,
            'itemid' => $itemid,
            'area' => 'main',
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        ], MUST_EXIST);
        $result = shopping_cart::cancel_purchase($itemid, 'main', $this->userid, 'local_shopping_cart', $historyid, 1.0);
        $this->assertEquals(1, $result['success'], $result['error'] ?? '');
    }

    /**
     * Assert that the coupon is refused because the buyer's limit is reached.
     *
     * @param string $code
     */
    private function assert_apply_refused_for_user_limit(string $code): void {
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 3, $this->userid);
        [$success, $message] = (new coupon($this->userid))->apply_coupon_code($code);
        $this->assertFalse($success);
        $this->assertSame(
            get_string('couponcouldnotbeapplied', 'local_shopping_cart', $code)
                . ' ' . get_string('couponmaxusesperuserreached', 'local_shopping_cart'),
            $message
        );
        shopping_cart::delete_item_from_cart('local_shopping_cart', 'main', 3, $this->userid);
    }

    /**
     * Assert which items carry the coupon in the ledger and in the history.
     *
     * @param int $couponid
     * @param int[] $withcoupon item ids that must reference the coupon
     * @param int[] $withoutcoupon item ids that must not reference any coupon
     */
    private function assert_coupon_recorded(int $couponid, array $withcoupon, array $withoutcoupon): void {
        global $DB;

        foreach (['local_shopping_cart_ledger', 'local_shopping_cart_history'] as $table) {
            $records = $DB->get_records($table, [
                'userid' => $this->userid,
                'componentname' => 'local_shopping_cart',
                'area' => 'testitem',
                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            ]);
            $byitem = [];
            foreach ($records as $record) {
                $byitem[(int) $record->itemid] = $record;
            }
            $this->assertCount(3, $byitem, "$table: expected one successful record per item");

            foreach ($withcoupon as $itemid) {
                $this->assertSame(
                    (string) $couponid,
                    (string) $byitem[$itemid]->coupon,
                    "$table: item $itemid was discounted and must reference the coupon"
                );
                $this->assertGreaterThan(
                    0,
                    (float) $byitem[$itemid]->discount,
                    "$table: item $itemid must carry the coupon discount"
                );
            }
            foreach ($withoutcoupon as $itemid) {
                $this->assertEmpty(
                    $byitem[$itemid]->coupon,
                    "$table: item $itemid was not discounted and must not reference the coupon"
                );
                $this->assertEmpty(
                    (float) $byitem[$itemid]->discount,
                    "$table: item $itemid must not carry a discount"
                );
            }

            // The ledger is what usage counting relies on: exactly the discounted items count.
            $this->assertSame(
                count($withcoupon),
                $DB->count_records($table, ['coupon' => (string) $couponid]),
                "$table: number of records referencing the coupon"
            );
        }
    }

    /**
     * Store an iteminfo json for one of the mock test items, the same shape the
     * shopping_cart_handler persists for coupon opt-in/opt-out configuration.
     *
     * @param int $itemid
     * @param array $json
     */
    private function set_iteminfo_json(int $itemid, array $json): void {
        global $DB;

        $DB->insert_record('local_shopping_cart_iteminfo', (object) [
            'itemid' => $itemid,
            'componentname' => 'local_shopping_cart',
            'area' => 'testitem',
            'json' => json_encode($json),
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}
