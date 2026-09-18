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
 * Tests for the setting that switches off the automatic completion of ongoing payments.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\local\cartstore;

/**
 * Tests for checkongoingpayments.
 *
 * Opening the checkout page asks every payment gateway whether a pending payment of this user has
 * been completed meanwhile. The setting switches that off, so the payment provider is not asked.
 *
 * @covers \local_shopping_cart\shopping_cart::check_for_ongoing_payment
 */
final class ongoing_payment_check_test extends advanced_testcase {
    /** @var int the buyer */
    private int $userid;

    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['name' => 'Account1']);
        set_config('accountid', $account->get('id'), 'local_shopping_cart');

        global $USER;
        $this->userid = (int) $USER->id;
    }

    /**
     * Mandatory clean-up after each test.
     */
    protected function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * The check is on by default, also on a site where the setting was never saved, and it looks
     * for payment accounts and their gateways.
     */
    public function test_check_runs_by_default(): void {
        // A site where the setting has never been saved.
        unset_config('checkongoingpayments', 'local_shopping_cart');

        $this->assertGreaterThan(1, $this->reads_of_check(), 'The check has to look for ongoing payments.');
    }

    /**
     * An explicitly enabled check behaves like the default.
     */
    public function test_check_runs_when_switched_on(): void {
        set_config('checkongoingpayments', 1, 'local_shopping_cart');

        $this->assertGreaterThan(1, $this->reads_of_check(), 'The check has to look for ongoing payments.');
    }

    /**
     * With the setting switched off, the check returns after reading the setting and asks no gateway.
     */
    public function test_check_is_skipped_when_switched_off(): void {
        set_config('checkongoingpayments', 1, 'local_shopping_cart');
        $readswhenon = $this->reads_of_check();

        set_config('checkongoingpayments', 0, 'local_shopping_cart');
        $readswhenoff = $this->reads_of_check();

        // Only the setting itself is read, nothing is looked up for the gateways.
        $this->assertLessThanOrEqual(1, $readswhenoff, 'Nothing but the setting must be read when the check is off.');
        $this->assertLessThan($readswhenon, $readswhenoff);
    }

    /**
     * Switching the check off still leaves a usable cart and checkout data.
     */
    public function test_cart_still_works_when_check_is_switched_off(): void {
        set_config('checkongoingpayments', 0, 'local_shopping_cart');
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 1, $this->userid);

        $cartstore = cartstore::instance($this->userid);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data, false);

        $this->assertCount(1, $data['items']);
        $this->assertNotEmpty($data['identifier']);
    }

    /**
     * Number of database reads the check needs.
     *
     * @return int
     */
    private function reads_of_check(): int {
        global $DB;

        $before = $DB->perf_get_reads();
        shopping_cart::check_for_ongoing_payment($this->userid);
        return $DB->perf_get_reads() - $before;
    }
}
