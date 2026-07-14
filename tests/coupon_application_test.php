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
 * High-level coupon application tests.
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

/**
 * High-level tests for coupon application and pricing combinations.
 */
final class coupon_application_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
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
     * Data provider for coupon application combinations.
     *
     * @return array
     */
    public static function coupon_combinations_provider(): array {
        return [
            'no_tax_no_credit' => [false, false],
            'no_tax_with_credit' => [false, true],
            'tax_no_credit' => [true, false],
            'tax_with_credit' => [true, true],
        ];
    }

    /**
     * Test coupon application across taxes and credit combinations.
     *
     * @param bool $enabletax
     * @param bool $usecredit
     *
     * @dataProvider coupon_combinations_provider
     * @covers \local_shopping_cart\local\coupon
     */
    public function test_coupon_application_combinations(bool $enabletax, bool $usecredit): void {
        global $USER;

        $this->setAdminUser();
        $userid = (int) $USER->id;

        set_config('couponenabled', 1, 'local_shopping_cart');
        set_config('bookingfee', 0, 'local_shopping_cart');
        set_config('bookingfeevariable', 0, 'local_shopping_cart');
        // Ensure discounts are not rounded to full integers.
        set_config('rounddiscounts', 0, 'local_shopping_cart');

        if ($enabletax) {
            set_config('enabletax', '1', 'local_shopping_cart');
            set_config('defaulttaxcategory', 'A', 'local_shopping_cart');
            set_config('taxcategories', 'A:15 B:10 C:0', 'local_shopping_cart');
        } else {
            set_config('enabletax', '0', 'local_shopping_cart');
        }

        // Create a 10% percentage coupon (no fixed amount). Opt-out type, so it
        // applies to the whole cart without per-item opt-in configuration.
        coupon::add_edit_coupon(0, 'TEST10', 10.0, 0.0, 'EUR', 0, 1, 0, 0, $userid, 'couponoptout');

        // Add three items with different prices (from mockitems):
        // itemid 1 => 10.00, itemid 2 => 20.30, itemid 3 => 13.8
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 3, $userid);

        if ($usecredit) {
            shopping_cart_credits::add_credit($userid, 5.00, 'EUR', '');
        }

        $coupon = new coupon($userid);
        [$success, $couponmessage] = $coupon->apply_coupon_code('TEST10');
        $this->assertTrue($success, $couponmessage);

        shopping_cart::save_used_credit_state($userid, $usecredit ? 1 : 0);
        $cartstore = cartstore::instance($userid);
        $data = $cartstore->get_data();
        // Per-item coupon discounts are computed transiently by the price modifier
        // chain inside get_data(); the raw cached items (get_items()) never carry them.
        $items = $data['items'];

        $couponmanager = new cart_coupon_manager($cartstore);
        $this->assertTrue($couponmanager->coupon_applied());

        // Expected behavior (desired): 10% is applied to the whole cart total.
        $carttotal = 10.00 + 20.30 + 13.80;
        $expecteddiscount = round($carttotal * 0.10, 2); // 10% of whole cart.
        $this->assertEqualsWithDelta($expecteddiscount, (float) $data['coupondiscount'], 0.01);

        // Sanity check: ensure discount is distributed across items (not only first item).
        $discounteditemcount = $this->count_discounted_items($items);
        $this->assertGreaterThan(1, $discounteditemcount, 'Expected coupon discount to affect multiple items.');
        $this->assertEquals($enabletax ? 1 : 0, (int) $data['taxesenabled']);

        if ($usecredit) {
            $this->assertGreaterThan(0, (float) $data['deductible']);
        } else {
            $this->assertEquals(0.0, (float) $data['deductible']);
        }
    }

    /**
     * Sum discounts across cart items.
     *
     * @param array $items
     * @return float
     */
    private function sum_item_discounts(array $items): float {
        $total = 0.0;
        foreach ($items as $item) {
            if (isset($item['discount'])) {
                $total += (float) $item['discount'];
            }
        }
        return $total;
    }

    /**
     * Count items that received a coupon discount.
     *
     * @param array $items
     * @return int
     */
    private function count_discounted_items(array $items): int {
        $count = 0;

        foreach ($items as $item) {
            if (!empty($item['coupondiscount']) && (float) $item['coupondiscount'] > 0) {
                $count++;
            }
        }

        return $count;
    }
}
