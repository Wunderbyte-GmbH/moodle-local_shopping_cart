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

use advanced_testcase;
use local_shopping_cart\local\cartstore;
use tool_mocktesttime\time_mock;
use phpunit_util;

/**
 * Test for cartitem
 * @covers \local_shopping_cart\local\entities\cartitem
 */
final class cartstore_mulitple_items_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        time_mock::init();
        time_mock::set_mock_time(strtotime('now'));
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        // Mandatory clean-up.
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
        time_mock::reset_mock_time();
    }

    /**
     * Test change number of items
     * @covers \local_shopping_cart\local\cartstore
     */
    public function test_change_number_of_items(): void {

        $user1 = $this->get_data_generator()->create_user();

        $cartstore = cartstore::instance((int)$user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            9,
            $user1->id
        );

        $data = $cartstore->get_data();

        // Check total price.
        $this->assertEquals($data['price'], 10);

        $cartstore->multiply_item(
            'local_shopping_cart',
            'testitem',
            9,
            3
        );

        $data = $cartstore->get_data();

        $this->assertEquals($data['price'], 15);

        $cartstore->multiply_item(
            'local_shopping_cart',
            'testitem',
            9,
            1
        );

        $data = $cartstore->get_data();

        $this->assertEquals($data['price'], 5);

        $cartstore->increase_number_of_item('local_shopping_cart', 'testitem', 9);

        $data = $cartstore->get_data();

        $this->assertEquals($data['price'], 10);

        $cartstore->decrease_number_of_item('local_shopping_cart', 'testitem', 9);

        $data = $cartstore->get_data();

        $this->assertEquals($data['price'], 5);
    }

    /**
     * Test change number of items
     * @covers \local_shopping_cart\local\cartstore
     */
    public function test_change_number_of_items_with_tax(): void {

        set_config('enabletax', "1", 'local_shopping_cart');
        set_config('defaulttaxcategory', 'A', 'local_shopping_cart');
        set_config('taxcategories', 'A:15 B:10 C:0', 'local_shopping_cart');

        $user1 = $this->get_data_generator()->create_user();

        $cartstore = cartstore::instance((int)$user1->id);

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            9,
            $user1->id
        );

        $data = $cartstore->get_data();

        // Check total price.
        $this->assertEquals($data['price'], 10);
        $this->assertEquals($data['price_net'], 8.7);

        $cartstore->multiply_item(
            'local_shopping_cart',
            'testitem',
            9,
            3
        );

        $data = $cartstore->get_data();

        $this->assertEquals($data['price'], 15);
        $this->assertEquals($data['price_net'], 13.04);

        $cartstore->multiply_item(
            'local_shopping_cart',
            'testitem',
            9,
            1
        );

        $data = $cartstore->get_data();

        $this->assertEquals($data['price'], 5);
        $this->assertEquals($data['price_net'], 4.35);
    }

    /**
     * Get data generator
     * @return \testing_data_generator
     */
    public static function get_data_generator() {
        return phpunit_util::get_data_generator();
    }
}
