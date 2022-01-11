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
use local_shopping_cart\shopping_cart;

/**
 * The service_provider interface for plugins to provide callbacks which are needed by the shopping_cart subsystem.
 *
 * @copyright  2022 Georg Maißer Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface service_provider {

    /**
     * Callback function that returns the price and description of the given item in the specified area,
     * @param int $itemid An identifier that is known to the plugin
     * @return cartitem
     */
    public static function get_cartitem(int $itemid):cartitem;

    /**
     * Callback function that is executed when the item is successfully bought.
     * @param int $itemid An identifier that is known to the plugin
     * @param int $paymentid payment id as inserted into the 'payments' table, if needed for reference
     * @param int $userid The userid the order is going to deliver to
     *
     * @return bool Whether successful or not
     */
    public static function successful_checkout(int $itemid, int $paymentid, int $userid): bool;
}
