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
 * Behat question-related steps definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\shopping_cart;

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

/**
 * Steps definitions related with the mooduell table management.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_shopping_cart extends behat_base {

    /**
     * Put item in my cart.
     * This ads a dummy item to the cache. After reloading the page, the item will be visible.
     * @Given /^I put "(?P<itemname_string>(?:[^"]|\\")*)" in my cart$/
     * @param string $itemname
     * @return void
     */
    public function i_put_item_in_my_cart(string $itemname) {

        $item = new cartitem(1, $itemname, 10, 'EUR', 'mod_quiz', 'item description');
        $data = $item->as_array();

        $shoppingcart = new shopping_cart();
        $shoppingcart->add_item_to_cart('local_shopping_cart', 1, 0); // TODO: Fix this with the correct params!
    }
}
