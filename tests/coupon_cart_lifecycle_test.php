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
 * Coupon behaviour over the life of a cart: changes, rounding, price web service, history, cancellation.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use core_external\external_api;
use local_shopping_cart\external\get_price;
use local_shopping_cart\local\cart_coupon_manager;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\coupon;
use local_shopping_cart\output\shoppingcart_history_list;

/**
 * Coupon behaviour over the life of a cart.
 *
 * Mock items: item 1 → 10.00 EUR, item 2 → 20.30 EUR, item 3 → 13.80 EUR.
 *
 * @covers \local_shopping_cart\local\pricemodifier\modifiers\coupon
 */
final class coupon_cart_lifecycle_test extends advanced_testcase {
    /** @var int the buyer */
    private int $userid;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // The purchase history list renders an error page without a payment account.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['name' => 'Account1']);
        set_config('accountid', $account->get('id'), 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->userid = (int) $user->id;
        $this->setAdminUser();

        set_config('couponenabled', 1, 'local_shopping_cart');
        set_config('bookingfee', 0, 'local_shopping_cart');
        set_config('bookingfeevariable', 0, 'local_shopping_cart');
        set_config('rounddiscounts', 0, 'local_shopping_cart');
        set_config('enabletax', '0', 'local_shopping_cart');
        set_config('cancelationfee', 0, 'local_shopping_cart');
    }

    /**
     * Mandatory clean-up after each test.
     */
    protected function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    // Cart changes after the coupon was applied.

    /**
     * An item added after a percentage coupon was applied is discounted as well.
     */
    public function test_item_added_after_apply_is_discounted(): void {
        $this->create_coupon('ADD10', 10.0, 0.0);
        $this->add_items([1, 2]);
        $this->apply('ADD10');
        $this->assert_cart(3.03, 27.27);

        $this->add_items([3]);

        $this->assert_cart(4.41, 39.69);
        $this->assert_item_discounts([1 => 1.00, 2 => 2.03, 3 => 1.38]);
    }

    /**
     * Removing a discounted item removes its share of a percentage coupon.
     */
    public function test_item_removed_after_apply_drops_its_discount(): void {
        $this->create_coupon('DEL10', 10.0, 0.0);
        $this->add_items([1, 2, 3]);
        $this->apply('DEL10');
        $this->assert_cart(4.41, 39.69);

        shopping_cart::delete_item_from_cart('local_shopping_cart', 'testitem', 2, $this->userid);

        $this->assert_cart(2.38, 21.42);
        $this->assert_item_discounts([1 => 1.00, 3 => 1.38]);
    }

    /**
     * An absolute coupon is redistributed when the item that absorbed it is removed.
     */
    public function test_absolute_coupon_is_redistributed_after_removal(): void {
        $this->create_coupon('ABS15', 0.0, 15.0);
        $this->add_items([1, 2, 3]);
        $this->apply('ABS15');
        $this->assert_item_discounts([1 => 10.00, 2 => 5.00, 3 => 0.0]);

        shopping_cart::delete_item_from_cart('local_shopping_cart', 'testitem', 1, $this->userid);

        $this->assert_cart(15.00, 19.10);
        $this->assert_item_discounts([2 => 15.00, 3 => 0.0]);
    }

    /**
     * An absolute coupon applied to a cart cheaper than the coupon grows with added items,
     * but never beyond the coupon value.
     */
    public function test_absolute_coupon_grows_with_added_items_up_to_its_value(): void {
        $this->create_coupon('ABS25', 0.0, 25.0);
        $this->add_items([1]);
        $this->apply('ABS25');
        $this->assert_cart(10.00, 0.00);

        $this->add_items([2]);

        $this->assert_cart(25.00, 5.30);
        $this->assert_item_discounts([1 => 10.00, 2 => 15.00]);
    }

    /**
     * Emptying the cart forgets the coupon.
     */
    public function test_emptying_cart_forgets_coupon(): void {
        $this->create_coupon('EMPTY10', 10.0, 0.0);
        $this->add_items([1, 2]);
        $this->apply('EMPTY10');

        shopping_cart::delete_all_items_from_cart($this->userid);

        $this->assertFalse((new cart_coupon_manager(cartstore::instance($this->userid)))->coupon_applied());
        $this->add_items([1]);
        $this->assert_cart(0.0, 10.00);
    }

