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

namespace local_shopping_cart\shopping_cart;

use local_shopping_cart\local\entities\cartitem;

/**
 * Shopping_cart subsystem callback implementation for local_shopping_cart, for testing, does not have any use for production.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_provider implements \local_shopping_cart\local\callback\service_provider {

    /**
     * Callback function that returns the costs and the accountid
     * for the course, just for testing.
     *
     * @param string $component
     * @param string $area
     * @param int $optionid
     * @param int $userid
     * @return cartitem
     */
    public static function load_cartitem(string $component, string $area, int $optionid, int $userid = 0): cartitem {
        $canceluntil = strtotime('+14 days', time());
        $serviceperiodstart = time();
        $serviceperiodend = strtotime('+30 days', time());
        $imageurl = new \moodle_url('/local/shopping_cart/pix/edu.png');

        return new cartitem($optionid,
                            'my test item ' . $optionid,
                            10,
                            'EUR',
                            'local_shopping_cart',
                            'main',
                            'item description',
                            $imageurl->out(),
                            $canceluntil,
                            $serviceperiodstart,
                            $serviceperiodend
                            );
    }

    /**
     * Callback function that unloads a cart item and thus frees
     * Used only in test.php for test purches.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $userid
     * @return bool
     */
    public static function unload_cartitem(string $component, string $area, int $itemid, int $userid = 0): bool {
        return true;
    }

    /**
     * Callback function that handles inscripiton after fee was paid.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $paymentid
     * @param int $userid
     * @return bool
     */
    public static function successful_checkout(string $component, string $area, int $itemid, int $paymentid, int $userid): bool {
        // TODO: Set booking_answer to 1.
        return true;
    }

    /**
     * Callback function that handles cancelation after purchase.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return bool
     */
    public static function cancel_purchase(string $component, string $area, int $itemid, int $userid = 0): bool {
        return true;
    }
}
