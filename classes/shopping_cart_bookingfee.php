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

define('LOCAL_SHOPPING_CART_BOOKINGFEE_ANY', 0);
define('LOCAL_SHOPPING_CART_BOOKINGFEE_EACHPURCHASE', 1);
define('LOCAL_SHOPPING_CART_BOOKINGFEE_ONLYONCE', 2);

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
     * @param int $userid the id of the user who books (-1 if cashier books for another user)
     * @param int $buyforuserid the id of the user to buy for (for cashier only)
     *
     * @return bool
     */
    public static function add_fee_to_cart(int $userid, int $buyforuserid = 0): bool {

        // Do we need to add a fee at all?
        if (get_config('local_shopping_cart', 'bookingfee') <= 0
            && empty(get_config('local_shopping_cart', 'bookingfeevariable'))) {
            return false;
        }

        // Which kind of fee?
        if (get_config('local_shopping_cart', 'bookingfeeonlyonce')) {
            // Verify if the user has already ever paid the fee.
            if ($userid >= 0) {
                if (self::user_has_paid_fee($userid)) {
                    return false;
                }
            } else if ($userid < 0 && !empty($buyforuserid)) {
                // Cashier books for other user.
                if (self::user_has_paid_fee($buyforuserid)) {
                    return false;
                }
            }
            $itemid = LOCAL_SHOPPING_CART_BOOKINGFEE_ONLYONCE;
        } else {
            $itemid = LOCAL_SHOPPING_CART_BOOKINGFEE_EACHPURCHASE;
        }

        // See if we are about to rebook, we don't add the booking fee.
        if (($buyforuserid == 0) && shopping_cart_rebookingcredit::is_rebooking($userid)) {
            return false;
        }

        shopping_cart::add_item_to_cart('local_shopping_cart', 'bookingfee', $itemid, $userid);

        return true;
    }

    /**
     * User has already paid the fee.
     *
     * @param int $userid
     * @param int $bookingfeetype
     * @return bool
     */
    private static function user_has_paid_fee(int $userid, int $bookingfeetype = LOCAL_SHOPPING_CART_BOOKINGFEE_ANY) {

        if ($bookingfeetype === LOCAL_SHOPPING_CART_BOOKINGFEE_ANY) {
            // Any booking fee type. So look for all of them and merge.
            $records1 = shopping_cart_history::return_items_from_history(
                LOCAL_SHOPPING_CART_BOOKINGFEE_ONLYONCE,
                'local_shopping_cart',
                'bookingfee',
                $userid);
            $records2 = shopping_cart_history::return_items_from_history(
                LOCAL_SHOPPING_CART_BOOKINGFEE_EACHPURCHASE,
                'local_shopping_cart',
                'bookingfee',
                $userid);
            $records = array_merge($records1, $records2);
        } else {
            // Specific booking fee type.
            $records = shopping_cart_history::return_items_from_history(
                $bookingfeetype,
                'local_shopping_cart',
                'bookingfee',
                $userid);
        }

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
     * @return bool
     */
    public static function is_fee(string $component, string $area): bool {

        if ($component === 'local_shopping_cart'
            && $area === 'bookingfee') {

            return true;
        }

        return false;
    }
}
