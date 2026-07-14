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
 * Unit tests for the termsandconditions class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\checkout_process\items;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\items\termsandconditions;

/**
 * Test for termsandconditions
 *
 * @covers \local_shopping_cart\local\checkout_process\items\termsandconditions
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
        $this->assertTrue(termsandconditions::is_active([], []), 'Expected is_active to return true.');

        // Reset configurations and test when both are false.
        unset_config('accepttermsandconditions', 'local_shopping_cart');
        unset_config('acceptadditionalconditions', 'local_shopping_cart');

        $this->assertFalse(
            termsandconditions::is_active([], []),
            'Expected is_active to return false when no conditions are true.'
        );
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
     * Test that get_active_conditions only returns conditions whose accept-flag
     * is on and whose text is not empty.
     */
    public function test_get_active_conditions(): void {
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('termsandconditions', 'Accept the terms', 'local_shopping_cart');
        set_config('acceptadditionalconditions', 0, 'local_shopping_cart');

        $active = termsandconditions::get_active_conditions();
        $this->assertArrayHasKey('accepttermsandconditions', $active);
        $this->assertArrayNotHasKey('acceptadditionalconditions', $active);

        // Accept-flag on but empty text -> not active.
        set_config('acceptadditionalconditions', 1, 'local_shopping_cart');
        set_config('additionalconditions', '', 'local_shopping_cart');
        $active = termsandconditions::get_active_conditions();
        $this->assertArrayNotHasKey('acceptadditionalconditions', $active);
    }

    /**
     * Test the evaluate_step method: the step is valid only once every active
     * conditions checkbox is ticked.
     */
    public function test_evaluate_step(): void {
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('termsandconditions', 'Accept the terms', 'local_shopping_cart');
        set_config('acceptadditionalconditions', 1, 'local_shopping_cart');
        set_config('additionalconditions', 'Additional conditions', 'local_shopping_cart');

        $item = new termsandconditions(0);

        // All conditions ticked -> valid.
        $result = $item->evaluate_step([
            'accepttermsandconditions' => true,
            'acceptadditionalconditions' => true,
        ]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('mandatory', $result);
        $this->assertTrue($result['valid'], 'Expected valid when all conditions are ticked.');

        // One condition not ticked -> invalid.
        $result = $item->evaluate_step([
            'accepttermsandconditions' => true,
            'acceptadditionalconditions' => false,
        ]);
        $this->assertFalse($result['valid'], 'Expected invalid when a condition is not ticked.');
    }

    /**
     * Test that parse_changed_input maps the legacy name/value JSON to the
     * data shape used by evaluate_step.
     */
    public function test_parse_changed_input(): void {
        $changedinput = json_encode([
            (object)['name' => 'accepttermsandconditions', 'value' => true],
        ]);
        $item = new termsandconditions(0);
        $data = $item->parse_changed_input($changedinput);

        $this->assertArrayHasKey('accepttermsandconditions', $data);
        $this->assertTrue($data['accepttermsandconditions']);
    }
}
