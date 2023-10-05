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
use local_shopping_cart\local\entities\cartitem;
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
     * Put an item in your shopping cart.
     * The name will actually ignored.
     *
     * @param string $itemname
     * @Given /^I put item "(?P<itemname_string>(?:[^"]|\\")*)" in my cart$/
     */
    public function i_put_item_in_my_cart(string $itemname) {
        // Put in a cart item.
        shopping_cart::add_item_to_cart('local_shopping_cart', 'behattest', 1, 0);
    }

    /**
     * Put an item in shopping cart for specified user.
     * The name will actually ignored.
     *
     * @param string $username
     * @Given /^I put item in shopping cart in behalf of user "(?P<username_string>(?:[^"]|\\")*)"$/
     */
    public function i_put_item_in_users_cart(string $username) {
        // Put in a cart item.
        shopping_cart::buy_for_user($this->get_user_id_by_identifier($username));
        shopping_cart::add_item_to_cart('local_shopping_cart', 'behattest', 1, -1);
    }

    /**
     * Delete existing cart, add two testitems and checkout.
     *
     * @Given /^I buy two items$/
     */
    public function i_buy_two_items() {

        global $USER;

        // Clean cart.
        shopping_cart::delete_all_items_from_cart($USER->id);

        // Put in 2 items.
        shopping_cart::add_item_to_cart('local_shopping_cart', 'behattest', 1, 0);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'behattest', 2, 0);

        // Not sure if we should add the booking fee. It could be added automatically anyways.
        //shopping_cart::add_item_to_cart('local_shopping_cart', 'bookingfee', 4, $USER->id);

        shopping_cart::confirm_payment($USER->id, 0);
    }
}
