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
use core_payment\helper;
use local_shopping_cart\external\add_item_to_cart;
use local_shopping_cart\external\confirm_cash_payment;
use local_shopping_cart\external\get_history_item;
use local_shopping_cart\local\cartstore;

/**
 * Tests for shopping_cart::add_partial_refund().
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_shopping_cart\shopping_cart::add_partial_refund
 */
final class partial_refund_test extends advanced_testcase {
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
     * Buys a cancellable ("main" area) item for the given user as cashier and returns
     * the paid price and the history id.
     *
     * @param \stdClass $user
     * @param int $itemid
     * @return array{0: float, 1: int} [paid price, history id]
     */
    private function buy_cancellable_item(\stdClass $user, int $itemid): array {
        $account = helper::save_payment_account((object)['name' => 'Test 1', 'idnumber' => '']);
        helper::save_payment_gateway(
            (object)['accountid' => $account->get('id'), 'gateway' => 'paypal', 'config' => 'T1']
        );

        $this->setAdminUser();
        $addresult = add_item_to_cart::execute('local_shopping_cart', 'main', $itemid, $user->id);
        $this->assertEquals(1, $addresult['success'], 'Item was not added to cart.');
        $price = (float) $addresult['price'];

        $purchaseresult = confirm_cash_payment::execute($user->id, 3, 'partial refund test purchase');
        $this->assertEquals(1, $purchaseresult['status'], 'Purchase was not confirmed.');

        $historyitem = get_history_item::execute('local_shopping_cart', 'main', $itemid, $user->id);
        return [$price, (int) $historyitem['id']];
    }

    /**
     * A partial refund grants the amount as credit, records its own ledger entry with the
     * partial-refund payment method, and leaves the original purchase untouched.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_partial_refund_credits_and_keeps_purchase(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        [$price, $historyid] = $this->buy_cancellable_item($user, 1);

        $refund = round($price / 2, 2);
        $this->assertGreaterThan(0, $refund, 'Test precondition: paid price must be positive.');

        // Called as the user herself - no cashier capability required.
        $this->setUser($user);
        $result = shopping_cart::add_partial_refund(
            'local_shopping_cart',
            'main',
            1,
            $user->id,
            $refund,
            'Rebooking refund: slot A -> slot B'
        );

        $this->assertEquals(1, $result['success'], 'Partial refund should succeed.');
        $this->assertNotEmpty($result['identifier'], 'Partial refund should return its own identifier.');

        // The refund is credited to the user's balance.
        $balance = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta($refund, (float) $balance[0], 0.001, 'Refund should be credited to balance.');

        // A dedicated ledger entry documents the refund with the partial-refund payment method.
        $ledger = $DB->get_record('local_shopping_cart_ledger', [
            'schistoryid' => $historyid,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_PARTIAL_REFUND,
        ]);
        $this->assertNotEmpty($ledger, 'A partial-refund ledger entry should exist.');
        $this->assertEqualsWithDelta($refund, (float) $ledger->credits, 0.001, 'Ledger credits should equal the refund.');
        $this->assertEquals(
            LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            (int) $ledger->paymentstatus,
            'Partial refund must NOT be booked as a cancellation.'
        );
        $this->assertEquals(
            'Rebooking refund: slot A -> slot B',
            $ledger->itemname,
            'Ledger itemname should carry the given description.'
        );

        // The original purchase stays active (it is not cancelled).
        $purchase = $DB->get_record('local_shopping_cart_history', ['id' => $historyid]);
        $this->assertEquals(
            LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            (int) $purchase->paymentstatus,
            'The original purchase must remain successful (not cancelled).'
        );
    }

    /**
     * The refund amount is capped at the originally paid price.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_partial_refund_caps_at_paid_price(): void {
        $user = $this->getDataGenerator()->create_user();
        [$price] = $this->buy_cancellable_item($user, 1);

        $this->setUser($user);
        $result = shopping_cart::add_partial_refund(
            'local_shopping_cart',
            'main',
            1,
            $user->id,
            $price + 100.0,
            'Overlarge refund attempt'
        );

        $this->assertEquals(1, $result['success'], 'Partial refund should still succeed (capped).');
        $balance = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta($price, (float) $balance[0], 0.001, 'Refund must be capped at the paid price.');
    }

    /**
     * A non-positive amount is rejected and credits nothing.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_partial_refund_rejects_invalid_amount(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->buy_cancellable_item($user, 1);

        $this->setUser($user);
        $result = shopping_cart::add_partial_refund('local_shopping_cart', 'main', 1, $user->id, 0.0, 'Zero refund');

        $this->assertEquals(0, $result['success'], 'A zero/negative refund must be rejected.');
        $this->assertNotEmpty($result['error'], 'An error message should be returned.');
        $balance = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta(0.0, (float) $balance[0], 0.001, 'Nothing should be credited on a rejected refund.');
    }

    /**
     * A refund without a matching purchase is rejected.
     *
     * @runInSeparateProcess
     * @return void
     */
    public function test_partial_refund_rejects_without_purchase(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = shopping_cart::add_partial_refund('local_shopping_cart', 'main', 999, $user->id, 5.0, 'No purchase');

        $this->assertEquals(0, $result['success'], 'A refund without a matching purchase must be rejected.');
        $this->assertNotEmpty($result['error'], 'An error message should be returned.');
        $balance = shopping_cart_credits::get_balance($user->id);
        $this->assertEqualsWithDelta(0.0, (float) $balance[0], 0.001, 'Nothing should be credited without a purchase.');
    }
}
