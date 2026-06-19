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

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnrchecker extends checkout_base_item {
    /**
     * Returns order number of form.
     * @return int
     */
    public static function get_ordernumber(): int {
        return 1;
    }

    /**
     * Renders checkout item.
     * @param mixed $changedinput
     * @param array $managercache
     * @return bool
     */
    public static function is_active($changedinput, $managercache): bool {
        if (
            self::show_vat_nr($changedinput, $managercache)
            && !empty(get_config('local_shopping_cart', 'owncountrycode'))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Checks status of checkout item.
     * @param mixed $changedinputs
     * @param array $managercache
     * @return string
     */
    public static function show_vat_nr($changedinputs, $managercache): string {
        $showvatnrchecker = get_config('local_shopping_cart', 'showvatnrchecker');
        $onlywithvatnrnumber = get_config('local_shopping_cart', 'onlywithvatnrnumber');
        if ($onlywithvatnrnumber) {
            return true;
        }
        if (!$showvatnrchecker) {
            return false;
        }
        // Only parse changedinput when it is actually provided (a JSON string,
        // e.g. when the voluntarily checkbox was just toggled). When there is no
        // changedinput (initial page load - it defaults to an array), fall
        // through to the cached value so a previously confirmed checkbox keeps
        // the VAT step active.
        if (is_string($changedinputs)) {
            $changedinputs = json_decode($changedinputs);
            if (!empty($changedinputs)) {
                foreach ($changedinputs as $changedinput) {
                    if (
                        isset($changedinput->name) &&
                        $changedinput->name == 'vatnumbervoluntarily'
                    ) {
                        return $changedinput->value;
                    }
                }
            }
        }
        return $managercache['vatnumbervoluntarily'] ?? false;
    }

    /**
     * This step is implemented as dynamic form (phase 2 of the forms migration).
     * Remove this override to roll back to the legacy render_body/check_status path.
     *
     * @return string
     */
    public static function get_form_classname(): string {
        return \local_shopping_cart\local\checkout_process\steps\vatnrchecker_form::class;
    }

    /**
     * Checks status of checkout item.
     * @return string
     */
    public static function get_icon_progress_bar(): string {
        return 'fa-solid fa-file-invoice';
    }

    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_mandatory(): bool {
        if (get_config('local_shopping_cart', 'onlywithvatnrnumber')) {
            return true;
        }
        // Ticking the voluntarily-VAT checkbox makes this step mandatory: the
        // user must then provide a valid VAT number before checkout is possible.
        if (!empty(self::$identifier)) {
            $cache = \cache::make('local_shopping_cart', 'cachebookingpreprocess');
            $managercache = $cache->get(self::$identifier);
            if (!empty($managercache['vatnumbervoluntarily'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parses the cached VAT step data (legacy "COUNTRY,NUMBER" shape) into
     * country/number. Still used by the vatnrchecker_form step to prefill.
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
     * Validation + persistence core of the VAT step. Verifies the VAT number
     * against VIES and stores the result in the cartstore (valid -> tax-relevant
     * VAT data set, invalid -> unset). Used by both the vatnrchecker_form step
     * and the legacy check_preprocess() path.
     *
     * @param array $data ['vatcodecountry' => code, 'vatnumber' => number]
     * @return array ['data' => string, 'mandatory' => bool, 'valid' => bool]
     */
    public function evaluate_step(array $data): array {
        $country = trim((string)($data['vatcodecountry'] ?? ''));
        $vatnumber = trim((string)($data['vatnumber'] ?? ''));

        $valid = false;
        if ($country !== '' && $country !== 'novatnr' && $vatnumber !== '') {
            $valid = vatnumberhelper::is_vatnr_valid($country, $vatnumber);
        }

        $cartstore = cartstore::instance((int)self::$identifier);
        if ($valid) {
            $cartstore->set_vatnr_data($country, $vatnumber, '', '', '');
        } else {
            $cartstore->unset_vatnr_data();
        }

        return [
            // Legacy cache shape, consumed by return_stored_vatnuber_country_code().
            'data' => json_encode(['vatCodeCountry' => $country . ',' . $vatnumber]),
            'mandatory' => self::is_mandatory(),
            'valid' => $valid,
        ];
    }

    /**
     * Adapts the legacy changedinput (JSON {"vatCodeCountry":"CC,NUM"}) to the
     * data shape expected by evaluate_step().
     *
     * @param mixed $changedinput
     * @return array
     */
    public function parse_changed_input($changedinput): array {
        $parsed = self::get_input_data($changedinput);
        return [
            'vatcodecountry' => $parsed['country'] ?? '',
            'vatnumber' => $parsed['vatnumber'] ?? '',
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
     *
     * With developer debugging enabled, the diagnostic trace of the last VAT
     * check (region detection, validator used, raw response) is appended.
     *
     * @return string
     */
    public static function get_error_feedback(): string {
        global $CFG;

        $errorkey = vatnumberhelper::get_last_validation_error_key();
        if (vatnumberhelper::last_failure_was_own_vatnr()) {
            $feedback = get_string('vatnrerrorownvatnr', 'local_shopping_cart');
        } else if (!empty($errorkey)) {
            // Technical error (service unavailable / rate limit) - tell the user
            // it is not their VAT number that is wrong.
            $feedback = get_string($errorkey, 'local_shopping_cart');
        } else {
            $feedback = get_string('vatnrerrorfeedback', 'local_shopping_cart');
        }

        if (!empty($CFG->debugdeveloper)) {
            $feedback .= vatnumberhelper::get_last_trace_html();
        }
        return $feedback;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     * @return bool
     *
     */
    public function get_info_feedback(): string {
        if (self::is_mandatory()) {
            return get_string('vatnrfeedbackmandatory', 'local_shopping_cart');
        }
        return get_string('vatnrfeedbackoptional', 'local_shopping_cart');
    }
}
