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

namespace local_shopping_cart\checkout_process\items;

use advanced_testcase;
use phpunit_util;
use local_shopping_cart\local\checkout_process\items\vatnrchecker;
use local_shopping_cart\local\cartstore;

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
final class vatnrchecker_test extends advanced_testcase {
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
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * Test the is_active method.
     */
    public function test_is_active(): void {
        // Mock configurations.
        set_config('showvatnrchecker', 1, 'local_shopping_cart');
        set_config('owncountrycode', 'DE', 'local_shopping_cart');

        // Not active: the checker is shown, but neither "only with vat number" nor the
        // voluntarily flag is set, so show_vat_nr() falls back to false.
        $this->assertFalse(
            vatnrchecker::is_active([], []),
            'Expected is_active to be false when the step is neither forced nor voluntarily confirmed.'
        );

        // Active (TRUE path): "only with vat number" forces the step and a home country is set.
        set_config('onlywithvatnrnumber', 1, 'local_shopping_cart');
        $this->assertTrue(
            vatnrchecker::is_active([], []),
            'Expected is_active to be true when onlywithvatnrnumber is set and a country code is configured.'
        );

        // Not active: even when forced, a missing home country code disables the step.
        unset_config('owncountrycode', 'local_shopping_cart');
        $this->assertFalse(
            vatnrchecker::is_active([], []),
            'Expected is_active to be false when the country code is missing.'
        );
    }

    /**
     * Test the get_icon_progress_bar method.
     */
    public function test_get_icon_progress_bar(): void {
        $expected = 'fa-solid fa-file-invoice';
        $this->assertEquals($expected, vatnrchecker::get_icon_progress_bar(), 'Icon does not match expected value.');
    }

    /**
     * Test that ticking the voluntarily-VAT checkbox makes the step mandatory.
     */
    public function test_is_mandatory_with_voluntarily(): void {
        unset_config('onlywithvatnrnumber', 'local_shopping_cart');
        $user = $this->get_data_generator()->create_user();
        vatnrchecker::$identifier = $user->id;
        $cache = \cache::make('local_shopping_cart', 'cachebookingpreprocess');

        // No voluntarily flag -> not mandatory.
        $cache->set($user->id, ['vatnumbervoluntarily' => false]);
        $this->assertFalse(vatnrchecker::is_mandatory(), 'Expected not mandatory without the voluntarily flag.');

        // Voluntarily flag ticked -> mandatory.
        $cache->set($user->id, ['vatnumbervoluntarily' => true]);
        $this->assertTrue(vatnrchecker::is_mandatory(), 'Expected mandatory once the voluntarily checkbox is ticked.');

        // onlywithvatnrnumber always wins.
        set_config('onlywithvatnrnumber', 1, 'local_shopping_cart');
        $cache->set($user->id, ['vatnumbervoluntarily' => false]);
        $this->assertTrue(vatnrchecker::is_mandatory(), 'Expected mandatory when onlywithvatnrnumber is set.');
    }

    /**
     * Test the evaluate_step method: validates against (mocked) VIES and returns
     * the legacy-shaped cache data.
     */
    public function test_evaluate_step(): void {
        $user1 = $this->get_data_generator()->create_user();
        $this->setUser($user1);

        // Mock a valid VAT response (is_vatnr_valid honours mockvat_* config under test).
        set_config('mockvat_at_atu74259768', '{"valid": true}', 'local_shopping_cart');

        $item = new vatnrchecker($user1->id);

        $result = $item->evaluate_step(['vatcodecountry' => 'AT', 'vatnumber' => 'ATU74259768']);
        $this->assertIsArray($result, 'Expected evaluate_step to return an array.');
        $this->assertArrayHasKey('data', $result, 'Expected the result to include "data".');
        $this->assertArrayHasKey('mandatory', $result, 'Expected the result to include "mandatory".');
        $this->assertTrue($result['valid'], 'Expected valid for a mocked valid VAT number.');

        // Empty input -> invalid.
        $result = $item->evaluate_step(['vatcodecountry' => '', 'vatnumber' => '']);
        $this->assertFalse($result['valid'], 'Expected invalid for empty input.');
    }

    /**
     * Test that parse_changed_input maps the legacy {"vatCodeCountry":"CC,NUM"}
     * shape to the data shape used by evaluate_step.
     */
    public function test_parse_changed_input(): void {
        $changedinput = json_encode(['vatCodeCountry' => 'DE,123456789']);
        $item = new vatnrchecker(0);
        $data = $item->parse_changed_input($changedinput);

        $this->assertEquals('DE', $data['vatcodecountry'], 'Expected country to be DE.');
        $this->assertEquals('123456789', $data['vatnumber'], 'Expected VAT number to match.');
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

    /**
     * Get data generator
     * @return \testing_data_generator
     */
    public static function get_data_generator() {
        return phpunit_util::get_data_generator();
    }
}
