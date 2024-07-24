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
 * phpUnit cartitem_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\cartstore;
use PHPUnit\Framework\TestCase;
use phpunit_util;

/**
 * Test for cartitem
 * @covers \cartitem
 */
final class cartstore_test extends TestCase {

    /**
     * Test taxcategory not set
     * @covers \cartstore
     * @param string $property
     *
     * @dataProvider cartstore_get_data_provider
     */
    public function test_cartstore_get_data(string $property): void {

        global $USER;

        $cartstore = cartstore::instance((int)$USER->id);
        $data = $cartstore->get_data();

        $this->assertArrayHasKey($property, $data);
    }

    /**
     * Test test_cartstore_add_items
     * @covers \cartstore
     */
    public function test_cartstore_add_items(): void {

        $user1 = $this->get_data_generator()->create_user();

        $cartstore = cartstore::instance((int)$user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            1,
            $user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            2,
            $user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            3,
            $user1->id);

        $data = $cartstore->get_data();

        // Check total price.
        $this->assertEquals($data['price'], 44.1);

        $cartstore->set_credit(14.1, 'EUR');

        $data = $cartstore->get_data();

        // Check total price.
        $this->assertEquals($data['price'], 30);

        set_config('enabletax', "1", 'local_shopping_cart');
        set_config('defaulttaxcategory', 'A', 'local_shopping_cart');
        set_config('taxcategories', 'A:15 B:10 C:0', 'local_shopping_cart');

        $cartstore->reset_instance($user1->id);

        $data = $cartstore->get_data();

        // Check total price.
        $this->assertEquals($data['price'], 30);
        $this->assertEquals($data["price_net"], 40.95);
        $this->assertEquals($data["initialtotal_net"], 40.95);

        set_config('itempriceisnet', "1", 'local_shopping_cart');

        shopping_cart::delete_all_items_from_cart($user1->id);

        $cartstore = cartstore::instance($user1->id);

        $this->assertEquals(count($cartstore->get_items()), 0);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            1,
            $user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            2,
            $user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            3,
            $user1->id);

        $data = $cartstore->get_data();

        // Check total price.
        $this->assertEquals($data['price'], 33.53);
        $this->assertEquals($data["price_net"], 44.1);
        $this->assertEquals($data["initialtotal_net"], 44.1);
        $this->assertEquals($data["initialtotal"], 47.63);
    }

    /**
     * Data provider for test_cartstore_get_data
     *
     * @return array
     */
    public static function cartstore_get_data_provider(): array {
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

    /**
     * Get data generator
     * @return testing_data_generator
     */
    public static function get_data_generator() {
        return phpunit_util::get_data_generator();
    }
}
