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
 * Tests for the checkout page data builder.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\checkout_process;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\checkout_page_data;

/**
 * Tests for the checkout page data builder.
 *
 * @covers \local_shopping_cart\local\checkout_process\checkout_page_data
 */
final class checkout_page_data_test extends advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Without a configured payment account, get_history_list_for_user()
        // renders an error page and exits, killing the whole PHPUnit run.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['name' => 'Account1']);
        set_config('accountid', $account->get('id'), 'local_shopping_cart');
    }

    /**
     * The builder assembles the checkout template data for a cart without
     * touching output — the same shape checkout.php feeds its template.
     */
    public function test_build_cart_checkout(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $data = checkout_page_data::build_cart_checkout((int) $user->id);

        // Checkout manager overview merged in and area set for the template.
        $this->assertSame('main', $data['area']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('couponenabled', $data);
        // The checkout manager contributes its step structure.
        $this->assertArrayHasKey('checkout_manager_head', $data);
        $this->assertArrayHasKey('checkout_manager_body', $data);
    }
}
