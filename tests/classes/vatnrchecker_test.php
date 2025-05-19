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
 * @covers \local\checkout_process\items\vatnrchecker
 */
final class vatnrchecker_test extends advanced_testcase {
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
        set_config('owncountrycode', 'DE', 'local_shopping_cart');

        // Assert that the method returns true when conditions are met.
        $this->assertTrue(vatnrchecker::is_active(), 'Expected is_active to return true when configuration is valid.');

        // Remove configurations and test again.
        unset_config('owncountrycode', 'local_shopping_cart');
        $this->assertFalse(vatnrchecker::is_active(), 'Expected is_active to return false when country code is missing.');
    }

    /**
     * Test the get_icon_progress_bar method.
     */
    public function test_get_icon_progress_bar(): void {
        $expected = 'fa-solid fa-file-invoice';
        $this->assertEquals($expected, vatnrchecker::get_icon_progress_bar(), 'Icon does not match expected value.');
    }

    /**
     * Test the set_data_from_cache method.
     */
    public function test_set_data_from_cache(): void {
        $vatnrcheckerdata = [
            'countries' => [
                ['code' => 'DE', 'name' => 'Germany'],
                ['code' => 'FR', 'name' => 'France'],
            ],
        ];
        $cachedata = json_encode(['vatCodeCountry' => 'DE,123456789']);

        vatnrchecker::set_data_from_cache($vatnrcheckerdata, $cachedata);

        // Assertions.
        $this->assertArrayHasKey('vatnumber', $vatnrcheckerdata, 'Expected vatnumber to be set in the data.');
        $this->assertEquals('123456789', $vatnrcheckerdata['vatnumber'], 'Expected vatnumber to match cached data.');
        $this->assertTrue($vatnrcheckerdata['countries'][0]['selected'], 'Expected Germany to be selected.');
        $this->assertArrayNotHasKey('selected', $vatnrcheckerdata['countries'][1], 'Expected France not to be selected.');
    }

    /**
     * Test the get_country_code_name method.
     */
    public function test_get_country_code_name(): void {
        $result = vatnrchecker::get_country_code_name();

        // Assertions.
        $this->assertIsArray($result, 'Expected get_country_code_name to return an array.');
        $this->assertCount(31, $result, 'Expected exactly two countries in the result.');
        $this->assertEquals(['code' => 'novatnr', 'name' => 'No VAT number'], $result[0], 'Expected first country to be Germany.');
    }

    /**
     * Test the check_status method.
     */
    public function test_check_status(): void {
        $managercachestep = [];
        $changedinput = json_encode(['vatCodeCountry' => 'DE,123456789']);
        $result = vatnrchecker::check_status($managercachestep, $changedinput);

        $this->assertIsArray($result, 'Expected check_status to return an array.');
        $this->assertFalse($result['valid'], 'Expected the VAT number to be valid.');
        $this->assertArrayHasKey('mandatory', $result, 'Expected the result to include "mandatory".');
        $this->assertArrayHasKey('data', $result, 'Expected the result to include "data".');
    }

    /**
     * Test the get_input_data method.
     */
    public function test_get_input_data(): void {
        $changedinput = json_encode(['vatCodeCountry' => 'DE,123456789']);
        $result = vatnrchecker::get_input_data($changedinput);

        $this->assertIsArray($result, 'Expected get_input_data to return an array.');
        $this->assertEquals('DE', $result['country'], 'Expected country to be DE.');
        $this->assertEquals('123456789', $result['vatnumber'], 'Expected VAT number to match.');
    }

    /**
     * Test the get_validation_feedback method.
     */
    public function test_get_validation_feedback(): void {
        $result = vatnrchecker::get_validation_feedback();
        $this->assertIsString($result, 'Expected validation feedback to be a string.');
    }

    /**
     * Test the get_error_feedback method.
     */
    public function test_get_error_feedback(): void {
        $result = vatnrchecker::get_error_feedback();
        $this->assertIsString($result, 'Expected error feedback to be a string.');
    }
}
