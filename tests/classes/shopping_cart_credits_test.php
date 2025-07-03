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
 * Unit tests for the shopping_cart_credits class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\classes;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\items\shopping_cart_credits;

/**
 * Test for shopping_cart_credits
 *
 * @covers \local_shopping_cart\local\checkout_process\items\shopping_cart_credits
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
     * Test the get_icon_progress_bar method.
     */
    public function test_get_icon_progress_bar(): void {
        $expected = 'fa-solid fa-coins';
        $this->assertEquals($expected, shopping_cart_credits::get_icon_progress_bar(), 'Icon does not match expected value.');
    }

    /**
     * Test the is_head method.
     */
    public function test_is_head(): void {
        $this->assertTrue(shopping_cart_credits::is_head(), 'Expected is_head to return true.');
    }

    /**
     * Test the is_mandatory method.
     */
    public function test_is_mandatory(): void {
        $this->assertFalse(shopping_cart_credits::is_mandatory(), 'Expected is_mandatory to return false.');
    }

    /**
     * Test the check_status method.
     */
    public function test_check_status(): void {
        $managercachestep = [];
        $changedinput = ['credits' => 100];

        $result = shopping_cart_credits::check_status($managercachestep, $changedinput);

        // Assertions.
        $this->assertIsArray($result, 'Expected check_status to return an array.');
        $this->assertEquals('', $result['data'], 'Expected data to be an empty string.');
        $this->assertFalse($result['mandatory'], 'Expected mandatory to return false.');
        $this->assertTrue($result['valid'], 'Expected valid to return true.');
    }

    /**
     * Test the render_body method.
     */
    public function test_render_body(): void {
        // Mock cache data.
        $cachedata = [
            'credits' => 100,
            'currency' => 'USD',
        ];

        // Call the method to test.
        $result = shopping_cart_credits::render_body($cachedata);

        // Assertions.
        $this->assertIsArray($result, 'Expected render_body to return an array.');
        $this->assertIsString($result['template'], 'Expected the template to be mocked.');
    }
}
