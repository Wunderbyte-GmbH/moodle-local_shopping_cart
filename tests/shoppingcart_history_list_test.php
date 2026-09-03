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

// The payment constants used below live in lib.php, which is not loaded automatically.
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Pins down what the purchase history list puts into the template.
 *
 * The list decides which cancel buttons, installment receipts and rebooking options a user gets
 * to see. Those decisions are what the performance work must not change, so every scenario below
 * describes one of them in terms of the rendered output (GH-204).
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\output\shoppingcart_history_list
 */
final class shoppingcart_history_list_test extends advanced_testcase {
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
     * With the shipped default (cancelationfee -1) users cannot cancel at all.
     */
    public function test_user_cannot_cancel_with_default_settings(): void {

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['itemname' => 'not cancelable']);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertSame('disabled hidden', $items['not cancelable']['buttonclass']);
        $this->assertSame(
            get_string('youcannotcancelanymore', 'local_shopping_cart'),
            $items['not cancelable']['canceluntilalert']
        );
    }

    /**
     * A user may cancel a purchase while the cancel until date lies in the future.
     */
    public function test_cancel_button_within_cancelation_period(): void {

        set_config('cancelationfee', 0, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['itemname' => 'still cancelable']);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertSame('btn-primary', $items['still cancelable']['buttonclass']);
        $this->assertNotEmpty($items['still cancelable']['canceluntilalert']);
        $this->assertFalse($items['still cancelable']['canceled']);
    }

    /**
     * Once the cancel until date has passed, the button is gone and the user is told why.
     */
    public function test_cancel_button_after_cancelation_period(): void {

        set_config('cancelationfee', 0, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, [
            'itemname' => 'too late',
            'canceluntil' => time() - DAYSECS,
        ]);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertSame('disabled hidden', $items['too late']['buttonclass']);
        $this->assertSame(
            get_string('youcannotcancelanymore', 'local_shopping_cart'),
            $items['too late']['canceluntilalert']
        );
    }

    /**
     * An already canceled purchase is shown as canceled and cannot be canceled again.
     */
    public function test_canceled_purchase(): void {

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, [
            'itemname' => 'canceled item',
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
        ]);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertTrue($items['canceled item']['canceled']);
        $this->assertSame('btn-danger disabled', $items['canceled item']['buttonclass']);
    }

    /**
     * The cashier sees a cancel button even when the cancelation period has passed.
     */
    public function test_cashier_sees_cancel_button_after_cancelation_period(): void {

        set_config('cancelationfee', 0, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, [
            'itemname' => 'too late',
            'canceluntil' => time() - DAYSECS,
        ]);
        $this->setAdminUser();

        $items = $this->render_for_user($user->id);

        $this->assertSame('btn-primary', $items['too late']['buttonclass']);
    }

    /**
     * Purchases that belong to the same installment plan offer each other's receipts.
     */
    public function test_installment_receipts_are_collected(): void {

        $user = $this->getDataGenerator()->create_user();
        $first = $this->create_history_entry($user->id, ['itemname' => 'installment 1']);
        $this->create_history_entry($user->id, ['itemname' => 'installment 2']);
        $this->create_history_entry($user->id, ['itemname' => 'installment 3']);
        $this->tie_entries_together($user->id, $first);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        foreach (['installment 1', 'installment 2', 'installment 3'] as $itemname) {
            $this->assertTrue($items[$itemname]['hasinstallments'], "$itemname has no installments");
            // Its own receipt plus the two of the other entries.
            $this->assertCount(3, $items[$itemname]['installmentreceipturls']);
        }

        // The own identifier must not be repeated among the other receipts.
        $identifiers = array_column($items['installment 1']['installmentreceipturls'], 'identifier');
        $this->assertSame($identifiers, array_unique($identifiers));
    }

    /**
     * A canceled installment purchase links to the cancellation confirmation of its counterpart.
     */
    public function test_cancel_confirmation_of_canceled_installment(): void {

        $user = $this->getDataGenerator()->create_user();
        $canceled = $this->create_history_entry($user->id, [
            'itemname' => 'canceled installment',
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
            'identifier' => 2000001,
        ]);
        $this->create_history_entry($user->id, [
            'itemname' => 'paid installment',
            'identifier' => 2000002,
        ]);

        /* A cancellation writes its own ledger row with a new identifier, which is what the list
        links to as the cancellation confirmation. */
        $this->add_ledger_row($user->id, $canceled, 2000001, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);
        $this->add_ledger_row($user->id, $canceled, 2000002, LOCAL_SHOPPING_CART_PAYMENT_SUCCESS);
        $this->add_ledger_row($user->id, $canceled, 2000003, LOCAL_SHOPPING_CART_PAYMENT_CANCELED);

        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertNotEmpty($items['canceled installment']['cancelconfirmation']);
        $this->assertEquals(
            2000003,
            $items['canceled installment']['cancelconfirmation']['identifier'],
            'The cancellation confirmation must point at the cancellation, not at the entry itself.'
        );
        // An entry that was not canceled has no cancellation confirmation.
        $this->assertTrue(empty($items['paid installment']['cancelconfirmation']));
    }

    /**
     * With rebooking turned on, an item may be rebooked unless its item info forbids it.
     */
    public function test_rebooking_option_follows_item_info(): void {

        global $DB;

        set_config('allowrebooking', 1, 'local_shopping_cart');
        set_config('rebookingperiod', 30, 'local_shopping_cart');
        set_config('rebookingmaxnumber', 5, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['itemname' => 'rebookable', 'itemid' => 1]);
        $this->create_history_entry($user->id, ['itemname' => 'not rebookable', 'itemid' => 2]);

        $iteminfo = new stdClass();
        $iteminfo->itemid = 2;
        $iteminfo->componentname = 'local_shopping_cart';
        $iteminfo->area = 'main';
        $iteminfo->json = json_encode(['allowrebooking' => 0]);
        $iteminfo->usermodified = $user->id;
        $iteminfo->timecreated = time();
        $iteminfo->timemodified = time();
        $DB->insert_record('local_shopping_cart_iteminfo', $iteminfo);

        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertTrue($items['rebookable']['showrebooking']);
        $this->assertNull($items['not rebookable']['showrebooking']);
    }

    /**
     * Nobody may rebook once the maximum number of rebookings within the period is reached.
     */
    public function test_rebooking_option_when_maximum_is_reached(): void {

        set_config('allowrebooking', 1, 'local_shopping_cart');
        set_config('rebookingperiod', 30, 'local_shopping_cart');
        set_config('rebookingmaxnumber', 1, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['itemname' => 'wants rebooking', 'itemid' => 1]);
        // One rebooking already happened within the period.
        $this->create_history_entry($user->id, [
            'itemname' => 'earlier rebooking',
            'itemid' => 2,
            'area' => 'rebookitem',
        ]);

        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertNull($items['wants rebooking']['showrebooking']);
        // The rebooking item itself is never offered for rebooking.
        $this->assertNull($items['earlier rebooking']['showrebooking']);
    }

    /**
     * Without the rebooking setting, no entry carries rebooking information at all.
     */
    public function test_no_rebooking_information_when_turned_off(): void {

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['itemname' => 'plain item']);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertArrayNotHasKey('showrebooking', $items['plain item']);
        $this->assertArrayNotHasKey('rebooking', $items['plain item']);
    }

    /**
     * The receipt page reads its items from the ledger, not from the history table.
     */
    public function test_list_from_ledger_via_identifier(): void {

        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, ['itemname' => 'bought item']);

        $ledger = $DB->get_record('local_shopping_cart_history', ['userid' => $user->id]);
        unset($ledger->id);
        $ledger->credits = 0;
        $DB->insert_record('local_shopping_cart_ledger', $ledger);

        $this->setUser($user);

        $list = (new shoppingcart_history_list($user->id, $ledger->identifier, true))->return_list();
        $items = $this->key_by_itemname($list['historyitems']);

        $this->assertCount(1, $items);
        $this->assertSame('bought item', $items['bought item']['itemname']);
        /* Ledger rows are not history rows: the cancel button stays hidden here, because the
        history id of the ledger entry does not identify a purchase that could be canceled. */
        $this->assertSame('disabled hidden', $items['bought item']['buttonclass']);
    }

    /**
     * Payment strings and prices are localized for the template.
     */
    public function test_payment_string_and_price_formatting(): void {

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entry($user->id, [
            'itemname' => 'paid by cash',
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH,
            'price' => 3.5,
        ]);
        $this->setUser($user);

        $items = $this->render_for_user($user->id);

        $this->assertSame(
            get_string('paymentcashier:cash', 'local_shopping_cart'),
            $items['paid by cash']['paymentstring']
        );
        $this->assertSame('3.50', $items['paid by cash']['price']);
        $this->assertNotEmpty($items['paid by cash']['timecreatedrendered']);
        $this->assertNotEmpty($items['paid by cash']['canceluntilrendered']);
    }

    /**
     * Build the list for a user and key the items by their name.
     *
     * @param int $userid
     * @return array
     */
    private function render_for_user(int $userid): array {

        $list = (new shoppingcart_history_list($userid))->return_list();

        return $this->key_by_itemname($list['historyitems']);
    }

    /**
     * Key a list of history items by item name.
     *
     * @param array $historyitems
     * @return array
     */
    private function key_by_itemname(array $historyitems): array {

        $keyed = [];
        foreach ($historyitems as $item) {
            $keyed[$item['itemname']] = $item;
        }
        return $keyed;
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

        static $counter = 0;
        $counter++;
        $now = time();

        $record = (object) array_merge([
            'userid' => $userid,
            'itemid' => $counter,
            'itemname' => 'Test item ' . $counter,
            'price' => 10.00,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'main',
            'identifier' => 1000000 + $counter,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'usermodified' => $userid,
            'timecreated' => $now - $counter,
            'timemodified' => $now - $counter,
            'canceluntil' => $now + WEEKSECS,
            'serviceperiodstart' => $now,
            'serviceperiodend' => $now + YEARSECS,
        ], $overrides);

        return $DB->insert_record('local_shopping_cart_history', $record);
    }

    /**
     * Write one ledger row that belongs to an installment plan.
     *
     * @param int $userid
     * @param int $schistoryid the history entry the plan is anchored at
     * @param int $identifier
     * @param int $paymentstatus
     */
    private function add_ledger_row(int $userid, int $schistoryid, int $identifier, int $paymentstatus): void {

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
            'schistoryid' => $schistoryid,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => $paymentstatus,
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Make all history entries of a user belong to one installment plan.
     *
     * @param int $userid
     * @param int $schistoryid the history id all ledger entries point to
     * @param bool $keepstatus keep the payment status of the history entry in the ledger
     */
    private function tie_entries_together(int $userid, int $schistoryid, bool $keepstatus = false): void {

        global $DB;

        foreach ($DB->get_records('local_shopping_cart_history', ['userid' => $userid]) as $record) {
            unset($record->id);
            $record->schistoryid = $schistoryid;
            $record->credits = 0;
            if (!$keepstatus) {
                $record->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
            }
            $DB->insert_record('local_shopping_cart_ledger', $record);
        }
    }
}
