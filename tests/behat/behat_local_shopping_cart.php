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
 * Behat.
 *
 * @package    local_shopping_cart
 * @copyright  2013-2020, Open Badge Factory Oy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Behat\Context\Step\Given;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;

/**
 * Behat functions.
 *
 * Currently requires modification to ienteravalidrequesttokento, and usage
 * of demo OBF accounts as tests delete all badges on OBF after running.
 *
 * @copyright  2013-2020, Open Badge Factory Oy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_shopping_cart extends behat_base {

    /**
     * Clean shopping cart for given user.
     *
     * @param string $username
     * @Given /^Shopping cart has been cleaned for user "([^"]*)"$/
     */
    public function i_clean_users_cart(string $username) {
        $userid = $this->get_user_id_by_identifier($username);
        shopping_cart::delete_all_items_from_cart($userid);
    }

    /**
     * Put specified item in shopping cart for given user.
     *
     * @param int $itemid
     * @param string $username
     * @Given /^Testitem "(?P<itemid_int>(?:[^"]|\\")*)" has been put in shopping cart of user "([^"]*)"$/
     */
    public function i_put_testitem_in_users_cart(int $itemid, string $username) {
        $userid = $this->get_user_id_by_identifier($username);
        // Put in a cart item.
        shopping_cart::buy_for_user($userid);
        $cartstore = cartstore::instance($userid);
        $data = shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $itemid, -1);
        $data = $cartstore->get_data();
    }

    /**
     * Purchase specified testitem for user (with checkout by cash).
     *
     * @param int $itemid
     * @param string $username
     * @Given /^Testitem "(?P<itemid_int>(?:[^"]|\\")*)" has been purchased by user "([^"]*)"$/
     */
    public function i_buy_testitem_for_user(int $itemid, string $username) {
        $userid = $this->get_user_id_by_identifier($username);
        // Clean cart.
        shopping_cart::delete_all_items_from_cart($userid);
        // Put item in cart.
        shopping_cart::buy_for_user($userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $itemid, -1);
        // Confirm purchase.
        shopping_cart::confirm_payment($userid, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH);
    }
}
