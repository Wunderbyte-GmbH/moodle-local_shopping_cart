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

namespace local_shopping_cart\local\checkout_process\items;

use local_shopping_cart\local\checkout_process\checkout_base_item;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addresses extends checkout_base_item {
    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_active() {
        if (get_config('local_shopping_cart', 'addresses_required')) {
            return true;
        }
        return false;
    }

    /**
     * Renders checkout item.
     * @return string
     */
    public static function get_icon_progress_bar() {
        return 'fa-solid fa-address-book';
    }

    /**
     * Renders checkout item.
     * @param array $cachedata
     * @return array
     */
    public static function render_body($cachedata) {
        global $PAGE;
        $data = self::get_template_render_data();
        $data['required_addresses'] = self::set_data_from_cache($data['required_addresses'], $cachedata['data']);
        $template = $PAGE->get_renderer('local_shopping_cart')
            ->render_from_template("local_shopping_cart/address", $data);
        return [
            'template' => $template,
        ];
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     * @param array $data
     * @param array $cachedata
     */
    public static function set_data_from_cache(&$requiredaddresses, $cachedata) {
        foreach ($requiredaddresses as &$requiredaddress) {
            $newsavedaddresses = [];
            foreach ($requiredaddress['saved_addresses'] as $savedaddress) {
                $savedaddresscopy = clone $savedaddress;
                if ($savedaddresscopy->id == $cachedata['selectedaddress_' . $requiredaddress['addresskey']]) {
                    $savedaddresscopy->selected = true;
                } else {
                    unset($savedaddresscopy->selected);
                }
                $newsavedaddresses[] = $savedaddresscopy;
            }
            $requiredaddress['saved_addresses'] = $newsavedaddresses;
        }
        return $requiredaddresses;
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     *
     * @return array all required template data
     */
    public static function get_template_render_data(): array {
        global $USER, $DB;
        $userid = $USER->id;
        $data["usermail"] = $USER->email;
        $data["username"] = $USER->firstname . $USER->lastname;
        $data["userid"] = $userid;

        // Get saved addresses for current user.
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

        $requiredaddresseslocalized = self::get_required_address_data();
        $data['required_addresses'] = array_values($requiredaddresseslocalized);
        foreach ($data['required_addresses'] as &$requiredaddress) {
            $requiredaddress['saved_addresses'] = $savedaddresses;
        }
        $data['required_addresses_keys'] = array_reduce($requiredaddresseslocalized, function ($keys, $addressdata) {
            $keys[] = $addressdata['addresskey'];
            return $keys;
        }, []);
        $data['required_addresses_multiple'] = count($requiredaddresseslocalized) > 1;
        return $data;
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
     * Renders checkout item.
     */
    public static function is_mandatory() {
        return true;
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
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return array list of all required address keys
     */
    public static function check_status(
        $managercachestep,
        $changedinput
    ) {
        $data = $managercachestep['data'];
        $requiredaddresskeys = self::get_required_address_keys();
        $changedinput = json_decode($changedinput);
        foreach ($requiredaddresskeys as $requiredaddresskey) {
            if (str_contains($changedinput->name, $requiredaddresskey)) {
                $data[$changedinput->name] = $changedinput->value;
            }
        }
        return [
            'data' => $data,
            'mandatory' => self::is_mandatory(),
            'valid' => self::is_valid($data, $requiredaddresskeys),
        ];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return bool list of all required address keys
     */
    public static function is_valid(
        $requiredaddresskeys,
        $data
    ) {
        $requiredkeys = count($requiredaddresskeys);
        $currentkeys = count($data);

        if ($requiredkeys == $currentkeys) {
            return true;
        }
        return false;
    }
}
