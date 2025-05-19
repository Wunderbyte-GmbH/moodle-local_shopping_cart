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
 * This file contains the local_shopping_cart\local\callback\service_provider interface.
 *
 * Plugins should implement this if they use shopping_cart subsystem.
 *
 * @package local_shopping_cart
 * @copyright 2022 Georg Maißer Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\callback;

use local_shopping_cart\local\entities\cartitem;

/**
 * The service_provider interface for plugins to provide callbacks which are needed by the shopping_cart subsystem.
 *
 * @copyright  2022 Georg Maißer Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface service_provider {

    /**
     * Callback function that returns the price and description of the given item in the specified area
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $userid
     *
     * @return array
     */
    public static function load_cartitem(string $area, int $itemid, int $userid = 0): array;

    /**
     * Callback function that unloads an item from the cart.
     * This has to be implemented in case of limited ressources where...
     * ... one reserved item can't be booked by another user.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $userid
     *
     * @return array
     */
    public static function unload_cartitem(string $area, int $itemid, int $userid = 0): array;

    /**
     * Callback function that is executed when the item is successfully bought.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $paymentid payment id as inserted into the 'payments' table, if needed for reference
     * @param int $userid The userid the order is going to deliver to
     *
     * @return bool Whether successful or not
     */
    public static function successful_checkout(string $area, int $itemid, int $paymentid, int $userid): bool;

    /**
     * Callback function that cancels an already bought item.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $userid
     *
     * @return bool
     */
    public static function cancel_purchase(string $area, int $itemid, int $userid = 0): bool;

    /**
     * Callback function to give back a float value how much of the initially bought item is already consumed.
     * 1 stands for everything, 0.5 for 50%.
     * This is used in cancellation, to know how much of the initial price is returned.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $userid
     *
     * @return float
     */
    public static function quota_consumed(string $area, int $itemid, int $userid = 0): float;

    /**
     * Callback function to check if an item can be cancelled.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     *
     * @return bool true if cancelling is allowed, else false
     */
    public static function allowed_to_cancel(string $area, int $itemid): bool;

    /**
     * Callback to check if adding item to cart is allowed.
     *
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return array
     */
    public static function allow_add_item_to_cart(string $area, int $itemid,
        int $userid = 0): array;
}
