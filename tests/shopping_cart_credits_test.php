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
 * phpUnit shopping_cart_credits_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart_generator;
use local_shopping_cart\local\cartstore;

/**
 * Test for shopping_cart_credits
 * @covers \shopping_cart_credits
 */
final class shopping_cart_credits_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        // Mandatory clean-up.
        cartstore::reset();
    }

    /**
     * Test shopping_cart_credits - single
     *
     * @covers \shopping_cart_credits::add_credit
     * @covers \shopping_cart_credits::get_balance
     * @covers \shopping_cart_credits::get_balance_for_all_costcenters
     *
     * @return void
     *
     */
    public function test_shopping_cart_credits_simple_credits(): void {

        parent::setUp();
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();

        $balance0 = shopping_cart_credits::add_credit($user1->id, 10.10, 'EUR', '');

        // Check add_credit response.
        $this->assertIsArray($balance0);
        $this->assertEquals(10.1, $balance0[0]);
        $this->assertEquals('EUR', $balance0[1]);
        $this->assertEquals('', $balance0[2]);

        $balance1 = shopping_cart_credits::get_balance($user1->id);

        // Check get_balance response.
        $this->assertIsArray($balance1);
        $this->assertEquals(10.1, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertArrayNotHasKey(2, $balance1);

        // Check get_balance_for_all_costcenters response.
        $balance2 = shopping_cart_credits::get_balance_for_all_costcenters($user1->id);
        $this->assertIsArray($balance2);
        $balance2 = array_shift($balance2);
        $this->assertIsArray($balance2);
        $this->assertEquals(10.1, $balance2['balance']);
        $this->assertEquals('EUR', $balance2['currency']);
        $this->assertEquals('', $balance2['costcenter']);
        $this->assertEquals('', $balance2['costcenterlabel']);

        // Check add_credit and get_balance are the same.
        $this->assertEquals($balance0[0], $balance1[0]);
        $this->assertEquals($balance0[1], $balance1[1]);
        $this->assertEquals($balance0[0], $balance2['balance']);
        $this->assertEquals($balance0[1], $balance2['currency']);
    }

    /**
     * Test shopping_cart_credits - per costcenters
     *
     * @covers \shopping_cart_credits::add_credit
     * @covers \shopping_cart_credits::get_balance
     * @covers \shopping_cart_credits::get_balance_for_all_costcenters
     *
     * @return void
     *
     */
    public function test_shopping_cart_credits_costcenter_credits(): void {

        parent::setUp();
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();

        $balance01 = shopping_cart_credits::add_credit($user1->id, 11.10, 'EUR', 'CC1');
        $balance02 = shopping_cart_credits::add_credit($user1->id, 22.20, 'EUR', 'CC2');

        // Check add_credit response.
        $this->assertIsArray($balance01);
        $this->assertEquals(11.1, $balance01[0]);
        $this->assertEquals('EUR', $balance01[1]);
        $this->assertEquals('CC1', $balance01[2]);
        $this->assertIsArray($balance02);
        $this->assertEquals(22.2, $balance02[0]);
        $this->assertEquals('EUR', $balance02[1]);
        $this->assertEquals('CC2', $balance02[2]);

        $balance11 = shopping_cart_credits::get_balance($user1->id, 'CC1');
        $balance12 = shopping_cart_credits::get_balance($user1->id, 'CC2');

        // Check get_balance response.
        $this->assertIsArray($balance11);
        $this->assertEquals(11.1, $balance11[0]);
        $this->assertEquals('EUR', $balance11[1]);
        $this->assertArrayNotHasKey(2, $balance11);
        $this->assertIsArray($balance12);
        $this->assertEquals(22.2, $balance12[0]);
        $this->assertEquals('EUR', $balance12[1]);
        $this->assertArrayNotHasKey(2, $balance12);

        // Check get_balance_for_all_costcenters response.
        $balance2 = shopping_cart_credits::get_balance_for_all_costcenters($user1->id);
        $this->assertIsArray($balance2);
        $balance21 = array_shift($balance2);
        $this->assertIsArray($balance2);
        $this->assertEquals(11.1, $balance21['balance']);
        $this->assertEquals('EUR', $balance21['currency']);
        $this->assertEquals('CC1', $balance21['costcenter']);
        $this->assertEquals('CC1', $balance21['costcenterlabel']);
        $balance22 = array_shift($balance2);
        $this->assertIsArray($balance2);
        $this->assertEquals(22.2, $balance22['balance']);
        $this->assertEquals('EUR', $balance22['currency']);
        $this->assertEquals('CC2', $balance22['costcenter']);
        $this->assertEquals('CC2', $balance22['costcenterlabel']);

        // Check add_credit and get_balance are the same.
        $this->assertEquals($balance01[0], $balance11[0]);
        $this->assertEquals($balance01[1], $balance11[1]);
        $this->assertEquals($balance02[0], $balance12[0]);
        $this->assertEquals($balance02[1], $balance12[1]);
        $this->assertEquals($balance01[0], $balance21['balance']);
        $this->assertEquals($balance01[1], $balance21['currency']);
        $this->assertEquals($balance02[0], $balance22['balance']);
        $this->assertEquals($balance02[1], $balance22['currency']);
        $this->assertEquals($balance01[2], $balance21['costcenter']);
        $this->assertEquals($balance02[2], $balance22['costcenter']);
    }

    /**
     * Test shopping_cart_credits refund by cache
     *
     * @covers \shopping_cart_credits::add_credit
     * @covers \shopping_cart_credits::get_balance
     * @covers \shopping_cart_credits::credit_paid_back
     *
     * @return void
     *
     */
    public function test_shopping_cart_credits_refund(): void {

        parent::setUp();
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();

        // Test refund by cache - no costcenter.
        $balance1 = shopping_cart_credits::add_credit($user1->id, 100, 'EUR', '');

        $this->assertIsArray($balance1);
        $this->assertEquals(100, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertEquals('', $balance1[2]);

        shopping_cart_credits::credit_paid_back($user1->id, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH);

        $balance1 = shopping_cart_credits::get_balance($user1->id);
        $this->assertIsArray($balance1);
        $this->assertEquals(0, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertArrayNotHasKey(2, $balance1);

        // Test refund by transfer - no costcenter.
        $balance1 = shopping_cart_credits::add_credit($user1->id, 120, 'EUR', '');

        $this->assertIsArray($balance1);
        $this->assertEquals(120, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertEquals('', $balance1[2]);

        shopping_cart_credits::credit_paid_back($user1->id, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER);

        $balance1 = shopping_cart_credits::get_balance($user1->id);
        $this->assertIsArray($balance1);
        $this->assertEquals(0, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertArrayNotHasKey(2, $balance1);

        // Test refund by cache - costcenter given.
        $balance1 = shopping_cart_credits::add_credit($user1->id, 130, 'EUR', 'CC1');

        $this->assertIsArray($balance1);
        $this->assertEquals(130, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertEquals('CC1', $balance1[2]);

        shopping_cart_credits::credit_paid_back(
            $user1->id,
            LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH,
            'CC1'
        );

        $balance1 = shopping_cart_credits::get_balance($user1->id);
        $this->assertIsArray($balance1);
        $this->assertEquals(0, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertArrayNotHasKey(2, $balance1);

        // Test refund by transfer - costcenter given.
        $balance1 = shopping_cart_credits::add_credit($user1->id, 140, 'EUR', 'CC2');

        $this->assertIsArray($balance1);
        $this->assertEquals(140, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertEquals('CC2', $balance1[2]);

        shopping_cart_credits::credit_paid_back(
            $user1->id,
            LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER,
            'CC2'
        );

        $balance1 = shopping_cart_credits::get_balance($user1->id);
        $this->assertIsArray($balance1);
        $this->assertEquals(0, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertArrayNotHasKey(2, $balance1);

        $balance2 = shopping_cart_credits::get_balance_for_all_costcenters($user1->id);
        $this->assertEmpty($balance2);
    }

    /**
     * Test test_cartstore_get_costcenter
     *
     * User selects two items with costcenters and enough credits in nocostcenter plus matching costcenter
     * when default costcenter is being set and than make checkout
     *
     * @covers \cartstore
     */
    public function test_cartstore_two_costcenters_enough_credits_nocostcenter_matchingcc_with_defaultcc(): void {

        parent::setUp();
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();

        $this->setAdminUser();
        // Clean cart.
        shopping_cart::delete_all_items_from_cart($user1->id);
        // Put item in cart.
        shopping_cart::buy_for_user($user1->id);

        // Instantiate cartstore and add items to the shopping cart.
        $cartstore = cartstore::instance((int)$user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'option',
            7,
            $user1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'option',
            8,
            $user1->id
        );

        // Set credits for all costcenters + define default CC.
        set_config('defaultcostcenterforcredits', 'CostCenter1', 'local_shopping_cart');
        shopping_cart_credits::add_credit($user1->id, 30, 'EUR');
        shopping_cart_credits::add_credit($user1->id, 13, 'EUR', 'CostCenter1');
        shopping_cart_credits::add_credit($user1->id, 14, 'EUR', 'CostCenter2');

        $data = $cartstore->get_data();

        // Check total price, costcenter and credits.
        // CostCenter2 + noname costcenter has to be used.
        $this->assertEmpty($data['price']);
        $this->assertEquals(9.9, $data['remainingcredit']);
        $this->assertEquals(34.1, $data['deductible']);
        $this->assertEquals(34.1, $data['initialtotal']);
        $this->assertEquals(44, $data['credit']);
        $this->assertEquals('CostCenter2', $data['costcenter']);
        $this->assertArrayHasKey('items', $data);
        $this->assertEquals(2, count($data['items']));

        // Confirm purchase.
        shopping_cart::confirm_payment($user1->id, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH);

        // Get final balance of credits.
        $balance1 = shopping_cart_credits::get_balance_for_all_costcenters($user1->id);
        $this->assertIsArray($balance1);
        $balance11 = array_shift($balance1);
        $this->assertIsArray($balance11);
        $this->assertEquals(13, $balance11['balance']);
        $this->assertEquals('EUR', $balance11['currency']);
        $this->assertEquals('CostCenter1', $balance11['costcenter']);
        $this->assertEquals('CostCenter1', $balance11['costcenterlabel']);
        $balance12 = array_shift($balance1);
        $this->assertIsArray($balance12);
        $this->assertEquals(9.9, $balance12['balance']);
        $this->assertEquals('EUR', $balance12['currency']);
        $this->assertEquals('CostCenter2', $balance12['costcenter']);
        $this->assertEquals('CostCenter2', $balance12['costcenterlabel']);
    }

    /**
     * Test test_cartstore_get_costcenter
     *
     * User selects two items with costcenters and no no enough credits in both nocostcenter
     * and dedicated costcenters and no default costcenter than proceed to checkout
     *
     * @covers \cartstore
     */
    public function test_cartstore_two_costcenters_not_enough_credits(): void {

        parent::setUp();
        $this->resetAfterTest(true);

        $user1 = $this->getDataGenerator()->create_user();

        $cartstore = cartstore::instance((int)$user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'option',
            7,
            $user1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'option',
            8,
            $user1->id
        );

        // Set credits for all costcenters.
        shopping_cart_credits::add_credit($user1->id, 15, 'EUR');
        shopping_cart_credits::add_credit($user1->id, 13, 'EUR', 'CostCenter1');
        shopping_cart_credits::add_credit($user1->id, 14, 'EUR', 'CostCenter2');

        $data = $cartstore->get_data();

        // Check total price, costcenter and credits.
        // CostCenter2 + noname costcenter has to be used.
        $this->assertEmpty($data['remainingcredit']);
        $this->assertEquals(5.1, $data['price']);
        $this->assertEquals(29, $data['deductible']);
        $this->assertEquals(34.1, $data['initialtotal']);
        $this->assertEquals(29, $data['credit']);
        $this->assertEquals('CostCenter2', $data['costcenter']);
        $this->assertArrayHasKey('items', $data);
        $this->assertEquals(2, count($data['items']));
    }

    /**
     * Data provider for test_shopping_cart_credits_get_data
     *
     * @return array
     */
    public static function shopping_cart_credits_get_data_provider(): array {
        return [
            ['items'],
            ['expirationtime'],
            ['userid'],
            ['credit'],
            ['remainingcredit'],
            ['currency'],
            ['count'],
            ['maxitems'],
            ['price'],
            ['taxesenabled'],
            ['initialtotal'],
            ['deductible'],
            ['nowdate'],
            ['checkouturl'],
        ];
    }
}
