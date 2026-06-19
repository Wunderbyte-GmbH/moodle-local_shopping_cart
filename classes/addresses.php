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

namespace local_shopping_cart;

use local_shopping_cart\local\checkout_process\items_helper\address_operations;

/**
 * Helper Class to handle addresses for the current user
 *
 * @package local_shopping_cart
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addresses {
    /**
     * Generates the data for rendering the templates/address.mustache template.
     *
     * The template expects the saved addresses nested inside each required
     * address entry, so we delegate to the checkout-process item which builds
     * exactly that structure (and is also guest-checkout aware).
     *
     * @return array all required template data
     */
    public static function get_template_render_data(): array {
        return \local_shopping_cart\local\checkout_process\items\addresses::get_template_render_data();
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     *
     * @return array list of all required addresses with a key and localized string
     */
    public static function get_required_address_data(): array {
        $requiredaddresseslocalized = [];
        $requiredaddresskeys = self::get_required_address_keys();
        // Insert localized string for required address types.
        foreach ($requiredaddresskeys as $addresstype) {
            $requiredaddresseslocalized[$addresstype] = [
                    "addresskey" => $addresstype,
                    "addresslabel" => get_string('addresses:' . $addresstype, 'local_shopping_cart'),
            ];
        }
        return $requiredaddresseslocalized;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return array list of all required address keys
     */
    public static function get_required_address_keys(): array {
        $addressesrequired = get_config('local_shopping_cart', 'addresses_required');
        $requiredaddresskeys = array_filter(explode(',', $addressesrequired));
        return $requiredaddresskeys;
    }

    /**
     * Returns a single address for the given user as a string representation
     *
     * @param int $userid id of the user
     * @param int $addressid id of the address
     * @return string|null the address in a single line string, or false if no matching address was found
     */
    public static function get_address_string_for_user(int $userid, int $addressid): ?string {
        $address = address_operations::get_specific_user_address($addressid);
        if ($address) {
            $countries = get_string_manager()->get_list_of_countries();
            return $address->address . trim(" " . $address->address2) . ", " . $address->zip . " " . $address->city . ", " .
                    $countries[$address->state];
        }
        return null;
    }

    /**
     * Retuens company name.
     * @param int $userid
     * @param int $addressid
     */
    public static function get_company_string_for_user(int $userid, int $addressid): ?string {
        $address = address_operations::get_specific_user_address($addressid);
        if ($address) {
            return $address->company;
        }
        return null;
    }
}
