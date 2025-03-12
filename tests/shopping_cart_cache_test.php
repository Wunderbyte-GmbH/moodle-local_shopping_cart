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
use cache_helper;
use core_payment\helper;
use local_shopping_cart\external\add_item_to_cart;
use local_shopping_cart\external\cancel_purchase;
use local_shopping_cart\external\confirm_cash_payment;
use local_shopping_cart\external\delete_item_from_cart;
use local_shopping_cart\external\get_history_item;
use local_shopping_cart\external\get_price;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\pricemodifier\modifiers\checkout;
use local_shopping_cart\payment\service_provider;

/**
 * phpUnit cartitem_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shopping_cart_cache_test extends advanced_testcase {
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
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param string $itemname
     * @param int $userid
     * @param int $paymenttype
     * @param string $annotation
     * @param array $discount
     * @param int $historyid
     * @param float $credit
     *
     * @covers \local_shopping_cart\shopping_cart
     * @dataProvider shoppingcartprovider
     * @runInSeparateProcess
     * @return void
     */
    public function test_shoppingcart_cache_add_item_and_start_new_checkout(
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

        global $DB;

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
        $originalprice = $price;

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

        // Check if discount is applied (mocked or verified result).
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
        $data = $cartstore->get_data();
        $data = checkout::prepare_checkout($data);
        $oldidentifier = $data['identifier'];
        $payable = service_provider::get_payable('', $data['identifier']);
        $this->assertEquals($payable->get_amount(), $price, 'Price was not correctly calculated.');

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        // We only want one, but we fetch all of them to see errors.
        $reservedrecords = $DB->get_records('local_shopping_cart_reserv', ['userid' => $data['userid']]);
        $this->assertCount(1, $reservedrecords, 'There should be exactly one reservedrecords item for the user.');

        // We now add another item, although we have already one in the cart.
        $addresult = add_item_to_cart::execute('local_shopping_cart', 'testarea', 3, $user->id);
        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);

        $payable = service_provider::get_payable('', $data['identifier']);
        $this->assertEquals($payable->get_amount(), $price, 'Price was not correctly calculated.');

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        // We go again to the checkout page.
        $data = $cartstore->get_data();
        $data = checkout::prepare_checkout($data);
        $newidentifier = $data['identifier'];

        // We should have received a new identifier.
        $this->assertNotEquals($oldidentifier, $newidentifier, 'Identifier was not changed.');

        $payable = service_provider::get_payable('', $newidentifier);
        $this->assertEquals($payable->get_amount(), $price + $addresult['price'], 'Price was not correctly calculated.');

        cache_helper::purge_all();

        // Step 3: Confirm purchase.
        $purchaseresult = service_provider::deliver_order('', $newidentifier, 0, $user->id);

        $data = $cartstore->get_data();

        $historyitem = get_history_item::execute($component, $area, $itemid, $user->id);

        // Test discount.
        $historyitemidentifier = $DB->get_field('local_shopping_cart_history', 'identifier', ['id' => $historyitem['id']]);
        $ledgeritems = shopping_cart_history::return_data_from_ledger_via_identifier($historyitemidentifier);

        $this->assertCount(2, $ledgeritems, 'There should be exactly two ledger items for the history item.');
    }

    /**
     * Function to test puchase and cancelation.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param string $itemname
     * @param int $userid
     * @param int $paymenttype
     * @param string $annotation
     * @param array $discount
     * @param int $historyid
     * @param float $credit
     *
     * @covers \local_shopping_cart\shopping_cart
     * @dataProvider shoppingcartprovider
     * @runInSeparateProcess
     * @return void
     */
    public function test_shoppingcart_cache_delete_item(
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

        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $userid = $user->id;

        // $this->setUser($user);
        $this->setAdminUser();

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
        $originalprice = $price;

        // Step 2: Apply discount.
        // $this->setUser($user);
        $cartstore = cartstore::instance($user->id);
        $cartstore->add_discount_to_item(
            $component,
            $area,
            $itemid,
            $discount['percent'],
            $discount['absolute']
        );

        // Check if discount is applied (mocked or verified result).
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
        $data = $cartstore->get_data();
        $data = checkout::prepare_checkout($data);
        $payable = service_provider::get_payable('', $data['identifier']);
        $this->assertEquals($payable->get_amount(), $price, 'Price was not correctly calculated.');

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        // We only want one, but we fetch all of them to see errors.
        $reservedrecords = $DB->get_records('local_shopping_cart_reserv', ['userid' => $data['userid']]);
        $this->assertCount(1, $reservedrecords, 'There should be exactly one reservedrecords item for the user.');

        // We now add another item, although we have already one in the cart.
        $addresult = delete_item_from_cart::execute($component, $area, $itemid, $userid);
        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);

        $payable = service_provider::get_payable('', $data['identifier']);
        $this->assertEquals($payable->get_amount(), $price, 'Price was not correctly calculated.');

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        cache_helper::purge_all();

        // Step 3: Confirm purchase.
        $purchaseresult = service_provider::deliver_order('', $data['identifier'], 0, $user->id);

        $data = $cartstore->get_data();

        $historyitem = get_history_item::execute($component, $area, $itemid, $user->id);

        // Test discount.
        $historyitemidentifier = $DB->get_field('local_shopping_cart_history', 'identifier', ['id' => $historyitem['id']]);
        $ledgeritems = shopping_cart_history::return_data_from_ledger_via_identifier($historyitemidentifier);

        $this->assertCount(1, $ledgeritems, 'There should be exactly no ledger items for the history item.');
    }

    /**
     * Function to test puchase and cancelation.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param string $itemname
     * @param int $userid
     * @param int $paymenttype
     * @param string $annotation
     * @param array $discount
     * @param int $historyid
     * @param float $credit
     *
     * @covers \local_shopping_cart\shopping_cart
     * @dataProvider shoppingcartprovider
     * @runInSeparateProcess
     * @return void
     */
    public function test_shoppingcart_cache(
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
        global $DB;

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
        $originalprice = $price;

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

        // Check if discount is applied (mocked or verified result).
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
        $data = $cartstore->get_data();
        $data = checkout::prepare_checkout($data);
        service_provider::get_payable('', $data['identifier']);

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        // We only want one, but we fetch all of them to see errors.
        $reservedrecords = $DB->get_records('local_shopping_cart_reserv', ['userid' => $data['userid']]);
        $this->assertCount(1, $reservedrecords, 'There should be exactly one reservedrecords item for the user.');

        // We now add another item, although we have already one in the cart.
        $addresult = add_item_to_cart::execute('local_shopping_cart', 'testarea', 3, $user->id);
        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);

        $initialidentifier = $data['identifier'];

        $data = $cartstore->get_data();
        $data = checkout::prepare_checkout($data);
        service_provider::get_payable('', $data['identifier']);

        cache_helper::purge_all();

        // Step 3: Confirm purchase.
        $purchaseresult = service_provider::deliver_order('', $initialidentifier, 0, $user->id);

        $data = $cartstore->get_data();

        $historyitem = get_history_item::execute($component, $area, $itemid, $user->id);

        // Test discount.
        $historyitemidentifier = $DB->get_field('local_shopping_cart_history', 'identifier', ['id' => $historyitem['id']]);
        $ledgeritems = shopping_cart_history::return_data_from_ledger_via_identifier($historyitemidentifier);

        $this->assertCount(1, $ledgeritems, 'There should be exactly one ledger item for the history item.');

        $ledgeritem = reset($ledgeritems);
        $discount = (float) $ledgeritem->discount;
        $this->assertEquals($discount, $originalprice - $price, 'Discount was not applied correctly.');

        // Step 4: Cancel purchase.
        $cancelresult = cancel_purchase::execute($component, $area, $itemid, $user->id, $historyitem['id'], 0);

        // When we cancel, the ledger record will get a different identifier than the history item.
        $ledgerrecordcancelled = $DB->get_record(
            'local_shopping_cart_ledger',
            [
                'schistoryid' => $historyitem['id'],
                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
            ]
        );
        $this->assertNotEquals(
            $ledgerrecordcancelled->identifier,
            $historyitemidentifier,
            'Identifier was not changed but should be changed for cancelled ledger record.'
        );

        $this->assertArrayHasKey('success', $cancelresult);
        $this->assertEquals($cancelresult['success'], 1, 'Purchase was not successfully canceled.');
    }

    /**
     * Function to test puchase and cancelation.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param string $itemname
     * @param int $userid
     * @param int $paymenttype
     * @param string $annotation
     * @param array $discount
     * @param int $historyid
     * @param float $credit
     *
     * @covers \local_shopping_cart\shopping_cart
     * @dataProvider shoppingcartprovider
     * @runInSeparateProcess
     * @return void
     */
    public function test_shoppingcart_cache_add_item(
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

        global $DB;

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
        $originalprice = $price;

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

        // Check if discount is applied (mocked or verified result).
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
        $data = $cartstore->get_data();
        $data = checkout::prepare_checkout($data);
        $payable = service_provider::get_payable('', $data['identifier']);
        $this->assertEquals($payable->get_amount(), $price, 'Price was not correctly calculated.');

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        // We only want one, but we fetch all of them to see errors.
        $reservedrecords = $DB->get_records('local_shopping_cart_reserv', ['userid' => $data['userid']]);
        $this->assertCount(1, $reservedrecords, 'There should be exactly one reservedrecords item for the user.');

        // We now add another item, although we have already one in the cart.
        $addresult = add_item_to_cart::execute('local_shopping_cart', 'testarea', 3, $user->id);
        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);

        $payable = service_provider::get_payable('', $data['identifier']);
        $this->assertEquals($payable->get_amount(), $price, 'Price was not correctly calculated.');

        $historyrecords = $DB->get_records('local_shopping_cart_history', ['identifier' => $data['identifier']]);
        $this->assertCount(1, $historyrecords, 'There should be exactly one history item for the identifier.');

        $addresult = delete_item_from_cart::execute($component, $area, $itemid, $userid);

        cache_helper::purge_all();

        // Step 3: Confirm purchase.
        $purchaseresult = service_provider::deliver_order('', $data['identifier'], 0, $user->id);

        $data = $cartstore->get_data();

        $historyitem = get_history_item::execute($component, $area, $itemid, $user->id);

        // Test discount.
        $historyitemidentifier = $DB->get_field('local_shopping_cart_history', 'identifier', ['id' => $historyitem['id']]);
        $ledgeritems = shopping_cart_history::return_data_from_ledger_via_identifier($historyitemidentifier);

        $this->assertCount(1, $ledgeritems, 'There should be exactly two ledger items for the history item.');
    }
}
