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
use local_shopping_cart\local\checkout_process\items\termsandconditions;

/**
 * Unit tests for the termsandconditions class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Test for termsandconditions
 */
final class termsandconditions_test extends advanced_testcase {
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
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('acceptadditionalconditions', 0, 'local_shopping_cart');

        // Assert that the method returns true when one condition is true.
        $this->assertTrue(termsandconditions::is_active(), 'Expected is_active to return true.');

        // Reset configurations and test when both are false.
        unset_config('accepttermsandconditions', 'local_shopping_cart');
        unset_config('acceptadditionalconditions', 'local_shopping_cart');

        $this->assertFalse(termsandconditions::is_active(), 'Expected is_active to return false when no conditions are true.');
    }

    /**
     * Test the get_icon_progress_bar method.
     */
    public function test_get_icon_progress_bar(): void {
        $expected = 'fa-solid fa-file-signature';
        $this->assertEquals($expected, termsandconditions::get_icon_progress_bar(), 'Icon does not match expected value.');
    }

    /**
     * Test the is_mandatory method.
     */
    public function test_is_mandatory(): void {
        $this->assertTrue(termsandconditions::is_mandatory(), 'Expected is_mandatory to return true.');
    }

    /**
     * Test the render_body method.
     */
    public function test_render_body(): void {
        $cachedata = [
            'data' => [
                'terms' => 'Accept terms',
                'additional' => 'Additional conditions',
            ],
        ];

        // Call the method to test.
        $result = termsandconditions::render_body($cachedata);

        // Assertions.
        $this->assertIsArray($result, 'Expected render_body to return an array.');
        $this->assertIsString($result['template'], 'Expected the template to be mocked.');
    }

    /**
     * Test the check_status method.
     */
    public function test_check_status(): void {
        $validationdata = json_encode([
            (object)['name' => 'accept_terms', 'value' => true],
            (object)['name' => 'accept_additional', 'value' => true],
        ]);

        $managercachestep = [];
        $result = termsandconditions::check_status($managercachestep, $validationdata);

        // Assertions.
        $this->assertIsArray($result, 'Expected check_status to return an array.');
        $this->assertArrayHasKey('data', $result, 'Expected the result to include "data".');
        $this->assertArrayHasKey('mandatory', $result, 'Expected the result to include "mandatory".');
        $this->assertArrayHasKey('valid', $result, 'Expected the result to include "valid".');
        $this->assertTrue($result['valid'], 'Expected valid to return true when all conditions are met.');
    }

    /**
     * Test the is_valid method.
     */
    public function test_is_valid(): void {
        // Case: All conditions met.
        $validationdata = [
            (object)['name' => 'accept_terms', 'value' => true],
            (object)['name' => 'accept_additional', 'value' => true],
        ];
        $this->assertTrue(termsandconditions::is_valid($validationdata), 'Expected is_valid to return true when all conditions are met.');

        // Case: One condition not met.
        $validationdata = [
            (object)['name' => 'accept_terms', 'value' => true],
            (object)['name' => 'accept_additional', 'value' => false],
        ];
        $this->assertFalse(termsandconditions::is_valid($validationdata), 'Expected is_valid to return false when a condition is not met.');
    }

    /**
     * Test the set_data_from_cache method.
     */
    public function test_set_data_from_cache(): void {
        $data = ['termsandconditions' => 'Accept terms'];
        $cachedata = ['additionalconditions' => 'Additional terms'];

        termsandconditions::set_data_from_cache($data, $cachedata);

        // Assertions.
        $this->assertArrayHasKey('termsandconditions', $data, 'Expected termsandconditions to remain in the array.');
        $this->assertArrayHasKey('additionalconditions', $data, 'Expected additionalconditions to be added to the array.');
        $this->assertEquals('Additional terms', $data['additionalconditions'], 'Expected additionalconditions to match cachedata value.');
    }
}
