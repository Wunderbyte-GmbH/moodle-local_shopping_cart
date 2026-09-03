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
use local_shopping_cart\local\cartstore;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// The payment constants used below live in lib.php, which is not loaded automatically.
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Tests that the checkout page can leave the purchase history to the browser.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\shopping_cart_history::user_has_history
 * @covers \local_shopping_cart\local\cartstore::get_expanded_checkout_data
 * @covers \local_shopping_cart\local\cartstore::has_history
 */
final class checkout_lazy_history_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['name' => 'Test account']);
        set_config('accountid', $account->get('id'), 'local_shopping_cart');
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
    }

    /**
     * The cheap check must answer the same question the full list answers.
     */
    public function test_user_has_history(): void {

        $withhistory = $this->getDataGenerator()->create_user();
        $withouthistory = $this->getDataGenerator()->create_user();

        $this->create_history_entry($withhistory->id);

        $this->assertTrue(shopping_cart_history::user_has_history($withhistory->id));
        $this->assertFalse(shopping_cart_history::user_has_history($withouthistory->id));

        // Same answer as the list that used to be built just to find this out.
        $this->setUser($withhistory);
        $list = (new output\shoppingcart_history_list($withhistory->id))->return_list();
        $this->assertTrue(!empty($list['has_historyitems']));

        $this->setUser($withouthistory);
        $list = (new output\shoppingcart_history_list($withouthistory->id))->return_list();
        $this->assertTrue(empty($list['has_historyitems']));
    }

    /**
     * A pending purchase is not a history entry yet.
     */
    public function test_user_has_history_ignores_unfinished_purchases(): void {

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_PENDING]);

        $this->assertFalse(shopping_cart_history::user_has_history($user->id));
    }

    /**
     * Without the history, the checkout data must be complete apart from the history itself.
     */
    public function test_checkout_data_without_history(): void {

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id);
        $this->setUser($user);

        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 1, $user->id);

        $cartstore = cartstore::instance($user->id);

        $withhistory = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($withhistory);

        $withouthistory = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($withouthistory, false);

        // The history is the only thing that is missing.
        $this->assertNotEmpty($withhistory['historyitems']);
        $this->assertArrayNotHasKey('historyitems', $withouthistory);

        $this->assertSame($withhistory['userid'], $withouthistory['userid']);
        $this->assertSame($withhistory['mail'], $withouthistory['mail']);
        $this->assertEquals(count($withhistory['items']), count($withouthistory['items']));
        $this->assertSame($withhistory['price'], $withouthistory['price']);
    }

    /**
     * The answer is looked up once and then remembered.
     */
    public function test_has_history_is_only_looked_up_once(): void {

        global $DB;

        $buyer = $this->getDataGenerator()->create_user();
        $this->create_history_entry($buyer->id);
        $newcomer = $this->getDataGenerator()->create_user();

        foreach ([$buyer->id => true, $newcomer->id => false] as $userid => $expected) {
            $cartstore = cartstore::instance($userid);

            $before = $DB->perf_get_reads();
            $this->assertSame($expected, $cartstore->has_history());
            $firstcall = $DB->perf_get_reads() - $before;

            $before = $DB->perf_get_reads();
            $this->assertSame($expected, $cartstore->has_history());
            $this->assertSame(0, $DB->perf_get_reads() - $before, 'The answer was looked up again.');

            $this->assertGreaterThan(0, $firstcall, 'The first call has to look the answer up.');
        }
    }

    /**
     * A purchase notes right away that this user now has a history.
     */
    public function test_purchase_sets_has_history(): void {

        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $cartstore = cartstore::instance($user->id);

        // Nothing bought yet, and that answer is now cached.
        $this->assertFalse($cartstore->has_history());

        $this->setAdminUser();
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 1, $user->id);
        $result = shopping_cart::confirm_payment($user->id, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH);
        $this->assertEquals(1, $result['status'], 'Purchase was not confirmed.');

        /* The purchase itself has to correct the cached answer, otherwise the checkout page would
        keep hiding the history tab from a user who just bought something. */
        $before = $DB->perf_get_reads();
        $this->assertTrue(cartstore::instance($user->id)->has_history());
        $this->assertSame(0, $DB->perf_get_reads() - $before, 'The purchase did not update the cached answer.');

        // And it agrees with the database.
        $this->assertTrue(shopping_cart_history::user_has_history($user->id));
    }

    /**
     * Deleting the purchases of a user must not leave a wrong answer behind.
     */
    public function test_purge_has_history(): void {

        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id);

        $cartstore = cartstore::instance($user->id);
        $this->assertTrue($cartstore->has_history());

        $DB->delete_records('local_shopping_cart_history', ['userid' => $user->id]);
        cartstore::purge_has_history($user->id);

        $this->assertFalse($cartstore->has_history());
    }

    /**
     * Write a single history entry for a user.
     *
     * @param int $userid
     * @param array $overrides values that differ from the default purchase
     * @return int the id of the new history record
     */
    private function create_history_entry(int $userid, array $overrides = []): int {

        global $DB;

        $now = time();

        $record = (object) array_merge([
            'userid' => $userid,
            'itemid' => 1,
            'itemname' => 'Test item',
            'price' => 10.00,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'main',
            'identifier' => 1000001,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'canceluntil' => $now + WEEKSECS,
            'serviceperiodstart' => $now,
            'serviceperiodend' => $now + YEARSECS,
        ], $overrides);

        return $DB->insert_record('local_shopping_cart_history', $record);
    }
}
