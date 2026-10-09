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

use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\local\checkout_process\items\addresses;
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
     * Request cache of single addresses, keyed by address id (false for missing ones).
     * @var array
     */
    private static $addresscache = [];

    /**
     * Request cache of all addresses of a user, keyed by user id.
     * @var array
     */
    private static $useraddresscache = [];

    /**
     * Forgets everything read from the address table in this request.
     *
     * Called after every write, so the same request never serves an address
     * that was just deleted or the old data of an address that was just edited.
     *
     * @return void
     */
    public static function reset_request_cache(): void {
        self::$addresscache = [];
        self::$useraddresscache = [];
    }

    /**
     * Saves a new Address in the database for the current $USER.
     *
     * @param object $address the already validated address data from the form
     * @return int the id of the newly created address
     */
    public static function add_address_for_user(object $address): int {
        global $DB, $USER;
        $address->address2 = $address->address2 ?? '';
        $address->phone = $address->phone ?? '';
        $address->company = $address->company ?? '';
        $address->name = $address->name ?? '';
        $address->userid = $USER->id;
        $id = $DB->insert_record('local_shopping_cart_address', $address, true);
        self::reset_request_cache();
        return $id;
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
        $address->company = $address->company ?? '';

        // Update the record in the database.
        $result = $DB->update_record('local_shopping_cart_address', $address);
        self::reset_request_cache();

        // The country of the selected billing address decides the tax country.
        self::sync_checkout_caches((int)$USER->id);

        return $result;
    }

    /**
     * Deletes an address and forgets it in the checkout caches of its owner.
     *
     * @param int $addressid
     * @return bool false when the address does not exist (any more)
     */
    public static function delete_user_address(int $addressid): bool {
        global $DB;

        $address = $DB->get_record('local_shopping_cart_address', ['id' => $addressid], 'id, userid', IGNORE_MISSING);
        if (!$address) {
            return false;
        }

        $DB->delete_records('local_shopping_cart_address', ['id' => $addressid]);
        self::reset_request_cache();

        // The owner may have selected this address in a running checkout. Both the
        // cartstore and the checkout manager cache keep its id, so they are cleaned
        // here, otherwise the checkout continues with a dangling address id.
        self::sync_checkout_caches((int)$address->userid);

        return true;
    }

    /**
     * Drops address ids that no longer resolve from the checkout caches of a user.
     *
     * The cartstore cache keeps the selected ids (address_billing, address_shipping)
     * and the tax country derived from the billing address, the checkout manager
     * cache keeps the selection of the addresses step together with its validity.
     * A selected address can disappear (deleted in the checkout, by a cashier or by
     * the privacy API) or change its country, so this re-derives both caches from the
     * database. Only writes when something actually changed.
     *
     * The checkout manager cache lives in the session, so for another user's
     * checkout only the cartstore part can be repaired here; the owner's next page
     * load runs this again in her own session.
     *
     * @param int $userid
     * @return bool true when a selected address was dropped
     */
    public static function sync_checkout_caches(int $userid): bool {
        if (empty($userid)) {
            return false;
        }

        $resolves = function ($addressid): bool {
            return !empty($addressid) && is_numeric($addressid)
                && self::get_specific_user_address((int)$addressid) !== false;
        };

        $dropped = false;

        // Cartstore cache: selected ids and the tax country of the billing address.
        $cartstore = cartstore::instance($userid);
        $cartdata = $cartstore->get_cache();
        $cartchanged = false;
        foreach (['address_billing', 'address_shipping'] as $key) {
            if (isset($cartdata[$key]) && !$resolves($cartdata[$key])) {
                unset($cartdata[$key]);
                $cartchanged = true;
                $dropped = true;
                if ($key === 'address_billing') {
                    // The tax country was derived from this address.
                    $cartdata['taxcountrycode'] = null;
                }
            }
        }
        if (isset($cartdata['address_billing'])) {
            // The billing address may have been edited: keep the tax country in line with it.
            $taxcountrycode = self::get_specific_user_address((int)$cartdata['address_billing'])->state;
            if (($cartdata['taxcountrycode'] ?? null) !== $taxcountrycode) {
                $cartdata['taxcountrycode'] = $taxcountrycode;
                $cartchanged = true;
            }
        }
        if ($cartchanged) {
            $cartstore->set_cache($cartdata);
        }

        // Checkout manager cache: selection and validity of the addresses step.
        $managercache = checkout_manager::get_cache($userid);
        $stepdata = $managercache['steps']['addresses']['data'] ?? null;
        if (is_array($stepdata)) {
            $stepchanged = false;
            foreach ($stepdata as $key => $value) {
                if (strpos($key, 'selectedaddress_') === 0 && !empty($value) && !$resolves($value)) {
                    unset($stepdata[$key]);
                    $stepchanged = true;
                    $dropped = true;
                }
            }
            if ($stepchanged) {
                $item = new addresses($userid);
                $managercache['steps']['addresses'] = $item->evaluate_step($stepdata);
                if (!$managercache['steps']['addresses']['valid']) {
                    $managercache['checkout_validation'] = false;
                    $managercache['feedback'] = ['errormessage' => addresses::get_error_feedback()];
                }
                checkout_manager::write_cache($userid, $managercache);
            }
        }

        return $dropped;
    }

    /**
     * Return the database object for a specific address ID, or false if the
     * address no longer exists.
     *
     * A selected address may have been deleted while its id is still referenced
     * in the checkout cache, so this uses IGNORE_MISSING and returns false
     * instead of throwing. Callers handle a false result.
     *
     * @param int $addressid
     * @return \stdClass|false
     */
    public static function get_specific_user_address(int $addressid) {
        global $DB;

        // Check if the specific address ID is already in the request cache.
        if (array_key_exists($addressid, self::$addresscache)) {
            // Return the cached result immediately.
            return self::$addresscache[$addressid];
        }
        // If not in cache, execute the database query.
        $record = $DB->get_record('local_shopping_cart_address', ['id' => $addressid], '*', IGNORE_MISSING);
        // Save the result to the cache for future calls within this request.
        self::$addresscache[$addressid] = $record;
        return $record;
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     * @param int $userid
     * @return array
     */
    public static function get_all_user_addresses(int $userid): array {
        global $DB;

        // 1. Check if the specific user ID is already in the request cache.
        if (isset(self::$useraddresscache[$userid])) {
            // Return the cached result immediately.
            return self::$useraddresscache[$userid];
        }

        // 2. If not in cache, execute the database query.
        $records = $DB->get_records('local_shopping_cart_address', ['userid' => $userid]);

        // 3. Save the result to the cache for future calls within this request.
        // get_records returns an object array, which is cast to an array by the function signature.
        self::$useraddresscache[$userid] = $records;

        // 4. Return the result.
        return $records;
    }
}
