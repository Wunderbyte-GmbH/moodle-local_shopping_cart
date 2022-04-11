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

use local_shopping_cart\shopping_cart_history;

/**
 * Event observer for local_shopping_cart.
 */
class local_shopping_cart_observer {

    /**
     * Triggered via payment_error event from any payment provider
     *
     * @param $event
     */
    public static function payment_error($event): string {

        // If we receive a payment error...
        // We check for the order id in our shopping cart history
        // And set it to error, if it was pending.

        $data = $event->get_data();

        // First check, to make it fast.
        if ($data['target'] !== 'payment') {
            return '';
        }

        // Next check.
        $stringarray = explode("\\", $data['eventname']);
        if (end($stringarray) !== 'payment_error') {
            return '';
        }

        // Next, we look in the shopping cart history if there is a pending payment.
        if (empty($data['other']['itemid'])) {
            return '';
        }

        $itemid = $data['other']['itemid'];

        shopping_cart_history::error_occured_for_identifier($itemid);

        return 'registered_payment_error';
    }

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