    // Rounding.

    /**
     * Data provider for rounded discounts.
     *
     * @return array
     */
    public static function rounding_provider(): array {
        return [
            // 1.00 + 2.03 + 1.38 → 1 + 2 + 1.
            'percentage 10' => [10.0, 0.0, [1 => 1.0, 2 => 2.0, 3 => 1.0]],
            // 1.50 + 3.045 + 2.07 → 2 + 3 + 2.
            'percentage 15' => [15.0, 0.0, [1 => 2.0, 2 => 3.0, 3 => 2.0]],
            // A whole absolute amount is spent exactly.
            'absolute 15' => [0.0, 15.0, [1 => 10.0, 2 => 5.0, 3 => 0.0]],
            // 5.50 is floored to 5; the remaining 0.50 is below the precision and expires.
            'absolute 5.50' => [0.0, 5.5, [1 => 5.0, 2 => 0.0, 3 => 0.0]],
        ];
    }

    /**
     * With rounddiscounts every item discount is a whole amount and the total is their sum.
     *
     * @dataProvider rounding_provider
     * @param float $percent
     * @param float $absolute
     * @param array $expected expected discount per item id
     */
    public function test_rounded_discounts(float $percent, float $absolute, array $expected): void {
        set_config('rounddiscounts', 1, 'local_shopping_cart');
        $this->create_coupon('ROUND', $percent, $absolute);
        $this->add_items([1, 2, 3]);
        $this->apply('ROUND');

        $this->assert_item_discounts($expected);
        $total = array_sum($expected);
        $this->assert_cart($total, 44.10 - $total);
    }

    /**
     * Rounding must never give away more than the value of an absolute coupon.
     */
    public function test_rounded_absolute_discount_never_exceeds_coupon_value(): void {
        set_config('rounddiscounts', 1, 'local_shopping_cart');
        $this->create_coupon('HALF', 0.0, 5.5);
        $this->add_items([1, 2, 3]);
        $this->apply('HALF');

        $data = cartstore::instance($this->userid)->get_data();
        $this->assertLessThanOrEqual(5.5, (float) $data['coupondiscount']);
    }

    /**
     * Removing a coupon with rounded discounts restores the original prices exactly.
     */
    public function test_clearing_rounded_coupon_restores_prices(): void {
        set_config('rounddiscounts', 1, 'local_shopping_cart');
        $this->create_coupon('ROUNDCLEAR', 15.0, 0.0);
        $this->add_items([1, 2, 3]);
        $this->apply('ROUNDCLEAR');

        [$success, $message] = (new coupon($this->userid))->apply_coupon_code('');
        $this->assertTrue($success, $message);

        $this->assert_cart(0.0, 44.10);
        $this->assert_item_discounts([1 => 0.0, 2 => 0.0, 3 => 0.0]);
    }

    /**
     * Data provider for the initial total with and without taxes.
     *
     * @return array
     */
    public static function initialtotal_provider(): array {
        return [
            'no taxes' => [false, false],
            'taxes, gross item prices' => [true, false],
            'taxes, net item prices' => [true, true],
        ];
    }

