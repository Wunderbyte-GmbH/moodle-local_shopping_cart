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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\items_helper;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class address_operations {
    /**
     * Saves a new Address in the database for the current $USER.
     *
     * @param object $address the already validated address data from the form
     * @return int the id of the newly created address
     */
    public static function add_address_for_user(object $address): int {
        global $DB, $USER;
        $address->address2 = $record->address2 ?? '';
        $address->phone = $record->phone ?? '';
        $address->userid = $USER->id;
        return $DB->insert_record('local_shopping_cart_address', $address, true);
    }

    /**
     * Function to return an array of localized country codes.
     * @param int $addressid
     * @return bool
     */
    public static function delete_user_address(int $addressid) {
        global $DB;
        return $DB->delete_records('local_shopping_cart_address', ['id' => $addressid]);
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     * @param int $addressid
     * @return mixed
     */
    public static function get_specific_user_address(int $addressid): object {
        global $DB;
        $sql = "SELECT *
                FROM {local_shopping_cart_address}
                WHERE id = :addressid
                ORDER BY id DESC";

        $params = ['addressid' => $addressid];
        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     * @param int $userid
     * @return array
     */
    public static function get_all_user_addresses(int $userid): array {
        global $DB;
        $sql = "SELECT *
                FROM {local_shopping_cart_address}
                WHERE userid = :userid
                ORDER BY id DESC";

        $params = ['userid' => $userid];
        return $DB->get_records_sql($sql, $params);
    }
}
