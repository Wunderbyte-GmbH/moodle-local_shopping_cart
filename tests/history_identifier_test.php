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
 * Tests that the cart identifier decides where the history is written.
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use stdClass;

/**
 * Tests that the cart identifier decides where the history is written.
 *
 * A checkout that received a fresh identifier must store its history under that
 * identifier, even when the items of the cart still carry the previous one.
 * Otherwise the payment gateway and deliver_order() look up an identifier that
 * has no rows, and a captured payment delivers nothing.
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class history_identifier_test extends advanced_testcase {
    /**
     * Tests set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Builds a cart whose items carry a different identifier than the cart itself.
     *
     * @param int $userid
     * @param int $cartidentifier identifier of the checkout, the one that gets paid
     * @param int $itemidentifier identifier left on the items by a previous checkout
     * @param int $itemid
     * @return stdClass
     */
    private function build_cart(int $userid, int $cartidentifier, int $itemidentifier, int $itemid = 4711): stdClass {
        $item = [
            'userid' => $userid,
            'itemid' => $itemid,
            'itemname' => 'Test item',
            'price' => 42.00,
            'currency' => 'EUR',
            'componentname' => 'local_shopping_cart',
            'area' => 'main',
            'identifier' => $itemidentifier,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_PENDING,
            'usermodified' => $userid,
            'timemodified' => time(),
            'canceluntil' => 0,
        ];

        $cart = new stdClass();
        $cart->userid = $userid;
        $cart->identifier = $cartidentifier;
        $cart->items = ['local_shopping_cart-main-' . $itemid => $item];

        return $cart;
    }

    /**
     * The history has to be written under the identifier of the cart, not of the items.
     *
     * @covers \local_shopping_cart\shopping_cart_history::write_to_db
     */
    public function test_history_is_written_under_the_cart_identifier(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 222, 111));

        $this->assertNotEmpty(
            shopping_cart_history::return_data_via_identifier(222),
            'The checkout that gets paid must have history rows.'
        );
    }

    /**
     * The real world case: the previous identifier already has rows, so the duplicate
     * check of write_to_db() silently skips the insert and the paid cart keeps none.
     *
     * @covers \local_shopping_cart\shopping_cart_history::write_to_db
     */
    public function test_history_is_written_although_the_previous_identifier_has_rows(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // First checkout, abandoned by the user.
        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 111, 111));
        $this->assertNotEmpty(shopping_cart_history::return_data_via_identifier(111));

        // The cart gets a new identifier, the items still point at the old one.
        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 222, 111));

        $this->assertNotEmpty(
            shopping_cart_history::return_data_via_identifier(222),
            'The checkout that gets paid must have history rows of its own.'
        );
    }

    /**
     * Two carts of one user with different identifiers must be payable independently.
     *
     * This is the two tab case: each tab has its own cart identifier, and each of them
     * has to end up with its own history, otherwise the tab whose items carry the other
     * identifier pays without being delivered.
     *
     * @covers \local_shopping_cart\shopping_cart_history::write_to_db
     */
    public function test_two_carts_with_different_identifiers_are_written_separately(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // First tab, cart 111 with item 4711.
        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 111, 111, 4711));

        // Second tab, cart 222 with a different item, but the items still carry 111.
        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 222, 111, 4712));

        $first = shopping_cart_history::return_data_via_identifier(111);
        $second = shopping_cart_history::return_data_via_identifier(222);

        $this->assertCount(1, $first, 'The first cart keeps its own history.');
        $this->assertCount(1, $second, 'The second cart gets its own history.');

        $firstrecord = reset($first);
        $secondrecord = reset($second);
        $this->assertEquals(4711, $firstrecord->itemid);
        $this->assertEquals(111, $firstrecord->identifier);
        $this->assertEquals(4712, $secondrecord->itemid);
        $this->assertEquals(222, $secondrecord->identifier);
    }

    /**
     * Writing a second cart must not move or remove the history of the first one.
     *
     * @covers \local_shopping_cart\shopping_cart_history::write_to_db
     */
    public function test_writing_a_second_cart_leaves_the_first_one_intact(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 111, 111));
        $before = shopping_cart_history::return_data_via_identifier(111);

        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 222, 111));
        $after = shopping_cart_history::return_data_via_identifier(111);

        $this->assertEquals(
            array_keys($before),
            array_keys($after),
            'The history of the first cart must survive unchanged.'
        );
    }

    /**
     * A cart whose items already agree with the cart keeps behaving as before.
     *
     * @covers \local_shopping_cart\shopping_cart_history::write_to_db
     */
    public function test_matching_identifiers_are_untouched(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        shopping_cart_history::write_to_db($this->build_cart((int) $user->id, 333, 333));

        $records = shopping_cart_history::return_data_via_identifier(333);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertEquals(333, $record->identifier);
    }
}