    /**
     * The initial total always shows the amount before the coupon, with and without taxes.
     *
     * @dataProvider initialtotal_provider
     * @param bool $taxes
     * @param bool $itempriceisnet
     */
    public function test_initialtotal_is_amount_before_coupon(bool $taxes, bool $itempriceisnet): void {
        if ($taxes) {
            set_config('enabletax', '1', 'local_shopping_cart');
            set_config('defaulttaxcategory', 'A', 'local_shopping_cart');
            set_config('taxcategories', 'A:15 B:10 C:0', 'local_shopping_cart');
        }
        set_config('itempriceisnet', $itempriceisnet ? 1 : 0, 'local_shopping_cart');
        $this->create_coupon('INITIAL10', 10.0, 0.0);
        $this->add_items([1, 2, 3]);

        $before = cartstore::instance($this->userid)->get_data();
        $this->assertEqualsWithDelta((float) $before['price'], (float) $before['initialtotal'], 0.001);

        $this->apply('INITIAL10');
        $after = cartstore::instance($this->userid)->get_data();

        $this->assertGreaterThan(0, (float) $after['coupondiscount']);
        $this->assertLessThan((float) $before['price'], (float) $after['price']);
        $this->assertEqualsWithDelta((float) $before['initialtotal'], (float) $after['initialtotal'], 0.001);
        if ($taxes) {
            $this->assertEqualsWithDelta((float) $before['initialtotal_net'], (float) $after['initialtotal_net'], 0.001);
        }
    }

    // Price web service.

    /**
     * A price refresh from a page without coupon input must not remove the applied coupon.
     * @runInSeparateProcess
     */
    public function test_get_price_without_coupon_ui_keeps_applied_coupon(): void {
        $this->create_coupon('KEEP10', 10.0, 0.0);
        $this->add_items([1, 2, 3]);
        $this->apply('KEEP10');

        $result = $this->get_price('', false);

        $this->assertTrue($result['couponapplied']);
        $this->assertSame('KEEP10', $result['coupon']);
        $this->assertEqualsWithDelta(4.41, (float) $result['coupondiscount'], 0.001);
        $this->assertEqualsWithDelta(39.69, (float) $result['price'], 0.001);
        $this->assertSame('', $result['couponmessage']);
    }

    /**
     * Submitting a code through the price web service applies it.
     * @runInSeparateProcess
     */
    public function test_get_price_applies_code(): void {
        $this->create_coupon('WS10', 10.0, 0.0);
        $this->add_items([1, 2, 3]);

        $result = $this->get_price('WS10', true);

        $this->assertTrue($result['couponapplied']);
        $this->assertSame('WS10', $result['coupon']);
        $this->assertEqualsWithDelta(4.41, (float) $result['coupondiscount'], 0.001);
        $this->assertSame(get_string('couponappliedsuccessfully', 'local_shopping_cart', 'WS10'), $result['couponmessage']);
    }

    /**
     * An empty code from the coupon UI removes the applied coupon.
     * @runInSeparateProcess
     */
    public function test_get_price_with_empty_code_from_coupon_ui_removes_coupon(): void {
        $this->create_coupon('WSCLEAR', 10.0, 0.0);
        $this->add_items([1, 2, 3]);
        $this->apply('WSCLEAR');

        $result = $this->get_price('', true);

        $this->assertFalse($result['couponapplied']);
        $this->assertEqualsWithDelta(0.0, (float) $result['coupondiscount'], 0.001);
        $this->assertEqualsWithDelta(44.10, (float) $result['price'], 0.001);
        $this->assertSame(get_string('couponremovedsuccessfully', 'local_shopping_cart', 'WSCLEAR'), $result['couponmessage']);
    }

