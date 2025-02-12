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
class termsandconditions extends checkout_base_item {
    /**
     * Renders checkout item.
     * @return bool
     */
    public function is_active() {
        if (
            get_config('local_shopping_cart', 'accepttermsandconditions') ||
            get_config('local_shopping_cart', 'acceptadditionalconditions')
        ) {
            return true;
        }
        return false;
    }

    /**
     * Checks status of checkout item.
     * @return string
     */
    public function get_icon_progress_bar() {
        return 'fa-solid fa-file-signature';
    }

    /**
     * Renders checkout item.
     */
    public function is_mandatory() {
        return true;
    }

    /**
     * Renders checkout item.
     * @return array
     */
    public function render_body($cachedata) {
        global $PAGE;

        $data = [];
        if (get_config('local_shopping_cart', 'accepttermsandconditions')) {
            $data['termsandconditions'] = get_config('local_shopping_cart', 'termsandconditions');
        }
        if (get_config('local_shopping_cart', 'acceptadditionalconditions')) {
            $data['additionalconditions'] = get_config('local_shopping_cart', 'additionalconditions');
        }
        self::set_data_from_cache($data, $cachedata['data']);
        $template = $PAGE->get_renderer('local_shopping_cart')
            ->render_from_template("local_shopping_cart/termsandconditions", $data);
        return [
            'template' => $template,
        ];
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
        $validationdata = json_decode($validationdata);
        $data = [];
        foreach ($validationdata as $validationvalue) {
            $data[$validationvalue->name] = $validationvalue->value;
        }
        return [
            'data' => $data,
            'mandatory' => self::is_mandatory(),
            'valid' => self::is_valid($validationdata),
        ];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     * @param array $validationdata
     * @return bool list of all required address keys
     */
    public function is_valid($validationdata) {
        foreach ($validationdata as $validationvalue) {
            if (
                !isset($validationvalue->value) ||
                $validationvalue->value == false
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     * @param array $termsandconditions
     * @param array $cachedata
     */
    public function set_data_from_cache(&$termsandconditions, $cachedata) {
        $termsandconditions = array_merge($termsandconditions, $cachedata ?? []);
    }
}
