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
 * Guards the number of database reads the purchase history list needs.
 *
 * The list used to run one to three ledger queries plus a redundant re-read of the history row
 * for every single entry, which made checkout.php and /my/ scale linearly with the number of
 * purchases a user has (GH-204). This test fails as soon as a per-entry query comes back.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\output\shoppingcart_history_list
 */
final class shoppingcart_history_list_performance_test extends advanced_testcase {
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
        cartstore::reset();
    }

    /**
     * The number of reads must not grow with the number of history entries.
     */
    public function test_reads_do_not_grow_with_number_of_history_entries(): void {

        $this->setup_payment_account();

        $small = $this->getDataGenerator()->create_user();
        $large = $this->getDataGenerator()->create_user();

        $this->create_history_entries($small->id, 5);
        $this->create_history_entries($large->id, 50);

        $readsforfive = $this->count_reads_for_history_list($small->id);
        $readsforfifty = $this->count_reads_for_history_list($large->id);

        /* Ten times the entries must not cost noticeably more queries. We allow a small margin so
        that an additional constant query does not fail the test, but a per-entry query does:
        45 more entries would show up as at least 45 more reads. */
        $this->assertLessThanOrEqual(
            $readsforfive + 3,
            $readsforfifty,
            "Building the history list for 50 entries needed $readsforfifty reads, for 5 entries "
                . "$readsforfive. The difference means the list queries the database per entry again."
        );

        // Absolute budget, so that a constant but expensive addition is noticed as well.
        $this->assertLessThanOrEqual(
            15,
            $readsforfifty,
            "Building the history list needed $readsforfifty reads, which is above the budget of 15."
        );
    }

    /**
     * Installment receipts and cancellation confirmations must be prefetched, not read per entry.
     */
    public function test_reads_do_not_grow_with_number_of_installment_entries(): void {

        $this->setup_payment_account();

        $small = $this->getDataGenerator()->create_user();
        $large = $this->getDataGenerator()->create_user();

        $this->create_history_entries($small->id, 5, true);
        $this->create_history_entries($large->id, 50, true);

        $readsforfive = $this->count_reads_for_history_list($small->id);
        $readsforfifty = $this->count_reads_for_history_list($large->id);

        $this->assertLessThanOrEqual(
            $readsforfive + 3,
            $readsforfifty,
            "With installment entries, 50 entries needed $readsforfifty reads and 5 entries "
                . "$readsforfive. The ledger data is being read per entry again."
        );
    }

    /**
     * The list must still show the same information after the prefetching.
     */
    public function test_history_list_content_is_unchanged(): void {

        $this->setup_payment_account();

        $user = $this->getDataGenerator()->create_user();
        $this->create_history_entries($user->id, 3, true);
        $this->setUser($user);

        $list = (new shoppingcart_history_list($user->id))->return_list();

        $this->assertCount(3, $list['historyitems']);
        $this->assertTrue($list['has_historyitems']);

        foreach ($list['historyitems'] as $item) {
            // Every entry knows its own receipt.
            $this->assertNotEmpty($item['receipturl']);
            /* Each entry shares its schistoryid with the two others, so each one has to offer the
            receipts of the other two as installments. */
            $this->assertTrue(!empty($item['hasinstallments']));
            $this->assertCount(3, $item['installmentreceipturls']);
        }
    }

    /**
     * Create a payment account and point the plugin at it.
     */
    private function setup_payment_account(): void {

        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['name' => 'Test account']);
        set_config('accountid', $account->get('id'), 'local_shopping_cart');
    }

    /**
     * Write history entries for a user directly to the database.
     *
     * @param int $userid
     * @param int $count number of entries
     * @param bool $withinstallments also write ledger entries that tie the purchases together
     */
    private function create_history_entries(int $userid, int $count, bool $withinstallments = false): void {

        global $DB;

        $now = time();
        $schistoryid = 0;

        for ($i = 1; $i <= $count; $i++) {
            $record = new stdClass();
            $record->userid = $userid;
            $record->itemid = $i;
            $record->itemname = 'Test item ' . $i;
            $record->price = 10.00;
            $record->currency = 'EUR';
            $record->componentname = 'local_shopping_cart';
            $record->area = 'main';
            $record->identifier = $userid * 100000 + $i;
            $record->payment = LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE;
            $record->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
            $record->usermodified = $userid;
            $record->timecreated = $now - $i;
            $record->timemodified = $now - $i;
            $record->canceluntil = $now + WEEKSECS;
            $record->serviceperiodstart = $now;
            $record->serviceperiodend = $now + YEARSECS;

            $historyid = $DB->insert_record('local_shopping_cart_history', $record);

            if (!$withinstallments) {
                continue;
            }

            /* All entries of a user point to the same schistoryid, so every entry has to collect
            the receipts of all the others. That is the expensive case of the old code. */
            $schistoryid = $schistoryid ?: $historyid;

            $ledger = clone $record;
            $ledger->schistoryid = $schistoryid;
            $DB->insert_record('local_shopping_cart_ledger', $ledger);
        }
    }

    /**
     * Count the database reads one full build of the history list needs.
     *
     * The list is built twice and only the second run is counted, so that caches which are filled
     * once per request (configuration, capabilities) do not distort the number.
     *
     * @param int $userid
     * @return int
     */
    private function count_reads_for_history_list(int $userid): int {

        global $DB;

        $this->setUser($userid);

        new shoppingcart_history_list($userid);

        $before = $DB->perf_get_reads();
        new shoppingcart_history_list($userid);

        return $DB->perf_get_reads() - $before;
    }
}
