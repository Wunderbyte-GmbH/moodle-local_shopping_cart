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

namespace local_shopping_cart\tests;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\items\addresses;

/**
 * Unit tests for the addresses class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test for addresses
 */
final class addresses_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test the is_active method.
     */
    public function test_is_active(): void {
        set_config('addresses_required', 1, 'local_shopping_cart');
        $this->assertTrue(addresses::is_active(), 'Expected is_active to return true.');
        unset_config('addresses_required', 'local_shopping_cart');
        $this->assertFalse(addresses::is_active(), 'Expected is_active to return false.');
    }

    /**
     * Test the get_icon_progress_bar method.
     */
    public function test_get_icon_progress_bar(): void {
        $expected = 'fa-solid fa-address-book';
        $this->assertEquals($expected, addresses::get_icon_progress_bar(), 'Icon does not match expected value.');
    }

    /**
     * Test the check_status method.
     */
    public function test_check_status(): void {
        $validationdata = json_encode([
            (object)['name' => 'home', 'value' => '123 Main Street'],
        ]);

        $managercachestep = ['data' => []];
        $result = addresses::check_status($managercachestep, $validationdata);

        $this->assertIsArray($result, 'Expected check_status to return an array.');
        $this->assertArrayHasKey('data', $result, 'Expected result to include data.');
        $this->assertFalse($result['valid'], 'Expected valid to be true when all conditions are met.');
    }

    /**
     * Test the get_user_data method.
     */
    public function test_get_user_data(): void {
        global $USER;
        $USER = (object)[
            'email' => 'test@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'id' => 42,
        ];

        $result = addresses::get_user_data();

        // Assertions.
        $this->assertEquals('test@example.com', $result['usermail'], 'Expected usermail to match.');
        $this->assertEquals('JohnDoe', $result['username'], 'Expected username to match.');
        $this->assertEquals(42, $result['userid'], 'Expected userid to match.');
    }

    /**
     * Test the get_required_address_keys method.
     */
    public function test_get_required_address_keys(): void {
        // Mock the configuration.
        set_config('addresses_required', 'home,work', 'local_shopping_cart');

        $result = addresses::get_required_address_keys();

        // Assertions.
        $this->assertCount(2, $result, 'Expected two required address keys.');
        $this->assertEquals(['home', 'work'], $result, 'Expected required address keys to match.');
    }

    /**
     * Test the is_valid method.
     */
    public function test_is_valid(): void {
        // Case: All required keys are present.
        $requiredkeys = ['home', 'work'];
        $data = [
            'home' => '123 Main Street',
            'work' => '456 Office Park',
        ];
        $this->assertTrue(addresses::is_valid($requiredkeys, $data), 'Expected is_valid to return true when all keys are present.');

        // Case: Missing a required key.
        $data = [
            'home' => '123 Main Street',
        ];
        $this->assertFalse(addresses::is_valid($requiredkeys, $data), 'Expected is_valid to return false when a key is missing.');
    }

}
