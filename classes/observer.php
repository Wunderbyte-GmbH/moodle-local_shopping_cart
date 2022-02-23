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
 * Event observers used in forum.
 *
 * @package    local_shopping_cart
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Event observer for local_shopping_cart.
 */
class local_shopping_cart_observer {

    /**
     * Triggered via add_item event.
     *
     * @param \local_shopping_cart\event\item_added $event
     */
    public static function item_added(\local_shopping_cart\event\item_added $event): string {
        $b = $event;
        $a = "item added";
        return $a;
    }

    /**
     * Triggered via item_deleted event.
     *
     * @param \local_shopping_cart\event\item_deleted $event
     */
    public static function item_deleted(\local_shopping_cart\event\item_deleted $event): string {
        $b = $event;
        $a = "item deleted";
        return $a;
    }
}
