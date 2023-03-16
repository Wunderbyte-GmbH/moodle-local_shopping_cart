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
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

define('BOOKINGFEE_EACHPURCHASE', 1);
define('BOOKINGFEE_ONLYONCE', 2);

use context_system;
use local_shopping_cart\task\delete_item_task;
use moodle_exception;
use stdClass;

/**
 * Class shopping_cart
 *
 * @author Georg Maißer
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_bookingfee {

    /**
     * entities constructor.
     */
    public function __construct() {
    }

    /**
     *
     * Add fee to cart.
     *
     *
     * @param int $userid
     *
     * @return bool
     */
    public static function add_fee_to_cart(int $userid): bool {

        $config = get_config('local_shopping_cart');

        // Do we need to add a fee at all?
        if ($config->bookingfee < 0.1) {
            return false;
        }

        // Which kind of fee?
        if ($config->bookingfeeonlyonce < 1) {
            $itemid = BOOKINGFEE_EACHPURCHASE;
        } else {
            // TODO: Verify if the user has already ever paid the fee.

            if (self::user_has_paid_fee($userid)) {
                return false;
            }
            $itemid = BOOKINGFEE_EACHPURCHASE;
        }

        shopping_cart::add_item_to_cart('local_shopping_cart', 'bookingfee', $itemid, $userid);

        return true;
    }

    /**
     * User has already paid the fee.
     *
     * @param integer $userid
     * @return bool
     */
    private static function user_has_paid_fee(int $userid) {

        $records = shopping_cart_history::return_items_from_history(
            BOOKINGFEE_ONLYONCE,
            'local_shopping_cart',
            'bookingfee',
            $userid);

        if (count($records) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Is shopping cart fee.
     *
     * @param string $component
     * @param string $area
     * @return boolean
     */
    public static function is_fee(string $component, string $area):bool {

        if ($component === 'local_shopping_cart'
            && $area === 'bookingfee') {

            return true;
        }

        return false;
    }
}
