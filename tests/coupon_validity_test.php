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
 * Coupon validity and discount calculation tests.
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
use local_shopping_cart\shopping_cart;

/**
 * Tests for coupon code validation and discount calculation.
 *
 * Standard test cart (items 1+2+3 from mockitems):
 *   Item 1 → 10.00 EUR
 *   Item 2 → 20.30 EUR
 *   Item 3 → 13.80 EUR
 *   Total  → 44.10 EUR
 */
final class coupon_validity_test extends advanced_testcase {
    /** @var int */
    private int $userid;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        global $USER;
        $this->userid = (int) $USER->id;

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
     * Add items 1, 2, 3 from mockitems (10.00 + 20.30 + 13.80 = 44.10 EUR).
     */
    private function fill_cart(): void {
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $this->userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $this->userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 3, $this->userid);
    }

    /**
     * Data provider for valid percentage coupons.
     *
     * Per-item breakdown at 10%: 1.00 + 2.03 + 1.38 = 4.41
     * Per-item breakdown at 20%: 2.00 + 4.06 + 2.76 = 8.82
     *
     * Note: the DB column discountpercentage is numeric(3,1), so 100% cannot be
     * stored. Full-cart coverage is tested via the 100 EUR absolute coupon instead.
     *
     * @return array
     */
    public static function percentage_coupon_provider(): array {
        return [
            '10 percent' => [10.0, 4.41, 39.69],
            '20 percent' => [20.0, 8.82, 35.28],
        ];
    }

    /**
     * Data provider for valid absolute coupons.
     *
     * Absolute discount is consumed item by item in cart order until exhausted:
     *   5 EUR  — fully consumed by item 1 (10.00), items 2+3 unchanged → discount 5.00
     *  15 EUR  — item 1 absorbs 10.00, item 2 absorbs 5.00, item 3 unchanged → discount 15.00
     * 100 EUR  — larger than cart total, capped at 44.10 → price 0.00
     *
     * @return array
     */
    public static function absolute_coupon_provider(): array {
        return [
            '5 EUR consumed by first item' => [5.0, 5.0, 39.10],
            '15 EUR spans first two items' => [15.0, 15.0, 29.10],
            '100 EUR capped at cart total' => [100.0, 44.10, 0.0],
        ];
    }

    /**
     * A valid percentage coupon reduces the total and each item's price proportionally.
     *
     * @dataProvider percentage_coupon_provider
     * @covers \local_shopping_cart\local\coupon
     *
     * @param float $percent
     * @param float $expecteddiscount
     * @param float $expectedprice
     */
    public function test_valid_percentage_coupon(
        float $percent,
        float $expecteddiscount,
        float $expectedprice
    ): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'PCTTEST', $percent, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        [$success, $message] = $couponobj->apply_coupon_code('PCTTEST');

        $this->assertTrue($success, $message);

        $cartstore = cartstore::instance($this->userid);
        $couponmanager = new cart_coupon_manager($cartstore);
        $data = $cartstore->get_data();

        $this->assertTrue($couponmanager->coupon_applied());
        $this->assertSame('PCTTEST', $couponmanager->get_applied_coupon());
        $this->assertEqualsWithDelta($expecteddiscount, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta($expectedprice, (float) $data['price'], 0.01);
    }

    /**
     * A valid absolute coupon reduces the total by the fixed amount, capped at cart total.
     *
     * @dataProvider absolute_coupon_provider
     * @covers \local_shopping_cart\local\coupon
     *
     * @param float $absolute
     * @param float $expecteddiscount
     * @param float $expectedprice
     */
    public function test_valid_absolute_coupon(
        float $absolute,
        float $expecteddiscount,
        float $expectedprice
    ): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'ABSTEST', 0.0, $absolute, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        [$success, $message] = $couponobj->apply_coupon_code('ABSTEST');

        $this->assertTrue($success, $message);

        $cartstore = cartstore::instance($this->userid);
        $couponmanager = new cart_coupon_manager($cartstore);
        $data = $cartstore->get_data();

        $this->assertTrue($couponmanager->coupon_applied());
        $this->assertSame('ABSTEST', $couponmanager->get_applied_coupon());
        $this->assertEqualsWithDelta($expecteddiscount, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta($expectedprice, (float) $data['price'], 0.01);
    }

    /**
     * A coupon code that does not exist in the database must fail.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_nonexistent_coupon_code_fails(): void {
        $this->fill_cart();

        $couponobj = new coupon($this->userid);
        [$success, ] = $couponobj->apply_coupon_code('DOESNOTEXIST');

        $this->assertFalse($success);

        $cartstore = cartstore::instance($this->userid);
        $this->assertFalse((new cart_coupon_manager($cartstore))->coupon_applied());
    }

    /**
     * A coupon whose endtime is in the past must be rejected.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_expired_coupon_fails(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'EXPIRED', 10.0, 0.0, 'EUR', 0, 1, 0, time() - 1, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        [$success, ] = $couponobj->apply_coupon_code('EXPIRED');

        $this->assertFalse($success);

        $cartstore = cartstore::instance($this->userid);
        $couponmanager = new cart_coupon_manager($cartstore);
        $data = $cartstore->get_data();

        $this->assertFalse($couponmanager->coupon_applied());
        $this->assertEqualsWithDelta(0.0, (float) ($data['coupondiscount'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(44.10, (float) $data['price'], 0.01);
    }

    /**
     * A coupon whose starttime is in the future must be rejected.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_coupon_not_yet_valid_fails(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'FUTURE', 10.0, 0.0, 'EUR', 0, 1, time() + 3600, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        [$success, ] = $couponobj->apply_coupon_code('FUTURE');

        $this->assertFalse($success);

        $cartstore = cartstore::instance($this->userid);
        $couponmanager = new cart_coupon_manager($cartstore);
        $data = $cartstore->get_data();

        $this->assertFalse($couponmanager->coupon_applied());
        $this->assertEqualsWithDelta(44.10, (float) $data['price'], 0.01);
    }

    /**
     * A coupon with both discount fields set to zero is accepted by validation
     * but the modifier must not alter any prices.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_zero_discount_coupon_applies_no_discount(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'ZERO', 0.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        (new coupon($this->userid))->apply_coupon_code('ZERO');

        $cartstore = cartstore::instance($this->userid);
        $data = $cartstore->get_data();

        $this->assertEqualsWithDelta(0.0, (float) ($data['coupondiscount'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(44.10, (float) $data['price'], 0.01);
    }

    /**
     * Applying the same coupon code a second time must not double the discount.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_applying_same_coupon_twice_is_idempotent(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'TWICE', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        $couponobj->apply_coupon_code('TWICE');
        [$success, ] = $couponobj->apply_coupon_code('TWICE');

        $this->assertTrue($success);

        $data = cartstore::instance($this->userid)->get_data();
        $this->assertEqualsWithDelta(4.41, (float) $data['coupondiscount'], 0.01);
    }

    /**
     * Applying a second percentage coupon must replace the first one entirely.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_switching_percentage_coupon_replaces_old_discount(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'FIRST', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');
        coupon::add_edit_coupon(0, 'SECOND', 20.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        $couponobj->apply_coupon_code('FIRST');
        [$success, ] = $couponobj->apply_coupon_code('SECOND');

        $this->assertTrue($success);

        $cartstore = cartstore::instance($this->userid);
        $data = $cartstore->get_data();

        $this->assertSame('SECOND', (new cart_coupon_manager($cartstore))->get_applied_coupon());
        $this->assertEqualsWithDelta(8.82, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta(35.28, (float) $data['price'], 0.01);
    }

    /**
     * Switching from a percentage coupon to an absolute coupon and back must
     * produce the correct discount for whichever coupon is currently active.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_switching_between_percentage_and_absolute_coupon(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'PCT', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');
        coupon::add_edit_coupon(0, 'ABS', 0.0, 15.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);

        // Apply percentage, then switch to absolute.
        $couponobj->apply_coupon_code('PCT');
        $couponobj->apply_coupon_code('ABS');

        $cartstore = cartstore::instance($this->userid);
        $data = $cartstore->get_data();

        $this->assertSame('ABS', (new cart_coupon_manager($cartstore))->get_applied_coupon());
        $this->assertEqualsWithDelta(15.0, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta(29.10, (float) $data['price'], 0.01);

        // Switch back to percentage.
        $couponobj->apply_coupon_code('PCT');

        $cartstore2 = cartstore::instance($this->userid);
        $data2 = $cartstore2->get_data();

        $this->assertSame('PCT', (new cart_coupon_manager($cartstore2))->get_applied_coupon());
        $this->assertEqualsWithDelta(4.41, (float) $data2['coupondiscount'], 0.01);
    }

    /**
     * Passing an empty string removes the active coupon and restores the cart total.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_clearing_coupon_restores_original_prices(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'CLEAR', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        $couponobj->apply_coupon_code('CLEAR');
        [$success, ] = $couponobj->apply_coupon_code('');

        $this->assertTrue($success);

        $cartstore = cartstore::instance($this->userid);
        $couponmanager = new cart_coupon_manager($cartstore);
        $data = $cartstore->get_data();

        $this->assertFalse($couponmanager->coupon_applied());
        $this->assertSame('', $couponmanager->get_applied_coupon());
        $this->assertEqualsWithDelta(0.0, (float) ($data['coupondiscount'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(44.10, (float) $data['price'], 0.01);
    }

    /**
     * After clearing a coupon every individual item must revert to its original price.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_clearing_coupon_restores_per_item_prices(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'ITEMCHECK', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        $couponobj = new coupon($this->userid);
        $couponobj->apply_coupon_code('ITEMCHECK');
        $couponobj->apply_coupon_code('');

        $items = cartstore::instance($this->userid)->get_items();
        $prices = array_column($items, 'price', 'itemid');

        $this->assertEqualsWithDelta(10.00, (float) $prices[1], 0.001);
        $this->assertEqualsWithDelta(20.30, (float) $prices[2], 0.001);
        $this->assertEqualsWithDelta(13.80, (float) $prices[3], 0.001);
    }

    // Active / inactive tests.

    /**
     * A coupon with active = 0 must be rejected regardless of other fields.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_inactive_coupon_fails(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'INACTIVE', 10.0, 0.0, 'EUR', 0, 0, 0, 0, $this->userid, 'couponoptout');

        [$success, ] = (new coupon($this->userid))->apply_coupon_code('INACTIVE');

        $this->assertFalse($success);
        $this->assertFalse(
            (new cart_coupon_manager(cartstore::instance($this->userid)))->coupon_applied()
        );
    }

    /**
     * A coupon with active = 1 must be accepted (sanity check for the active flag).
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_active_coupon_succeeds(): void {
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'ACTIVE', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');

        [$success, ] = (new coupon($this->userid))->apply_coupon_code('ACTIVE');

        $this->assertTrue($success);
        $this->assertTrue(
            (new cart_coupon_manager(cartstore::instance($this->userid)))->coupon_applied()
        );
    }

    // Max-uses tests.

    /**
     * A coupon with maxnumber = 1 that has already been used once must be rejected.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_coupon_at_max_uses_fails(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'MAXONE', 10.0, 0.0, 'EUR', 1, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'MAXONE']);

        $this->insert_ledger_usage((string)$couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);

        [$success, ] = (new coupon($this->userid))->apply_coupon_code('MAXONE');

        $this->assertFalse($success);
        $this->assertFalse(
            (new cart_coupon_manager(cartstore::instance($this->userid)))->coupon_applied()
        );
    }

    /**
     * A coupon with maxnumber = 2 used once must still be accepted.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_coupon_below_max_uses_succeeds(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'MAXTWO', 10.0, 0.0, 'EUR', 2, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'MAXTWO']);

        $this->insert_ledger_usage((string)$couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);

        [$success, ] = (new coupon($this->userid))->apply_coupon_code('MAXTWO');

        $this->assertTrue($success);
    }

    /**
     * A coupon with maxnumber = 0 (unlimited) must always be accepted regardless of
     * how many successful history records reference it.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_unlimited_coupon_never_exhausted(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'UNLIMITED', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'UNLIMITED']);

        for ($i = 0; $i < 5; $i++) {
            $this->insert_ledger_usage((string)$couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000 + $i);
        }

        [$success, ] = (new coupon($this->userid))->apply_coupon_code('UNLIMITED');

        $this->assertTrue($success);
    }

    /**
     * A PENDING ledger record must not count toward the usage limit.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_pending_ledger_record_does_not_count_toward_max_uses(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'PENDINGTEST', 10.0, 0.0, 'EUR', 1, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'PENDINGTEST']);

        $this->insert_ledger_usage((string)$couponid, LOCAL_SHOPPING_CART_PAYMENT_PENDING);

        [$success, ] = (new coupon($this->userid))->apply_coupon_code('PENDINGTEST');

        $this->assertTrue($success);
    }

    /**
     * The usage limit message names the reason: the coupon is used up for everyone.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_max_uses_refusal_message(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'MAXMSG', 10.0, 0.0, 'EUR', 1, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'MAXMSG']);
        $this->insert_ledger_usage((string)$couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);

        [$success, $message] = $this->apply('MAXMSG');

        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('MAXMSG', 'couponmaxusesreached'), $message);
    }

    /**
     * Only the ledger counts. A history record alone (no successful ledger record) is not a usage.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_history_record_without_ledger_record_does_not_count(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'HISTONLY', 10.0, 0.0, 'EUR', 1, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'HISTONLY']);

        $DB->insert_record('local_shopping_cart_history', (object) [
            'userid' => $this->userid,
            'itemid' => 1,
            'itemname' => 'Test item',
            'price' => 9.00,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'testitem',
            'identifier' => 1000,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'coupon' => (string) $couponid,
            'usermodified' => $this->userid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        [$success, ] = $this->apply('HISTONLY');

        $this->assertTrue($success);
    }

    /**
     * Checkout mode: three discounted items paid under one identifier are one usage.
     *
     * @covers \local_shopping_cart\local\coupon::count_usages
     */
    public function test_checkout_mode_counts_one_usage_per_identifier(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(
            0,
            'PERCHECKOUT',
            10.0,
            0.0,
            'EUR',
            1,
            1,
            0,
            0,
            $this->userid,
            'couponoptout',
            0,
            coupon::COUNTMODE_CHECKOUT
        );
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'PERCHECKOUT']);

        // One checkout with three discounted items: the limit of 1 is not reached yet.
        $this->assertSame(0, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
        foreach ([1, 2, 3] as $itemid) {
            $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, $itemid);
        }
        // The same three records are a different number of usages depending on the mode.
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
        $this->assertSame(3, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));

        [$success, $message] = $this->apply('PERCHECKOUT');
        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('PERCHECKOUT', 'couponmaxusesreached'), $message);

        // With a limit of 2 the second checkout is still allowed.
        coupon::add_edit_coupon(
            $couponid,
            'PERCHECKOUT',
            10.0,
            0.0,
            'EUR',
            2,
            1,
            0,
            0,
            $this->userid,
            'couponoptout',
            0,
            coupon::COUNTMODE_CHECKOUT
        );
        [$success, $message] = $this->apply('PERCHECKOUT');
        $this->assertTrue($success, $message);
    }

    /**
     * Item mode: every discounted item is one usage, even within a single checkout.
     *
     * @covers \local_shopping_cart\local\coupon::count_usages
     */
    public function test_item_mode_counts_every_discounted_item(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(
            0,
            'PERITEM',
            10.0,
            0.0,
            'EUR',
            3,
            1,
            0,
            0,
            $this->userid,
            'couponoptout',
            0,
            coupon::COUNTMODE_ITEM
        );
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'PERITEM']);

        // Two discounted items in one checkout: 2 of 3 used, still valid.
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, 1);
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, 2);
        $this->assertSame(2, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
        [$success, $message] = $this->apply('PERITEM');
        $this->assertTrue($success, $message);
        (new cart_coupon_manager(cartstore::instance($this->userid)))->clear_coupon();

        // A third discounted item in a later checkout exhausts the coupon.
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1001, null, 3);
        $this->assertSame(3, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
        [$success, $message] = $this->apply('PERITEM');
        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('PERITEM', 'couponmaxusesreached'), $message);
    }

    /**
     * Per-user limit: the user who used the coupon is refused with the per-user message,
     * another user is still accepted, and the overall limit stays unlimited.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_per_user_limit_blocks_only_that_user(): void {
        global $DB;
        $this->fill_cart();
        $otheruser = $this->getDataGenerator()->create_user();
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, (int) $otheruser->id);

        coupon::add_edit_coupon(
            0,
            'PERUSER',
            10.0,
            0.0,
            'EUR',
            0,
            1,
            0,
            0,
            $this->userid,
            'couponoptout',
            1,
            coupon::COUNTMODE_CHECKOUT
        );
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'PERUSER']);

        // One checkout of the test user with two discounted items: one usage for this user.
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, 1);
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, 2);
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, $this->userid));
        $this->assertSame(0, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT, (int) $otheruser->id));

        [$success, $message] = $this->apply('PERUSER');
        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('PERUSER', 'couponmaxusesperuserreached'), $message);
        $this->assertFalse((new cart_coupon_manager(cartstore::instance($this->userid)))->coupon_applied());

        [$success, $message] = $this->apply('PERUSER', (int) $otheruser->id);
        $this->assertTrue($success, $message);
    }

    /**
     * Per-user limit in item mode: two discounted items in one checkout are two usages.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_per_user_limit_in_item_mode(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(
            0,
            'PERUSERITEM',
            10.0,
            0.0,
            'EUR',
            0,
            1,
            0,
            0,
            $this->userid,
            'couponoptout',
            2,
            coupon::COUNTMODE_ITEM
        );
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'PERUSERITEM']);

        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, 1);
        [$success, $message] = $this->apply('PERUSERITEM');
        $this->assertTrue($success, $message);
        (new cart_coupon_manager(cartstore::instance($this->userid)))->clear_coupon();

        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000, null, 2);
        [$success, $message] = $this->apply('PERUSERITEM');
        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('PERUSERITEM', 'couponmaxusesperuserreached'), $message);
    }

    /**
     * A coupon that is already applied is validated again when it is submitted again,
     * so a limit reached in the meantime removes it from the cart.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_reapplying_applied_coupon_revalidates_limits(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'REAPPLY', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout', 1);
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'REAPPLY']);

        [$success, $message] = $this->apply('REAPPLY');
        $this->assertTrue($success, $message);

        // The user's limit is reached elsewhere while the coupon is still in this cart.
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);

        [$success, $message] = $this->apply('REAPPLY');
        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('REAPPLY', 'couponmaxusesperuserreached'), $message);
        $this->assertFalse((new cart_coupon_manager(cartstore::instance($this->userid)))->coupon_applied());
        $this->assertEqualsWithDelta(0.0, (float) cartstore::instance($this->userid)->get_data()['coupondiscount'], 0.01);
    }

    /**
     * When both limits are reached, the overall limit is reported.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_overall_limit_is_reported_before_per_user_limit(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'BOTH', 10.0, 0.0, 'EUR', 1, 1, 0, 0, $this->userid, 'couponoptout', 1);
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'BOTH']);
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);

        [$success, $message] = $this->apply('BOTH');
        $this->assertFalse($success);
        $this->assertSame($this->refusal_message('BOTH', 'couponmaxusesreached'), $message);
    }

    /**
     * A cancelled purchase leaves a CANCELED ledger record that must not count as a usage.
     *
     * @covers \local_shopping_cart\local\coupon::count_usages
     */
    public function test_canceled_ledger_record_does_not_count(): void {
        global $DB;
        $this->fill_cart();
        coupon::add_edit_coupon(0, 'CANCELED', 10.0, 0.0, 'EUR', 2, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'CANCELED']);

        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, 1000);
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_CANCELED, 1000);
        $this->insert_ledger_usage((string) $couponid, LOCAL_SHOPPING_CART_PAYMENT_CANCELED, 1001);

        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_CHECKOUT));
        $this->assertSame(1, coupon::count_usages($couponid, coupon::COUNTMODE_ITEM));
        [$success, $message] = $this->apply('CANCELED');
        $this->assertTrue($success, $message);
    }

    /**
     * Saving a coupon with an unknown counting mode is refused.
     *
     * @covers \local_shopping_cart\local\coupon::add_edit_coupon
     */
    public function test_unknown_countmode_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        coupon::add_edit_coupon(0, 'BADMODE', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout', 0, 'weekly');
    }

    /**
     * An opt-in coupon only discounts items that explicitly enable it in their
     * item configuration; without any opted-in item it has no effect at all.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_optin_coupon_only_applies_to_opted_in_items(): void {
        global $DB;

        $this->fill_cart();
        coupon::add_edit_coupon(0, 'OPTIN10', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptin');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'OPTIN10'], MUST_EXIST);

        $couponobj = new coupon($this->userid);

        // Without any opt-in the coupon is accepted but discounts nothing.
        [$success, $message] = $couponobj->apply_coupon_code('OPTIN10');
        $this->assertTrue($success, $message);
        $data = cartstore::instance($this->userid)->get_data();
        $this->assertEqualsWithDelta(0.0, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta(44.10, (float) $data['price'], 0.01);

        // Item 1 opts in: only its 10.00 EUR get the 10% discount (1.00),
        // items 2 and 3 keep their full price.
        $this->set_iteminfo_json(1, ['couponoptin' => (string) $couponid]);
        $couponobj->apply_coupon_code('OPTIN10');

        $data = cartstore::instance($this->userid)->get_data();
        $this->assertEqualsWithDelta(1.0, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta(43.10, (float) $data['price'], 0.01);
    }

    /**
     * An opt-out coupon discounts every item except those that explicitly
     * exclude it in their item configuration.
     *
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_optout_coupon_skips_excluded_items(): void {
        global $DB;

        $this->fill_cart();
        coupon::add_edit_coupon(0, 'OPTOUT10', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid, 'couponoptout');
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'OPTOUT10'], MUST_EXIST);

        // Item 2 (20.30 EUR) opts out: only items 1 and 3 are discounted
        // (10% of 10.00 + 13.80 = 2.38).
        $this->set_iteminfo_json(2, ['couponoptout' => (string) $couponid]);

        [$success, $message] = (new coupon($this->userid))->apply_coupon_code('OPTOUT10');
        $this->assertTrue($success, $message);

        $data = cartstore::instance($this->userid)->get_data();
        $this->assertEqualsWithDelta(2.38, (float) $data['coupondiscount'], 0.01);
        $this->assertEqualsWithDelta(41.72, (float) $data['price'], 0.01);
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
            'usermodified' => $this->userid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Insert a minimal ledger record for a given coupon id and payment status.
     *
     * Usage counting relies on the ledger, where every discounted item of a checkout is one
     * record and all records of one checkout share the identifier.
     *
     * @param string $couponid numeric coupon id stored in the coupon column
     * @param int $paymentstatus
     * @param int $identifier the checkout identifier
     * @param int|null $userid the buyer, defaults to the test user
     * @param int $itemid
     */
    private function insert_ledger_usage(
        string $couponid,
        int $paymentstatus,
        int $identifier = 1000,
        ?int $userid = null,
        int $itemid = 1
    ): void {
        global $DB;

        $record = new \stdClass();
        $record->userid = $userid ?? $this->userid;
        $record->itemid = $itemid;
        $record->itemname = 'Test item';
        $record->price = 9.00;
        $record->discount = 1.00;
        $record->currency = 'EUR';
        $record->componentname = 'local_shopping_cart';
        $record->area = 'testitem';
        $record->identifier = $identifier;
        $record->paymentstatus = $paymentstatus;
        $record->coupon = $couponid;
        $record->usermodified = $this->userid;
        $record->timecreated = time();
        $record->timemodified = time();

        $DB->insert_record('local_shopping_cart_ledger', $record);
    }

    /**
     * Apply a coupon code for a user and return the result of apply_coupon_code().
     *
     * @param string $code
     * @param int|null $userid
     * @return array [bool $success, string $message]
     */
    private function apply(string $code, ?int $userid = null): array {
        return (new coupon($userid ?? $this->userid))->apply_coupon_code($code);
    }

    /**
     * The message a user gets when a coupon is refused for the given reason.
     *
     * @param string $code
     * @param string $reasonstring lang string identifier of the reason
     * @return string
     */
    private function refusal_message(string $code, string $reasonstring): string {
        return get_string('couponcouldnotbeapplied', 'local_shopping_cart', $code)
            . ' ' . get_string($reasonstring, 'local_shopping_cart');
    }
}
