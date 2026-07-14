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
     * Returns order number of form.
     * @return int
     */
    public static function get_ordernumber(): int {
        return 2;
    }

    /**
     * Renders checkout item.
     * @param array $changedinput
     * @param array $managercache
     * @return bool
     */
    public static function is_active($changedinput, $managercache): bool {
        if (
            get_config('local_shopping_cart', 'accepttermsandconditions') ||
            get_config('local_shopping_cart', 'acceptadditionalconditions')
        ) {
            return true;
        }
        return false;
    }

    /**
     * This step is implemented as dynamic form (pilot of the forms migration).
     * Remove this override to roll back to the legacy render_body/check_status path.
     *
     * @return string
     */
    public static function get_form_classname(): string {
        return \local_shopping_cart\local\checkout_process\steps\termsandconditions_form::class;
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
     * Returns the conditions the user has to accept. A checkbox is only active
     * when its accept-flag is on AND the conditions text is not empty.
     *
     * @return array fieldname => conditions text (HTML)
     */
    public static function get_active_conditions(): array {
        $conditions = [];

        $termsandconditions = get_config('local_shopping_cart', 'termsandconditions');
        if (
            get_config('local_shopping_cart', 'accepttermsandconditions')
            && !empty(trim(strip_tags((string)$termsandconditions)))
        ) {
            $conditions['accepttermsandconditions'] = $termsandconditions;
        }

        $additionalconditions = get_config('local_shopping_cart', 'additionalconditions');
        if (
            get_config('local_shopping_cart', 'acceptadditionalconditions')
            && !empty(trim(strip_tags((string)$additionalconditions)))
        ) {
            $conditions['acceptadditionalconditions'] = $additionalconditions;
        }

        return $conditions;
    }

    /**
     * Validation core of the terms step: the step is valid once every active
     * conditions checkbox is ticked. Used by both the termsandconditions_form
     * step and the legacy check_preprocess() path.
     *
     * @param array $data fieldname => bool (ticked)
     * @return array ['data' => array, 'mandatory' => bool, 'valid' => bool]
     */
    public function evaluate_step(array $data): array {
        $stepdata = [];
        $valid = true;
        foreach (array_keys(self::get_active_conditions()) as $fieldname) {
            $stepdata[$fieldname] = !empty($data[$fieldname]);
            $valid = $valid && $stepdata[$fieldname];
        }
        return [
            'data' => $stepdata,
            'mandatory' => self::is_mandatory(),
            'valid' => $valid,
        ];
    }

    /**
     * Adapts the legacy changedinput (JSON array of {name,value}) to the data
     * shape expected by evaluate_step().
     *
     * @param mixed $changedinput
     * @return array
     */
    public function parse_changed_input($changedinput): array {
        $decoded = is_array($changedinput) ? $changedinput : json_decode($changedinput);
        $data = [];
        foreach ((array)$decoded as $input) {
            if (isset($input->name)) {
                $data[$input->name] = $input->value ?? false;
            }
        }
        return $data;
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
