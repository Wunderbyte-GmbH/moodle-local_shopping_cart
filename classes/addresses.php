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
 * Entities Class to handle addresses for the current user
 *
 * @package local_shopping_cart
 * @author Maurice Wohlkönig
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use stdClass;

defined('MOODLE_INTERNAL') || die();

class addresses {
    private const DATABASE_TABLE = 'local_shopping_cart_address';

    /**
     * @return array all required template data to render the templates/address.mustace template
     */
    public static function get_template_render_data(): array {
        global $USER, $DB;
        $userid = $USER->id;
        $data["usermail"] = $USER->email;
        $data["username"] = $USER->firstname . $USER->lastname;
        $data["userid"] = $userid;

        // get saved addresses for current user
        $sql = "SELECT *
                FROM {local_shopping_cart_address}
                WHERE userid=:userid
                ORDER BY id DESC";

        $params = ['userid' => $userid];
        $addressesfromdb = $DB->get_records_sql($sql, $params);

        $countries = get_string_manager()->get_list_of_countries();
        $savedaddresses = [];
        foreach ($addressesfromdb as $dbaddress) {
            $dbaddress->country = $countries[$dbaddress->state];
            $savedaddresses[] = $dbaddress;
        }
        $data['saved_addresses'] = $savedaddresses;

        $requiredaddresseslocalized = self::get_required_address_data();
        $data['required_addresses'] = array_values($requiredaddresseslocalized);
        $data['required_addresses_keys'] = array_reduce($requiredaddresseslocalized, function($keys, $addressdata) {
            $keys[] = $addressdata['addresskey'];
            return $keys;
        }, []);
        $data['required_addresses_multiple'] = count($requiredaddresseslocalized) > 1;
        return $data;
    }

    /**
     * @return array list of all required addresses with a key and localized string
     */
    public static function get_required_address_data(): array {
        $requiredaddresseslocalized = [];
        $requiredaddresskeys = self::get_required_address_keys();
        // insert localized string for required address types
        foreach ($requiredaddresskeys as $addresstype) {
            $requiredaddresseslocalized[$addresstype] = [
                    "addresskey" => $addresstype,
                    "addresslabel" => get_string('addresses:' . $addresstype, 'local_shopping_cart')
            ];
        }
        return $requiredaddresseslocalized;
    }

    public static function get_required_address_keys(): array {
        $addressesrequired = get_config('local_shopping_cart', 'addresses_required');
        $requiredaddresskeys = explode(',', $addressesrequired);
        return $requiredaddresskeys;
    }

    /**
     * @param stdClass $address the already validated address data from the form
     * @return int the id of the newly created address
     */
    public static function add_address_for_user(stdClass $address): int {
        global $DB, $USER;

        $address->userid = $USER->id;

        return $DB->insert_record(self::DATABASE_TABLE, $address, true);
    }

    /**
     * @param int $userid id of the user
     * @param int $addressid id of the address
     * @return stdClass|false the address or false if no matching address was found
     */
    public static function get_address_for_user(int $userid, int $addressid): stdClass {
        global $DB;

        return $DB->get_record_select(self::DATABASE_TABLE, 'userid=? AND id=?', array($userid, $addressid));
    }

}