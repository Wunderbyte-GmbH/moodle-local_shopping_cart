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
use context_system;
use local_wunderbyte_table\local\helper\actforuser;

/**
 * Tests that the shortcodes hand the cashier check to the helper that reads the user id from the url.
 *
 * Only cashiers may see the history or the info card of another user. The shortcodes name the
 * cashier capability to the helper, so the helper itself rejects the id of another user for
 * everybody else. A user id that is accepted without this check is counted by the helper;
 * that must not happen in these shortcodes.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\shortcodes::shoppingcarthistory
 * @covers \local_shopping_cart\shortcodes::userinfocard
 */
final class shortcodes_actforuser_test extends advanced_testcase {
    /**
     * Set up: no payment account, so the shortcodes do not need a complete shop.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        unset_config('accountid', 'local_shopping_cart');
        unset($_GET['userid']);
        actforuser::$unverifiedforeignids = 0;
    }

    /**
     * Mandatory clean-up after each test.
     */
    protected function tearDown(): void {
        unset($_GET['userid']);
        parent::tearDown();
    }

    /**
     * The two shortcodes, for a cashier and for a regular user.
     *
     * @return array
     */
    public static function shortcode_provider(): array {
        return [
            'history, cashier' => ['shortcode' => 'shoppingcarthistory', 'cashier' => true],
            'history, regular user' => ['shortcode' => 'shoppingcarthistory', 'cashier' => false],
            'info card, cashier' => ['shortcode' => 'userinfocard', 'cashier' => true],
            'info card, regular user' => ['shortcode' => 'userinfocard', 'cashier' => false],
        ];
    }

    /**
     * No shortcode takes the id of another user from the url without the capability check of the helper.
     *
     * @dataProvider shortcode_provider
     *
     * @param string $shortcode
     * @param bool $cashier
     * @return void
     */
    public function test_user_id_from_url_is_checked_by_the_helper(string $shortcode, bool $cashier): void {
        global $PAGE;

        $other = $this->getDataGenerator()->create_user();
        $user = $this->getDataGenerator()->create_user();
        if ($cashier) {
            $roleid = $this->getDataGenerator()->create_role();
            assign_capability('local/shopping_cart:cashier', CAP_ALLOW, $roleid, context_system::instance());
            role_assign($roleid, $user->id, context_system::instance());
        }
        $this->setUser($user);
        $PAGE->set_url('/');
        $_GET['userid'] = (string)$other->id;

        shortcodes::$shortcode('', [], '', (object)[], fn($a) => $a);

        $this->assertSame(0, actforuser::$unverifiedforeignids);
    }
}
