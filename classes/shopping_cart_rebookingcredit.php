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
 * Entities Class to display list of entity records.
 *
 * @package local_shopping_cart
 * @author Thomas Winkler
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Class shopping_cart
 *
 * @author Georg Maißer
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_rebookingcredit {

    /**
     * entities constructor.
     */
    public function __construct() {
    }

    /**
     *
     * Add rebookingcredit to cart.
     *
     *
     * @param int $userid the id of the user who books (-1 if cashier books for another user)
     * @param int $buyforuserid the id of the user to buy for (for cashier only)
     *
     * @return bool
     */
    public static function add_rebookingcredit_to_cart(int $userid, int $buyforuserid = 0): bool {

        // Do we need to add a fee at all?
        if (get_config('local_shopping_cart', 'allowrebookingcredit')) {
            return false;
        }

        shopping_cart::add_item_to_cart('local_shopping_cart', 'rebookingcredit', 1, $userid);

        return true;
    }
}
