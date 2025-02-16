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
use local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper;
use moodle_exception;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnrchecker extends checkout_base_item {
    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_active(): bool {
        if (
            get_config('local_shopping_cart', 'showvatnrchecker')
            && !empty(get_config('local_shopping_cart', 'owncountrycode'))
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
        return 'fa-solid fa-file-invoice';
    }

    /**
     * Render body.
     *
     * @param mixed $cachedata
     *
     * @return array
     *
     */
    public function render_body($cachedata): array {
        global $PAGE;
        $data = [];
        $data['countries'] = self::get_country_code_name();
        self::set_data_from_cache($data, $cachedata['data'] ?? []);
        $template = $PAGE->get_renderer('local_shopping_cart')
            ->render_from_template("local_shopping_cart/vatnrchecker", $data);
        return [
            'template' => $template,
        ];
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     * @param array $vatnrcheckerdata
     * @param array $cachedata
     *
     * @return void
     */
    public static function set_data_from_cache(&$vatnrcheckerdata, $cachedata): void {
        $cacheddata = self::get_input_data($cachedata);
        self::set_cached_selected_country($vatnrcheckerdata, $cacheddata['country']);
        $vatnrcheckerdata['vatnumber'] = $cacheddata['vatnumber'];
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     * @param array $vatnrcheckerdata
     * @param string $countrycode
     *
     * @return void
     */
    public static function set_cached_selected_country(&$vatnrcheckerdata, $countrycode): void {

        foreach ($vatnrcheckerdata['countries'] as &$country) {
            if ($country['code'] == $countrycode) {
                $country['selected'] = true;
            } else {
                unset($country['selected']);
            }
        }
    }

    /**
     * Renders checkout item.
     * @return array
     */
    public static function get_country_code_name(): array {
        $countries = vatnumberhelper::get_countrycodes_array();

        $formattedcountrycodes = [];
        foreach ($countries as $code => $name) {
            $formattedcountrycodes[] = [
                'code' => $code,
                'name' => $name,
            ];
        }
        return $formattedcountrycodes;
    }

    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_mandatory(): bool {
        if (get_config('local_shopping_cart', 'onlywithvatnrnumber')) {
            return true;
        }
        return false;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @param mixed $managercachestep
     * @param mixed $changedinput
     *
     * @return array
     *
     */
    public function check_status(
        $managercachestep,
        $changedinput
    ): array {
        $data = $changedinput ?? [];
        $vatnumbercheck = false;
        try {
            $changedinput = self::get_input_data($changedinput);
            if (isset($changedinput['country']) && isset($changedinput['vatnumber'])) {
                $vatnumbercheck = vatnumberhelper::check_vatnr_number(
                    $changedinput['country'],
                    $changedinput['vatnumber']
                );

                $cartstore = cartstore::instance($this->identifier);
                if ($vatnumbercheck) {
                    $cartstore->set_vatnr_data($changedinput['country'], $changedinput['vatnumber'], '', '', '');
                } else if ($changedinput['country'] === "novatnr" || empty($changedinput['vatnumber'])) {
                    $cartstore->unset_vatnr_data();
                }
            }
        } catch (\Exception $e) {
            throw new moodle_exception(
                'wronginputvalue',
                'local_shopping_cart',
                '',
                null,
                $e->getMessage()
            );
        }
        return [
            'data' => $data,
            'mandatory' => self::is_mandatory(),
            'valid' => $vatnumbercheck,
        ];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @param mixed $changedinput
     *
     * @return array
     *
     */
    public static function get_input_data(
        $changedinput
    ): array {
        if (!is_array($changedinput)) {
            $changedinput = json_decode($changedinput);
        }
        $vatcodecountry = explode(',', $changedinput->vatCodeCountry ?? ',');
        [$countrycode, $vatnumber] = $vatcodecountry;
        return [
            'country' => $countrycode,
            'vatnumber' => $vatnumber,
        ];
    }

    /**
     * Validation feedback.
     * @return string
     */
    public static function get_validation_feedback(): string {
        return get_string('vatnrvalidationfeedback', 'local_shopping_cart');
    }

    /**
     * Validation feedback.
     * @return string
     */
    public static function get_error_feedback(): string {
        return get_string('vatnrerrorfeedback', 'local_shopping_cart');
    }
}
