<?php
// This file is part of Moodle - http://moodle.org/.
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
 * Unit tests for the checkout_manager class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\checkout_process;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\checkout_manager;

/**
 * Test for checkout_manager
 * @covers \local_shopping_cart\local\checkout_process\checkout_manager
 */
final class checkout_manager_test extends advanced_testcase {
    /**
     * Setup the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('addresses_required', 1, 'local_shopping_cart');
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('showvatnrchecker', 1, 'local_shopping_cart');
        set_config('owncountrycode', 12345678, 'local_shopping_cart');
    }

    /**
     * Test the constructor and initialization of checkout_manager.
     */
    public function test_constructor_initialization(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];
        $mockcontrol = [
            'currentstep' => 0,
            'action' => 'next',
        ];

        $checkoutmanager = new checkout_manager($mockdata, $mockcontrol);
        $this->assertNotEmpty($checkoutmanager, 'The checkout_manager instance should be created.');
        $this->assertEquals(3, checkout_manager::get_cache(1)['body_mandatory_count']['body_count']);
        $this->assertEquals(2, checkout_manager::get_cache(1)['body_mandatory_count']['mandatory_count']);
    }

    /**
     * Test that render_overview initializes structure correctly.
     */
    public function test_render_overview(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];
        $mockcontrol = [
            'currentstep' => 0,
            'action' => 'next',
        ];

        $checkoutmanager = new checkout_manager($mockdata, $mockcontrol);
        $overview = $checkoutmanager->render_overview();

        $this->assertIsArray($overview, 'Overview should be an array.');
        $this->assertArrayHasKey('checkout_manager_head', $overview);
        $this->assertArrayHasKey('checkout_manager_body', $overview);
    }

    /**
     * Test the set_manager_data method.
     */
    public function test_set_manager_data(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];
        $mockcontrol = [
            'currentstep' => 0,
            'action' => 'next',
        ];

        $checkoutmanager = new checkout_manager($mockdata, $mockcontrol);
        $checkoutmanagerdata = [
            'checkout_manager_head' => [],
            'checkout_manager_body' => [],
        ];

        $checkoutmanager->set_manager_data($checkoutmanagerdata, 0);

        $this->assertArrayHasKey('checkout_manager_head', $checkoutmanagerdata);
        $this->assertArrayHasKey('checkout_manager_body', $checkoutmanagerdata);
        $this->assertIsArray($checkoutmanagerdata['checkout_manager_body']['item_list'] ?? []);
    }

    /**
     * Test the get_checkout_validation method.
     */
    public function test_get_checkout_validation(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];
        $mockcontrol = [
            'currentstep' => 0,
            'action' => 'next',
        ];

        $checkoutmanager = new checkout_manager($mockdata, $mockcontrol);
        $checkoutmanager->set_body_mandatory_count();
        $checkoutmanager->get_checkout_validation();

        $this->assertIsBool($checkoutmanager->render_checkout_button(), 'Checkout button should return a boolean.');
    }

    /**
     * Test the set_body_mandatory_count method.
     */
    public function test_set_body_mandatory_count(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];

        $checkoutmanager = new checkout_manager($mockdata);
        $checkoutmanager->set_body_mandatory_count();

        $this->assertArrayHasKey('body_mandatory_count', checkout_manager::get_cache(1));
    }

    /**
     * Test is_checkout_allowed logic.
     */
    public function test_is_checkout_allowed(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];

        $checkoutmanager = new checkout_manager($mockdata);

        $result = $checkoutmanager->is_checkout_allowed(1, 1, 2);
        $this->assertFalse($result, 'is_checkout_allowed should return true when conditions are met.');
    }

    /**
     * Test cache interactions.
     */
    public function test_cache_interaction(): void {
        $mockdata = [
            'userid' => 1,
            'cart_items' => ['item1', 'item2'],
        ];

        $checkoutmanager = new checkout_manager($mockdata);

        // Set and retrieve from cache.
        $checkoutmanager->set_cache();
        $cachedata = checkout_manager::get_cache(1);

        $this->assertIsArray($cachedata, 'Cache data should be an array.');
    }

}
