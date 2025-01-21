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

namespace local_shopping_cart;

use advanced_testcase;
use core_payment\helper;
use core_payment\helper_test;
use local_shopping_cart\external\add_item_to_cart;
use local_shopping_cart\external\cancel_purchase;
use local_shopping_cart\external\confirm_cash_payment;
use local_shopping_cart\external\get_history_item;
use local_shopping_cart\local\cartstore;

/**
 * phpUnit cartitem_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shopping_cart_buy_and_cancel_test extends advanced_testcase {
    /**
     * Data provider for shopping cart tests.
     *
     * @return array
     */
    public static function shoppingcartprovider(): array {
        return [
            'basic item test' => [
                'component' => 'local_shopping_cart',
                'area' => 'testarea',
                'itemid' => 1,
                'itemname' => 'my test item',
                'userid' => 0,
                'paymenttype' => 3,
                'annotation' => 'test purchase',
                'discount' => ['percent' => 10, 'absolute' => 0],
                'historyid' => 1001,
                'credit' => 50.00,

            ],
            'another item test' => [
                'component' => 'local_shopping_cart',
                'area' => 'otherarea',
                'itemid' => 2,
                'itemname' => 'my test item',
                'userid' => 0,
                'paymenttype' => 3,
                'annotation' => 'another test purchase',
                'discount' => ['percent' => 0, 'absolute' => 5],
                'historyid' => 1002,
                'credit' => 25.00,
            ],
        ];
    }

    /**
     * Function to test puchase and cancelation.
     * @covers \local_shopping_cart\shopping_cart
     * @dataProvider shoppingcartprovider
     * @runInSeparateProcess
     */
    public function test_shoppingcart(
        string $component,
        string $area,
        int $itemid,
        string $itemname,
        int $userid,
        int $paymenttype,
        string $annotation,
        array $discount,
        int $historyid,
        float $credit
    ): void {

        $user = $this->getDataGenerator()->create_user();

        $this->setUser($user);

        $this->resetAfterTest();

        $account = helper::save_payment_account((object)['name' => 'Test 1', 'idnumber' => '']);
        $gateway = helper::save_payment_gateway(
            (object)['accountid' => $account->get('id'), 'gateway' => 'paypal', 'config' => 'T1']
        );

        // Step 1: Add item to cart.
        $addresult = add_item_to_cart::execute($component, $area, $itemid, $userid);
        $this->assertArrayHasKey('success', $addresult);
        $this->assertEquals($addresult['success'], 1, 'Item was not successfully added to cart.');

        $price = $addresult['price'];

        // Step 2: Apply discount.
        $this->setAdminUser();
        $cartstore = cartstore::instance($user->id);
        $cartstore->add_discount_to_item(
            $component,
            $area,
            $itemid,
            $discount['percent'],
            $discount['absolute']
        );

        // Check if discount is applied (mocked or verified result)
        $cartitems = $cartstore->get_items();
        $this->assertNotEmpty($cartitems, 'Cart items should not be empty after adding.');

        $cartitem = reset($cartitems);

        if (!empty($discount['percent'])) {
            $price = $price * ((100 - $discount['percent']) / 100);
            $this->assertEquals($price, $cartitem['price']);
        } else if (!empty($discount['absolute'])) {
            $price -= $discount['absolute'];
            $this->assertEquals($price, $cartitem['price']);
        }

        // Step 3: Confirm purchase.
        $purchaseresult = confirm_cash_payment::execute($user->id, $paymenttype, $annotation);
        $this->assertArrayHasKey('status', $purchaseresult);
        $this->assertEquals($purchaseresult['status'], 1, 'Purchase was not successfully confirmed.');

        $historyitem = get_history_item::execute($component, $area, $itemid, $user->id);

        // Step 4: Cancel purchase.
        $cancelresult = cancel_purchase::execute($component, $area, $itemid, $user->id, $historyitem['id'], 0);
        $this->assertArrayHasKey('success', $cancelresult);
        $this->assertEquals($cancelresult['success'], 1, 'Purchase was not successfully canceled.');
    }
}
