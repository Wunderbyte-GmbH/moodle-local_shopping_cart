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
 * Tests that the prolonged payment time really holds the cart during a payment.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\reservations;
use local_shopping_cart\payment\service_provider;
use stdClass;
use tool_mocktesttime\time_mock;

/**
 * Tests that the prolonged payment time really holds the cart during a payment.
 *
 * While the user is at the payment provider, the reservation must not be released. The setting
 * prolongedpaymenttime exists for exactly that, so the prolonged expiration time has to survive a
 * lost cart cache and a cart that is rebuilt from the database.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runInSeparateProcess
 * @runTestsInSeparateProcesses
 */
final class prolonged_payment_time_test extends \advanced_testcase {
    /** @var \core_payment\account account */
    private $account;

    /** @var int Minutes an item stays in the cart before a checkout. */
    private const EXPIRATIONTIME = 30;

    /** @var int Minutes the reservation is held once the payment process has started. */
    private const PROLONGEDTIME = 120;

    /**
     * Setup function.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('country', 'AT');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $this->account = $generator->create_payment_account(['name' => 'PayOne1']);

        $record = new stdClass();
        $record->accountid = $this->account->get('id');
        $record->gateway = 'payone';
        $record->enabled = 1;
        $record->timecreated = time();
        $record->timemodified = time();

        $config = new stdClass();
        $config->environment = 'sandbox';
        $config->brandname = 'fakename';
        $config->clientid = 'fakeclientid';
        $config->secret = 'fakesecret';
        $record->config = json_encode($config);

        \core_payment\helper::save_payment_gateway($record);

        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');
        set_config('expirationtime', self::EXPIRATIONTIME, 'local_shopping_cart');
        set_config('prolongedpaymenttime', self::PROLONGEDTIME, 'local_shopping_cart');

        time_mock::init();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * Fills a cart with two items and starts the checkout for it.
     *
     * @param int $userid
     * @return int the cart identifier
     */
    private function start_checkout(int $userid): int {
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $userid);

        $cartstore = cartstore::instance($userid);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);

        $identifier = (int) $data['identifier'];

        // This is what the payment gateway calls when the payment process starts.
        service_provider::get_payable('', $identifier);

        return $identifier;
    }

    /**
     * Throws the in memory instance and the cache away, as it happens between two requests.
     *
     * @return void
     */
    private function forget_cart_cache(): void {
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * The prolonged expiration time has to be stored, not only kept in the cache.
     *
     * @covers \local_shopping_cart\local\cartstore::set_expiration
     * @covers \local_shopping_cart\shopping_cart_history::write_to_db
     * @return void
     */
    public function test_prolonged_expirationtime_is_persisted(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $starttime = time();
        $identifier = $this->start_checkout($user->id);

        $expected = $starttime + self::PROLONGEDTIME * 60;

        $record = $DB->get_record('local_shopping_cart_reserv', ['identifier' => $identifier]);
        $this->assertNotEmpty($record, 'No reservation was stored for this checkout.');
        $this->assertEquals($expected, (int) $record->expirationtime);

        $stored = json_decode($record->json, true);
        $this->assertEquals($expected, (int) $stored['expirationtime']);
    }

    /**
     * When the cache is lost while the payment runs, the cart must not be emptied.
     *
     * @covers \local_shopping_cart\local\cartstore::get_data
     * @covers \local_shopping_cart\local\cartstore::restore_cart_from_db
     * @return void
     */
    public function test_cart_survives_lost_cache_during_payment(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $starttime = time();
        $this->start_checkout($user->id);

        // The user is at the payment provider. More than expirationtime, less than prolongedpaymenttime.
        time_mock::set_mock_time($starttime + (self::EXPIRATIONTIME + 10) * 60);

        $this->forget_cart_cache();

        $data = cartstore::instance($user->id)->get_data();

        $this->assertCount(2, $data['items'], 'The cart was emptied while the payment was still running.');
        $this->assertEquals($starttime + self::PROLONGEDTIME * 60, (int) $data['expirationtime']);
    }

    /**
     * A prolonged expiration time does not make it a different cart.
     *
     * @covers \local_shopping_cart\local\reservations::different_cart_with_same_identifier
     * @return void
     */
    public function test_a_prolonged_cart_is_not_a_different_cart(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $identifier = $this->start_checkout($user->id);

        $stored = reservations::get_json_from_db_via_identifier($identifier);
        $this->assertNotEmpty($stored);

        $prolonged = $stored;
        $prolonged['expirationtime'] = (int) $stored['expirationtime'] + 3600;

        $this->assertFalse(
            reservations::different_cart_with_same_identifier($prolonged, $identifier),
            'A cart that only got a later expiration time must not count as a different cart.'
        );
    }
}
