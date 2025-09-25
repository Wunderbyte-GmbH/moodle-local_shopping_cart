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

use moodle_exception;

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
     * Updates an existing address in the database for the current $USER.
     *
     * @param int $addressid Id of the address in table
     * @param object $address The already validated address data from the form
     * @return bool Whether the update was successful
     */
    public static function update_address_for_user(int $addressid, object $address): bool {
        global $DB, $USER;

        // Check if the record exists for the given address ID and is owned by the current user.
        if (!$DB->record_exists('local_shopping_cart_address', ['id' => $addressid, 'userid' => $USER->id])) {
            throw new moodle_exception('Address does not exist or you do not have permission to update it.');
        }

        // Ensure the user ID is assigned to the address for ownership.
        $address->id = $addressid; // Make sure the ID is set for updating.
        $address->userid = $USER->id;

        // Handle optional fields with default values if not provided.
        $address->address2 = $address->address2 ?? '';
        $address->phone = $address->phone ?? '';

        // Update the record in the database.
        return $DB->update_record('local_shopping_cart_address', $address);
    }

    /**
     * Function to return an array of localized country codes.
     * @param int $addressid
     * @return bool
     */
    public static function delete_user_address(int $addressid): bool {
        global $DB;
        return $DB->delete_records('local_shopping_cart_address', ['id' => $addressid]);
    }

    /**
     * Return the database object for a specific address ID as stdClass.
     *
     * @param int $addressid
     * @return \stdClass
     */
    public static function get_specific_user_address(int $addressid): \stdClass {
        global $DB;

        // Define a static variable to hold the cache for all addresses queried in this request.
        static $addresscache = [];
        // Check if the specific address ID is already in the cache.
        if (isset($addresscache[$addressid])) {
            // Return the cached result immediately.
            return $addresscache[$addressid];
        }
        // If not in cache, execute the database query.
        $record = $DB->get_record('local_shopping_cart_address', ['id' => $addressid], '*', MUST_EXIST);
        // Save the result to the cache for future calls within this request.
        $addresscache[$addressid] = $record;
        return $record;
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     * @param int $userid
     * @return array
     */
    public static function get_all_user_addresses(int $userid): array {
        global $DB;

        // Define a static variable to hold the cache for all user addresses queried in this request.
        static $useraddresscache = [];

        // 1. Check if the specific user ID is already in the cache.
        if (isset($useraddresscache[$userid])) {
            // Return the cached result immediately.
            return $useraddresscache[$userid];
        }

        // 2. If not in cache, execute the database query.
        $records = $DB->get_records('local_shopping_cart_address', ['userid' => $userid]);

        // 3. Save the result to the cache for future calls within this request.
        // get_records returns an object array, which is cast to an array by the function signature.
        $useraddresscache[$userid] = $records;

        // 4. Return the result.
        return $records;
    }
}
