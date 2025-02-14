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
use local_shopping_cart\local\checkout_process\items_helper\address_operations;

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
    public function is_active() {
        if (get_config('local_shopping_cart', 'addresses_required')) {
            return true;
        }
        return false;
    }

    /**
     * Renders checkout item.
     * @return string
     */
    public function get_icon_progress_bar() {
        return 'fa-solid fa-address-book';
    }

    /**
     * Renders checkout item.
     * @param array $cachedata
     * @return array
     */
    public function render_body($cachedata) {
        global $PAGE;
        $data = self::get_template_render_data();
        $data['required_addresses'] = self::set_data_from_cache(
            $data['required_addresses'],
            $cachedata['data'] ?? []
        );
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
    public function set_data_from_cache(&$requiredaddresses, $cachedata) {
        foreach ($requiredaddresses as &$requiredaddress) {
            $newsavedaddresses = [];
            foreach ($requiredaddress['saved_addresses'] as $savedaddress) {
                $savedaddresscopy = clone $savedaddress;
                if (
                    $savedaddresscopy->id == ($cachedata['selectedaddress_' . $requiredaddress['addresskey']] ?? 0)
                ) {
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
     * @return object all required template data
     */
    public function get_template_render_data(): array {
        $data = self::get_user_data();
        $addressesfromdb = address_operations::get_all_user_addresses($data["userid"]);
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
    public function get_user_data(): array {
        global $USER;
        return [
            "usermail" => $USER->email,
            "username" => $USER->firstname . $USER->lastname,
            "userid" => $USER->id,
        ];
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     *
     * @return array list of all required addresses with a key and localized string
     */
    public function get_required_address_data(): array {
        $requiredaddresseslocalized = [];
        $requiredaddresskeys = self::get_required_address_keys();
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
    public function is_mandatory() {
        return true;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return array list of all required address keys
     */
    private function get_required_address_keys(): array {
        $addressesrequired = get_config('local_shopping_cart', 'addresses_required');
        $requiredaddresskeys = array_filter(explode(',', $addressesrequired));
        return $requiredaddresskeys;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return array list of all required address keys
     */
    public function check_status(
        $managercachestep,
        $validationdata
    ) {
        $data = $managercachestep['data'] ?? [];
        $requiredaddresskeys = self::get_required_address_keys();
        $validationdata = json_decode($validationdata);
        foreach ($requiredaddresskeys as $requiredaddresskey) {
            foreach ($validationdata as $address) {
                if (str_contains($address->name, $requiredaddresskey)) {
                    $data[$address->name] = $address->value;
                }
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
    private function is_valid(
        $requiredaddresskeys,
        $data
    ) {
        $requiredkeys = $requiredaddresskeys ? count($requiredaddresskeys) : null;
        $currentkeys = count($data);
        if (
            $requiredkeys === $currentkeys &&
            self::is_address_valid($requiredaddresskeys)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return bool list of all required address keys
     */
    private function is_address_valid(
        $requiredaddresskeys
    ) {
        $addressesfromdb = address_operations::get_all_user_addresses($this->identifier);
        foreach ($requiredaddresskeys as $requiredaddresskey) {
            if (!isset($addressesfromdb[$requiredaddresskey])) {
                return false;
            }
        }
        return true;
    }
}
