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

use local_shopping_cart\local\cartstore;
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
    public static function is_active(): bool {
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
    public static function get_icon_progress_bar(): string {
        return 'fa-solid fa-file-signature';
    }

    /**
     * Renders checkout item.
     *
     * @return bool
     */
    public static function is_mandatory(): bool {
        return true;
    }

    /**
     * Renders checkout item.
     *
     * @param mixed $cachedata
     *
     * @return array
     *
     */
    public static function render_body($cachedata): array {
        global $PAGE;

        $data = [];

        // Add data from cache.
        self::set_data_from_cache($data, $cachedata['data'] ?? []);

        $termsandconditions = get_config('local_shopping_cart', 'termsandconditions');
        $additionalconditions = get_config('local_shopping_cart', 'additionalconditions');

        // Correctly set terms and conditions.
        if (
            get_config('local_shopping_cart', 'accepttermsandconditions')
            && !empty(trim(strip_tags($termsandconditions)))
        ) {
            $data['termsandconditions'] = $termsandconditions;
        } else {
            unset($data['termsandconditions']);
        }

        // Correctly set additional conditions.
        if (
            get_config('local_shopping_cart', 'acceptadditionalconditions')
            && !empty(trim(strip_tags($additionalconditions)))
        ) {
            $data['additionalconditions'] = $additionalconditions;
        } else {
            unset($data['additionalconditions']);
        }

        $template = $PAGE->get_renderer('local_shopping_cart')
            ->render_from_template("local_shopping_cart/termsandconditions", $data);
        return [
            'template' => $template,
        ];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @param mixed $managercachestep
     * @param mixed $validationdata
     *
     * @return array
     *
     */
    public static function check_status(
        $managercachestep,
        $validationdata
    ): array {
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
     *
     * @param array $validationdata
     * @return bool list of all required address keys
     */
    public static function is_valid($validationdata): bool {
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
     *
     * @return void
     */
    public static function set_data_from_cache(&$termsandconditions, $cachedata): void {
        $termsandconditions = array_merge($termsandconditions, $cachedata ?? []);
    }

    /**
     * Validation feedback.
     * @return string
     */
    public function get_info_feedback(): string {

        global $USER;
        $carstore = cartstore::instance($USER->id);

        if (!$carstore->has_items()) {
            return get_string('cartisempty', 'local_shopping_cart');
        }

        $requiredfields = [];
        if (!empty(get_config('local_shopping_cart', 'termsandconditions'))) {
            $firststring = get_string(
                'completeshoppingcartprecheckout',
                'local_shopping_cart',
                get_string('confirmterms', 'local_shopping_cart')
            );
            $requiredfields[] = $firststring;
        }

        if (!empty(get_config('local_shopping_cart', 'additionalconditions'))) {
            $secondstring = get_string(
                'completeshoppingcartprecheckout',
                'local_shopping_cart',
                get_string('confirmadditionalconditions', 'local_shopping_cart')
            );
            if ($secondstring != ($firststring ?? '')) {
                $requiredfields[] = $secondstring;
            }
        }
        if (empty($requiredfields)) {
            return '';
        }
        return implode('<br>', $requiredfields);
    }
}
