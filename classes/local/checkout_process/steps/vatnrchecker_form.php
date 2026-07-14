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
 * VAT-number checkout step as dynamic form.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\steps;

use local_shopping_cart\local\checkout_process\checkout_step_form;
use local_shopping_cart\local\checkout_process\items\vatnrchecker;
use local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper;
use stdClass;

/**
 * VAT-number checkout step as dynamic form.
 *
 * No auto-submit: the VIES check is an external call, so verification only
 * runs on the explicit submit ("Verify") button - like the legacy step.
 *
 * The cached step data keeps the legacy shape (a JSON string containing
 * vatCodeCountry as "COUNTRY,NUMBER"), because
 * checkout_manager::return_stored_vatnuber_country_code() and the tax
 * calculation consume exactly that format.
 *
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnrchecker_form extends checkout_step_form {
    /**
     * Cache key of this step in the checkout manager.
     *
     * @return string
     */
    public static function get_step_key(): string {
        return 'vatnrchecker';
    }

    /**
     * Mirrors the checkout item.
     *
     * @return bool
     */
    protected function is_step_mandatory(): bool {
        return vatnrchecker::is_mandatory();
    }

    /**
     * Country select, VAT number input and an explicit verify (submit) button.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'select',
            'vatcodecountry',
            get_string('checkvatnrcountrycode', 'local_shopping_cart'),
            vatnumberhelper::get_countrycodes_array()
        );

        $mform->addElement(
            'text',
            'vatnumber',
            get_string('checkvatnrnumber', 'local_shopping_cart'),
            ['placeholder' => get_string('usevatnr', 'local_shopping_cart')]
        );
        $mform->setType('vatnumber', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('verify', 'local_shopping_cart'));
    }

    /**
     * Prefill from the cached legacy-shaped step data.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $cached = static::get_cached_step_data();
        $defaults = ['vatcodecountry' => '', 'vatnumber' => ''];
        if (!empty($cached) && is_string($cached)) {
            $decoded = vatnrchecker::get_input_data($cached);
            $defaults['vatcodecountry'] = $decoded['country'] ?? '';
            $defaults['vatnumber'] = $decoded['vatnumber'] ?? '';
        }
        $this->set_data($defaults);
    }

    /**
     * Delegates to the shared item validation core (vatnrchecker::evaluate_step),
     * which verifies the VAT number against VIES and stores the result in the
     * cartstore.
     *
     * @param stdClass $data
     * @return array
     */
    protected function build_step_result(stdClass $data): array {
        global $USER;

        $item = new vatnrchecker((int)$USER->id);
        $result = $item->evaluate_step([
            'vatcodecountry' => $data->vatcodecountry ?? '',
            'vatnumber' => $data->vatnumber ?? '',
        ]);

        return [
            'data' => $result['data'],
            'valid' => $result['valid'],
        ];
    }
}
