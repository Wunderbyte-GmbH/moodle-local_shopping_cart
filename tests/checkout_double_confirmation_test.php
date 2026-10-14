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
use local_shopping_cart\payment\service_provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;

// The payment constants used below live in lib.php, which is not loaded automatically.
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Tests that confirming the same online payment twice does not book the used credits twice.
 *
 * A payment gateway may deliver the same order more than once (webhook plus return url, retries).
 * The second delivery must not change the ledger or the credits of the user.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\shopping_cart::confirm_payment
 * @covers \local_shopping_cart\shopping_cart::add_record_to_ledger_table
 * @covers \local_shopping_cart\shopping_cart_credits::use_credit
 * @covers \local_shopping_cart\payment\service_provider::deliver_order
 */
final class checkout_double_confirmation_test extends advanced_testcase {
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
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * The ledger line "credits used" has to exist exactly once per checkout.
     */
    public function test_second_delivery_does_not_duplicate_credits_used_in_ledger(): void {
        global $DB;

        [$user, $identifier] = $this->prepare_online_checkout_with_credits(5.0);

        service_provider::deliver_order('', $identifier, 1, $user->id);

        $ledgerparams = ['userid' => $user->id, 'identifier' => $identifier, 'itemid' => 0];
        $this->assertEquals(
            1,
            $DB->count_records('local_shopping_cart_ledger', $ledgerparams),
            'The first delivery has to write the credits used line exactly once.'
        );
        $ledgerbefore = $DB->count_records('local_shopping_cart_ledger', ['userid' => $user->id]);

        // The gateway delivers the very same order a second time.
        service_provider::deliver_order('', $identifier, 1, $user->id);

        $this->assertEquals(
            1,
            $DB->count_records('local_shopping_cart_ledger', $ledgerparams),
            'The credits used line was written a second time.'
        );
        $this->assertEquals(
            $ledgerbefore,
            $DB->count_records('local_shopping_cart_ledger', ['userid' => $user->id]),
            'The second delivery changed the ledger.'
        );
    }

    /**
     * A second delivery must not touch the credits of the user at all.
     */
    public function test_second_delivery_does_not_write_credits_again(): void {
        global $DB;

        [$user, $identifier] = $this->prepare_online_checkout_with_credits(5.0);

        service_provider::deliver_order('', $identifier, 1, $user->id);

        $creditsbefore = $DB->count_records('local_shopping_cart_credits', ['userid' => $user->id]);
        [$balancebefore] = shopping_cart_credits::get_balance($user->id);

        service_provider::deliver_order('', $identifier, 1, $user->id);

        $this->assertEquals(
            $creditsbefore,
            $DB->count_records('local_shopping_cart_credits', ['userid' => $user->id]),
            'The second delivery wrote an additional (zero) line to the credits table.'
        );
        [$balanceafter] = shopping_cart_credits::get_balance($user->id);
        $this->assertEquals($balancebefore, $balanceafter, 'The balance changed with the second delivery.');
    }

    /**
     * Put one item in the cart, give the user some credits and prepare the online payment.
     *
     * @param float $credits the credits the user owns and wants to use
     * @return array [user, identifier of the checkout]
     */
    private function prepare_online_checkout_with_credits(float $credits): array {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        shopping_cart_credits::add_credit($user->id, $credits, 'EUR', '');
        shopping_cart::save_used_credit_state($user->id, 1);

        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', 1, $user->id);

        // This is what the checkout page does before the user is sent to the payment gateway.
        $cartstore = cartstore::instance($user->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);
        service_provider::get_payable('', $data['identifier']);

        return [$user, (int) $data['identifier']];
    }
}
