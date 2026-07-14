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
 * Unit tests for the addresses class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\checkout_process\items;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\items\addresses;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;

/**
 * Test for addresses
 *
 * @covers \local_shopping_cart\local\checkout_process\items\addresses
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
        $this->assertTrue(addresses::is_active([], []), 'Expected is_active to return true.');
        unset_config('addresses_required', 'local_shopping_cart');
        $this->assertFalse(addresses::is_active([], []), 'Expected is_active to return false.');
    }

    /**
     * Test the get_icon_progress_bar method.
     */
    public function test_get_icon_progress_bar(): void {
        $expected = 'fa-solid fa-address-book';
        $this->assertEquals($expected, addresses::get_icon_progress_bar(), 'Icon does not match expected value.');
    }

    /**
     * Test the evaluate_step method: with a required billing address but none
     * selected, the step is invalid.
     */
    public function test_evaluate_step(): void {
        set_config('addresses_required', 'billing', 'local_shopping_cart');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $addresses = new addresses($user->id);
        $result = $addresses->evaluate_step([]);

        $this->assertIsArray($result, 'Expected evaluate_step to return an array.');
        $this->assertArrayHasKey('data', $result, 'Expected result to include data.');
        $this->assertArrayHasKey('mandatory', $result, 'Expected result to include mandatory.');
        $this->assertFalse($result['valid'], 'Expected invalid when no required address is selected.');
    }

    /**
     * Test parse_changed_input maps the legacy name/value JSON to step data.
     */
    public function test_parse_changed_input(): void {
        set_config('addresses_required', 'billing', 'local_shopping_cart');
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $validationdata = json_encode([
            (object)['name' => 'selectedaddress_billing', 'value' => 42],
        ]);
        $addresses = new addresses($user->id);
        $data = $addresses->parse_changed_input($validationdata);

        $this->assertEquals(42, $data['selectedaddress_billing'], 'Expected the billing selection to be parsed.');
    }

    /**
     * Test the get_user_data method.
     */
    public function test_get_user_data(): void {
        global $USER;
        $USER = (object)[
            'id' => 5,
            'username' => 'johndoe',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'test@example.com',
        ];

        $result = addresses::get_user_data();

        // Assertions.
        $this->assertEquals(5, $result['userid'], 'Expected userid to match.');
        $this->assertEquals('johndoe', $result['username'], 'Expected username to match.');
        $this->assertEquals('John', $result['firstname'], 'Expected firstname to match.');
        $this->assertEquals('Doe', $result['lastname'], 'Expected lastname to match.');
        $this->assertEquals('test@example.com', $result['email'], 'Expected email to match.');
    }

    /**
     * Test the get_required_address_keys method.
     *
     * The required addresses are driven by the 'addresses_required'
     * multicheckbox setting (billing / shipping). The result follows that
     * setting: billing is treated as the leading address (billing-centric
     * checkout UX), shipping-only returns shipping, and nothing returns none.
     */
    public function test_get_required_address_keys(): void {
        // Billing required -> billing.
        set_config('addresses_required', 'billing', 'local_shopping_cart');
        $this->assertEquals(
            ['billing'],
            addresses::get_required_address_keys(),
            'Expected billing when billing is required.'
        );

        // Billing and shipping required -> billing-centric -> billing.
        set_config('addresses_required', 'billing,shipping', 'local_shopping_cart');
        $this->assertEquals(
            ['billing'],
            addresses::get_required_address_keys(),
            'Expected billing-centric reduction when both are required.'
        );

        // Only shipping required -> shipping.
        set_config('addresses_required', 'shipping', 'local_shopping_cart');
        $this->assertEquals(
            ['shipping'],
            addresses::get_required_address_keys(),
            'Expected shipping when only shipping is required.'
        );

        // Nothing required -> empty.
        set_config('addresses_required', '', 'local_shopping_cart');
        $this->assertEquals(
            [],
            addresses::get_required_address_keys(),
            'Expected no required address keys when the setting is empty.'
        );
    }

    /**
     * A non-existent (e.g. deleted) address id must return false instead of
     * throwing, so checkout.php can handle a stale address id in the cache.
     */
    public function test_get_specific_user_address_missing_returns_false(): void {
        $this->assertFalse(
            address_operations::get_specific_user_address(999999),
            'Expected false for a non-existent address id.'
        );
    }
}
