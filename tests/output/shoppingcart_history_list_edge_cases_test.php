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

namespace local_shopping_cart\output;

use advanced_testcase;
use local_shopping_cart\local\cartstore;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Edge cases of the purchase history list that the prefetching (GH-204) must leave untouched.
 *
 * Every scenario here describes what the list produced before the ledger and rebooking data
 * were fetched for the whole list at once.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\output\shoppingcart_history_list
 */
final class shoppingcart_history_list_edge_cases_test extends advanced_testcase {
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
     * With two cancellation rows for one purchase, the list links to the one written first.
     */
    public function test_cancel_confirmation_points_at_first_cancellation(): void {

        $user = $this->getDataGenerator()->create_user();
        $canceled = $this->insert_history($user->id, [
            'itemname' => 'canceled twice',
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
            'identifier' => 3000001,
        ]);
        $this->insert_ledger($user->id, $canceled, 3000001, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);
        $this->insert_ledger($user->id, $canceled, 3000005, LOCAL_SHOPPING_CART_PAYMENT_CANCELED);
        $this->insert_ledger($user->id, $canceled, 3000009, LOCAL_SHOPPING_CART_PAYMENT_CANCELED);

        $this->setUser($user);
        $items = $this->render_for_user($user->id);

        $confirmation = $items['canceled twice']['cancelconfirmation'];
        $this->assertEquals(3000005, $confirmation['identifier']);
        // The identifier comes from the database and used to be handed over unchanged.
        $this->assertSame('3000005', $confirmation['identifier']);
        $this->assertStringContainsString('id=3000005', $confirmation['cancelconfirmationurl']->out(false));
    }

    /**
     * Identifiers of installment receipts keep the type the database returns.
     */
    public function test_installment_identifiers_keep_their_type(): void {

        $user = $this->getDataGenerator()->create_user();
        $first = $this->insert_history($user->id, ['itemname' => 'first rate', 'identifier' => 3000011]);
        $this->insert_ledger($user->id, $first, 3000011, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);
        $this->insert_ledger($user->id, $first, 3000012, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);

        $this->setUser($user);
        $items = $this->render_for_user($user->id);

        $urls = $items['first rate']['installmentreceipturls'];
        $this->assertCount(2, $urls);
        $this->assertSame('3000012', $urls[1]['identifier']);
    }

    /**
     * A rebooking credit line from the ledger is not a purchase and gets no cancel button.
     */
    public function test_rebooking_credit_line_gets_no_cancel_button(): void {

        set_config('showextrareceiptstousers', 1, 'local_shopping_cart');
        set_config('cancelationfee', 0, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        // Make sure the ledger id of the credit line does not coincide with a history id.
        $this->insert_ledger($user->id, 0, 3000021, LOCAL_SHOPPING_CART_PAYMENT_PENDING);
        $this->insert_ledger($user->id, 0, 3000022, LOCAL_SHOPPING_CART_PAYMENT_PENDING);
        $this->insert_ledger($user->id, 0, 3000023, LOCAL_SHOPPING_CART_PAYMENT_PENDING);
        $this->insert_history($user->id, ['itemname' => 'real purchase', 'identifier' => 3000024]);
        $this->insert_credit_line($user->id, 'rebooking credit', 'main');

        $this->setUser($user);
        $items = $this->render_for_user($user->id);

        $this->assertSame('btn-primary', $items['real purchase']['buttonclass']);
        $this->assertSame('disabled hidden', $items['rebooking credit']['buttonclass']);
        $this->assertSame(
            get_string('youcannotcancelanymore', 'local_shopping_cart'),
            $items['rebooking credit']['canceluntilalert']
        );
    }

    /**
     * A rebooking credit line without area must not break the list when rebooking is on.
     */
    public function test_rebooking_credit_line_without_area_with_rebooking_enabled(): void {

        set_config('showextrareceiptstousers', 1, 'local_shopping_cart');
        set_config('allowrebooking', 1, 'local_shopping_cart');
        set_config('rebookingperiod', 30, 'local_shopping_cart');
        set_config('rebookingmaxnumber', 5, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->insert_history($user->id, ['itemname' => 'real purchase', 'identifier' => 3000031]);
        $this->insert_credit_line($user->id, 'rebooking credit', null);

        $this->setUser($user);
        $items = $this->render_for_user($user->id);

        $this->assertArrayHasKey('rebooking credit', $items);
        $this->assertTrue($items['real purchase']['showrebooking']);
    }

    /**
     * Build the list for a user and key the items by their name.
     *
     * @param int $userid
     * @return array
     */
    private function render_for_user(int $userid): array {

        $list = (new shoppingcart_history_list($userid))->return_list();

        $keyed = [];
        foreach ($list['historyitems'] as $item) {
            $keyed[$item['itemname']] = $item;
        }
        return $keyed;
    }

    /**
     * Write a history row.
     *
     * @param int $userid
     * @param array $overrides
     * @return int the history id
     */
    private function insert_history(int $userid, array $overrides = []): int {

        global $DB;

        $now = time();
        $record = (object) array_merge([
            'userid' => $userid,
            'itemid' => 1,
            'itemname' => 'purchase',
            'price' => 10.00,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'main',
            'identifier' => 3000000,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'usermodified' => $userid,
            'timecreated' => $now - 100,
            'timemodified' => $now - 100,
            'canceluntil' => $now + WEEKSECS,
            'serviceperiodstart' => $now,
            'serviceperiodend' => $now + YEARSECS,
        ], $overrides);

        return $DB->insert_record('local_shopping_cart_history', $record);
    }

    /**
     * Write a ledger row that belongs to a purchase.
     *
     * @param int $userid
     * @param int $schistoryid
     * @param int $identifier
     * @param int $paymentstatus
     */
    private function insert_ledger(int $userid, int $schistoryid, int $identifier, int $paymentstatus): void {

        global $DB;

        $now = time();
        $DB->insert_record('local_shopping_cart_ledger', (object) [
            'userid' => $userid,
            'itemid' => 1,
            'itemname' => 'ledger row',
            'price' => 10.00,
            'credits' => 0,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'main',
            'identifier' => $identifier,
            'schistoryid' => $schistoryid ?: null,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => $paymentstatus,
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Write a ledger row of the type "rebooking credits correction".
     *
     * @param int $userid
     * @param string $itemname
     * @param string|null $area
     */
    private function insert_credit_line(int $userid, string $itemname, ?string $area): void {

        global $DB;

        $now = time();
        $DB->insert_record('local_shopping_cart_ledger', (object) [
            'userid' => $userid,
            'itemid' => 0,
            'itemname' => $itemname,
            'price' => 0,
            'credits' => 12.5,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => $area,
            'identifier' => 3000099,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_REBOOKING_CREDITS_CORRECTION,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
