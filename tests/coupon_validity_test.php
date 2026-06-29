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
        coupon::add_edit_coupon(0, 'PCTTEST', $percent, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'ABSTEST', 0.0, $absolute, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'EXPIRED', 10.0, 0.0, 'EUR', 0, 1, 0, time() - 1, $this->userid);

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
        coupon::add_edit_coupon(0, 'FUTURE', 10.0, 0.0, 'EUR', 0, 1, time() + 3600, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'ZERO', 0.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'TWICE', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'FIRST', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);
        coupon::add_edit_coupon(0, 'SECOND', 20.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'PCT', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);
        coupon::add_edit_coupon(0, 'ABS', 0.0, 15.0, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'CLEAR', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);

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
        coupon::add_edit_coupon(0, 'ITEMCHECK', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $this->userid);

        $couponobj = new coupon($this->userid);
        $couponobj->apply_coupon_code('ITEMCHECK');
        $couponobj->apply_coupon_code('');

        $items = cartstore::instance($this->userid)->get_items();
        $prices = array_column($items, 'price', 'itemid');

        $this->assertEqualsWithDelta(10.00, (float) $prices[1], 0.001);
        $this->assertEqualsWithDelta(20.30, (float) $prices[2], 0.001);
        $this->assertEqualsWithDelta(13.80, (float) $prices[3], 0.001);
    }
}
