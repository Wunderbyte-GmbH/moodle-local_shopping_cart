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

namespace local_shopping_cart\classes;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\local\checkout_process\items\vatnrchecker;

/**
 * Unit tests for the vatnrchecker class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test for vatnrchecker
 *
 * @covers \local_shopping_cart\local\checkout_process\items\vatnrchecker
 */
final class vatnumbervoluntarily_test extends advanced_testcase {
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
        // Mock configurations.
        set_config('showvatnrchecker', 1, 'local_shopping_cart');
        set_config('onlywithvatnrnumber', 1, 'local_shopping_cart');
        set_config('owncountrycode', 'DE', 'local_shopping_cart');
        // Assert that the method returns true when conditions are met.
        $this->assertTrue(vatnrchecker::is_active([], []), 'Expected is_active to return true when configuration is valid.');

        set_config('showvatnrchecker', 0, 'local_shopping_cart');
        set_config('onlywithvatnrnumber', 0, 'local_shopping_cart');
        $this->assertFalse(vatnrchecker::is_active([], []), 'Expected is_active to return true when configuration is valid.');

        set_config('showvatnrchecker', 1, 'local_shopping_cart');
        $this->assertFalse(vatnrchecker::is_active([], []), 'Expected is_active to return true when configuration is valid.');

        $this->assertFalse(
            vatnrchecker::is_active(
                '[{"name": "vatnumbervoluntarily","value": false}]',
                []
            ),
            'Expected is_active to return true when configuration is valid.'
        );

        $this->assertTrue(
            vatnrchecker::is_active(
                '[[],{"name":"vatnumbervoluntarily","value":true}]',
                []
            ),
            'Expected is_active to return true when configuration is valid.'
        );

        $this->assertTrue(
            vatnrchecker::is_active(
                '[{"name":"vatnumbervoluntarily","value":true}]',
                []
            ),
            'Expected is_active to return true when configuration is valid.'
        );
    }
}
