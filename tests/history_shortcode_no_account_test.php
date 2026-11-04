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

namespace local_shopping_cart;

use advanced_testcase;

/**
 * The purchase history shortcode on a page without a configured payment account.
 *
 * The shortcode is embedded in pages whose output has already started (for example
 * mod_booking's mybookings.php). Without a payment account the history used to render
 * a page of its own, which threw "Cannot call moodle_page::add_body_class after output
 * has been started". It must degrade to a notification (admins) or to nothing (users).
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\shortcodes::shoppingcarthistory
 * @covers \local_shopping_cart\shopping_cart_history::get_history_list_for_user
 */
final class history_shortcode_no_account_test extends advanced_testcase {
    /**
     * Set up: explicitly no payment account.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        unset_config('accountid', 'local_shopping_cart');
    }

    /**
     * A regular user gets an empty string instead of an exception.
     */
    public function test_user_without_account_sees_nothing(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $out = shortcodes::shoppingcarthistory('', ['foruserid' => $user->id], '', (object)[], fn($a) => $a);

        $this->assertSame('', $out);
    }

    /**
     * An admin gets the hint where to configure the account, as a notification inside the page.
     */
    public function test_admin_without_account_sees_notification(): void {
        $this->setAdminUser();

        $out = shortcodes::shoppingcarthistory('', [], '', (object)[], fn($a) => $a);

        $this->assertStringContainsString(get_string('nopaymentaccounts', 'local_shopping_cart'), $out);
        $this->assertStringContainsString('/payment/accounts.php', $out);
    }
}