    /**
     * A refused code is reported through the web service and leaves the cart undiscounted.
     * @runInSeparateProcess
     */
    public function test_get_price_reports_refused_code(): void {
        global $DB;
        $this->create_coupon('WSUSED', 10.0, 0.0, 1);
        $couponid = $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'WSUSED']);
        $DB->insert_record('local_shopping_cart_ledger', (object) [
            'userid' => $this->userid,
            'itemid' => 1,
            'componentname' => 'local_shopping_cart',
            'area' => 'testitem',
            'identifier' => 1000,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'coupon' => (string) $couponid,
            'usermodified' => $this->userid,
            'timecreated' => time(),
        ]);
        $this->add_items([1, 2, 3]);

        $result = $this->get_price('WSUSED', true);

        $this->assertFalse($result['couponapplied']);
        $this->assertEqualsWithDelta(0.0, (float) $result['coupondiscount'], 0.001);
        $this->assertSame(
            get_string('couponcouldnotbeapplied', 'local_shopping_cart', 'WSUSED')
                . ' ' . get_string('couponmaxusesreached', 'local_shopping_cart'),
            $result['couponmessage']
        );
    }

    /**
     * With coupons switched off site-wide, the web service never discounts and hides the input.
     * @runInSeparateProcess
     */
    public function test_get_price_with_coupons_disabled(): void {
        $this->create_coupon('OFF10', 10.0, 0.0);
        $this->add_items([1, 2, 3]);
        $this->apply('OFF10');

        set_config('couponenabled', 0, 'local_shopping_cart');
        $result = $this->get_price('OFF10', true);

        $this->assertFalse($result['couponenabled']);
        $this->assertEqualsWithDelta(0.0, (float) $result['coupondiscount'], 0.001);
        $this->assertEqualsWithDelta(44.10, (float) $result['price'], 0.001);
    }

    // Purchase history display.

    /**
     * The purchase history shows the original price only for items the coupon discounted.
     */
    public function test_history_list_marks_only_discounted_items(): void {
        global $DB;
        $couponid = $this->create_coupon('HIST10', 10.0, 0.0);
        $this->set_iteminfo_json(2, 'testitem', ['couponoptout' => (string) $couponid]);
        $this->add_items([1, 2, 3]);
        $this->apply('HIST10');
        $this->checkout_cash();

        $list = (new shoppingcart_history_list($this->userid))->return_list();
        $byitem = [];
        foreach ($list['historyitems'] as $item) {
            $byitem[(int) $item['itemid']] = $item;
        }

        $this->assertSame('1.00', $byitem[1]['coupondiscount']);
        $this->assertSame('10.00', $byitem[1]['originalprice']);
        $this->assertSame('9.00', $byitem[1]['price']);
        $this->assertSame('1.38', $byitem[3]['coupondiscount']);
        $this->assertSame('13.80', $byitem[3]['originalprice']);
        $this->assertArrayNotHasKey('coupondiscount', $byitem[2]);
        $this->assertArrayNotHasKey('originalprice', $byitem[2]);
        $this->assertSame('20.30', $byitem[2]['price']);
    }

    // Cancellation.

    /**
     * Data provider for cancellations of a coupon-discounted item.
     *
     * Item 2 (20.30 EUR) was bought with a 10 % coupon, so 18.27 EUR were paid.
     *
     * @return array
     */
    public static function cancellation_provider(): array {
        return [
            'no cancelation fee' => [0.0, false, 18.27],
            'cancelation fee' => [3.0, false, 15.27],
            'cancelation fee above paid price' => [30.0, false, 0.0],
            'rounded refund' => [0.0, true, 18.0],
            'rounded refund with cancelation fee' => [3.0, true, 15.0],
        ];
    }

    /**
     * A self-cancellation refunds what was actually paid, not the price before the coupon.
     *
     * @dataProvider cancellation_provider
     * @covers \local_shopping_cart\shopping_cart::cancel_purchase
     * @param float $cancelationfee
     * @param bool $roundrefund
     * @param float $expectedrefund
     */
    public function test_cancellation_refunds_discounted_price(
        float $cancelationfee,
        bool $roundrefund,
        float $expectedrefund
    ): void {
        global $DB;
        $this->create_coupon('CANCEL10', 10.0, 0.0);
        foreach ([1, 2] as $itemid) {
            shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $itemid, $this->userid);
        }
        $this->apply('CANCEL10');
        $this->checkout_cash();

        $history = $DB->get_record('local_shopping_cart_history', [
            'userid' => $this->userid,
            'itemid' => 2,
            'area' => 'main',
        ], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(18.27, (float) $history->price, 0.001);
        $this->assertEqualsWithDelta(2.03, (float) $history->discount, 0.001);

        set_config('cancelationfee', $cancelationfee, 'local_shopping_cart');
        set_config('roundrefundamount', $roundrefund ? 1 : 0, 'local_shopping_cart');
        $this->setUser($this->userid);
        $result = shopping_cart::cancel_purchase(2, 'main', $this->userid, 'local_shopping_cart', (int) $history->id);
        $this->assertEquals(1, $result['success'], $result['error'] ?? '');

        [$balance] = shopping_cart_credits::get_balance($this->userid);
        $this->assertEqualsWithDelta($expectedrefund, (float) $balance, 0.001);

        $ledger = $DB->get_record('local_shopping_cart_ledger', [
            'schistoryid' => $history->id,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
        ], '*', MUST_EXIST);
        $this->assertEqualsWithDelta($expectedrefund, (float) $ledger->credits, 0.001);
        $this->assertEqualsWithDelta(min($cancelationfee, 18.27), (float) $ledger->fee, 0.001);

        // The other item of the same checkout is untouched.
        $this->assertTrue($DB->record_exists('local_shopping_cart_history', [
            'userid' => $this->userid,
            'itemid' => 1,
            'area' => 'main',
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        ]));
    }

    // Helpers.

    /**
     * Create an opt-out coupon and return its id.
     *
     * @param string $code
     * @param float $percent
     * @param float $absolute
     * @param int $maxnumber
     * @return int
     */
    private function create_coupon(string $code, float $percent, float $absolute, int $maxnumber = 0): int {
        global $DB;
        coupon::add_edit_coupon(0, $code, $percent, $absolute, 'EUR', $maxnumber, 1, 0, 0, 2, 'couponoptout');
        return (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => $code], MUST_EXIST);
    }

    /**
     * Add mock items to the buyer's cart.
     *
     * @param int[] $itemids
     */
    private function add_items(array $itemids): void {
        foreach ($itemids as $itemid) {
            shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', $itemid, $this->userid);
        }
    }

    /**
     * Apply a coupon code and require success.
     *
     * @param string $code
     */
    private function apply(string $code): void {
        [$success, $message] = (new coupon($this->userid))->apply_coupon_code($code);
        $this->assertTrue($success, $message);
    }

    /**
     * Cashier checkout for the buyer.
     */
    private function checkout_cash(): void {
        $this->setAdminUser();
        $result = shopping_cart::confirm_payment($this->userid, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH);
        $this->assertEquals(1, $result['status'], $result['error'] ?? '');
    }

    /**
     * Call the price web service for the buyer as admin.
     *
     * @param string $couponvalue
     * @param bool $couponenabled whether the calling page shows the coupon input
     * @return array
     */
    private function get_price(string $couponvalue, bool $couponenabled): array {
        $result = get_price::execute($this->userid, 0, 0, $couponvalue, $couponenabled);
        return external_api::clean_returnvalue(get_price::execute_returns(), $result);
    }

    /**
     * Assert the total coupon discount and the total price of the buyer's cart.
     *
     * @param float $discount
     * @param float $price
     */
    private function assert_cart(float $discount, float $price): void {
        $data = cartstore::instance($this->userid)->get_data();
        $this->assertEqualsWithDelta($discount, (float) ($data['coupondiscount'] ?? 0), 0.001, 'coupon discount');
        $this->assertEqualsWithDelta($price, (float) $data['price'], 0.001, 'cart price');
    }

    /**
     * Assert the coupon discount of every listed item in the buyer's cart.
     *
     * @param array $expected discount per item id
     */
    private function assert_item_discounts(array $expected): void {
        $data = cartstore::instance($this->userid)->get_data();
        $actual = [];
        foreach ($data['items'] as $item) {
            $actual[(int) $item['itemid']] = (float) ($item['coupondiscount'] ?? 0);
        }
        ksort($actual);
        ksort($expected);
        $this->assertSame(array_keys($expected), array_keys($actual), 'items in cart');
        foreach ($expected as $itemid => $discount) {
            $this->assertEqualsWithDelta($discount, $actual[$itemid], 0.001, "discount of item $itemid");
        }
    }

    /**
     * Store an iteminfo json for one of the mock items.
     *
     * @param int $itemid
     * @param string $area
     * @param array $json
     */
    private function set_iteminfo_json(int $itemid, string $area, array $json): void {
        global $DB;
        $DB->insert_record('local_shopping_cart_iteminfo', (object) [
            'itemid' => $itemid,
            'componentname' => 'local_shopping_cart',
            'area' => $area,
            'json' => json_encode($json),
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}
