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
    /**
     * @return array all required template data to render the templates/address.mustace template
     */
    public static function get_template_render_data(): array {
        global $USER, $DB;
        $userid = $USER->id;
        $addressesrequired = get_config('local_shopping_cart', 'addresses_required');
        $data["usermail"] = $USER->email;
        $data["username"] = $USER->firstname . $USER->lastname;
        $data["userid"] = $userid;

        // get saved addresses for current user
        $sql = "SELECT *
                FROM {local_shopping_cart_address}
                WHERE userid=:userid
                ORDER BY id DESC
                LIMIT 1";

        $params = ['userid' => $userid];
        $savedaddresses = $DB->get_record_sql($sql, $params);

        $data['saved_addresses'] = $savedaddresses;

        // insert localized string for required address types
        $requiredaddresseslocalized = [];
        foreach (explode(',', $addressesrequired) as $addresstype) {
            $requiredaddresseslocalized[] = [
                    "addresskey" => $addresstype,
                    "requiredaddress" => get_string('addresses:' . $addresstype, 'local_shopping_cart')
            ];
        }
        $data['required_addresses'] = $requiredaddresseslocalized;
        return $data;
    }

    /**
     * @param stdClass $address the already validated address data from the form
     * @return int the id of the newly created address
     */
    public static function add_address_for_user(stdClass $address): int {
        global $DB, $USER;

        $address->userid = $USER->id;

        return $DB->insert_record('local_shopping_cart_address', $address, true);
    }
}