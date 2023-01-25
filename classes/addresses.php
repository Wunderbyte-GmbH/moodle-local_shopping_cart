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

defined('MOODLE_INTERNAL') || die();

class addresses {
    public static function get_template_render_data(): array {
        global $USER;
        $addressesrequired = get_config('local_shopping_cart', 'addresses_required');
        $data["usermail"] = $USER->email;
        $data["username"] = $USER->firstname . $USER->lastname;
        $data["userid"] = $USER->id;

        $data['saved_addresses'] = [];

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
}