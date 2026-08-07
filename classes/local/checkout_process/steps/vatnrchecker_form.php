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

use local_shopping_cart\local\checkout_process\checkout_manager;
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
     * Static part of the form: the country select and a hidden no-submit button.
     *
     * Every country change presses the hidden button (change listener in
     * checkout_manager.js), which rebuilds the form server-side. All
     * country-dependent elements live in definition_after_data().
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'select',
            'vatcodecountry',
            get_string('checkvatnrcountrycode', 'local_shopping_cart'),
            vatnumberhelper::get_countrycodes_array()
        );

        // The label doubles as the submitted value of the button - it must not be
        // empty, otherwise no_submit_button_pressed() cannot detect the press.
        $mform->registerNoSubmitButton('vatcountryrebuild');
        $mform->addElement(
            'submit',
            'vatcountryrebuild',
            get_string('checkvatnrcountrycode', 'local_shopping_cart'),
            ['class' => 'd-none', 'tabindex' => '-1']
        );
        $mform->setType('vatcountryrebuild', PARAM_RAW);
    }

    /**
     * Country-dependent part of the form (rules-form pattern).
     *
     * Runs in both form lifecycles (render and submission), reacting to the
     * no-submit rebuild:
     *  - "No VAT number": neither number input nor verification are shown.
     *  - Non-European: number input without any verify button - no check exists
     *    and none is pretended; the optional number autosubmits on entry.
     *  - EU/GB: number input plus explicit verify button, so the external VIES
     *    lookup only runs on click.
     *
     * @return void
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        $country = $this->current_country();

        // A country change (no-submit press) persists the new state right away, like
        // other dynamic forms store their data during the rebuild: the non-European
        // selection simply becomes valid (check-free, number taken over as entered),
        // every other selection is reset until it is verified explicitly - a mere
        // country change never triggers a VIES call.
        if (!empty($this->_ajaxformdata['vatcountryrebuild'])) {
            global $USER;
            $item = new vatnrchecker((int)$USER->id);
            $result = $item->evaluate_step([
                'vatcodecountry' => $country,
                'vatnumber' => $country === vatnumberhelper::COUNTRY_NONEU
                    ? trim((string)($this->_ajaxformdata['vatnumber'] ?? ''))
                    : '',
            ]);
            checkout_manager::persist_form_step_result((int)$USER->id, static::get_step_key(), [
                'data' => $result['data'],
                'valid' => (bool)$result['valid'],
                'mandatory' => $this->is_step_mandatory(),
            ]);
        }

        if ($country === '' || $country === 'novatnr') {
            // No VAT number: nothing to enter and nothing to verify.
            return;
        }

        $attributes = ['placeholder' => get_string('usevatnr', 'local_shopping_cart')];
        if ($country === vatnumberhelper::COUNTRY_NONEU) {
            // The optional, check-free number is saved on entry (change listener in
            // checkout_manager.js), like the address step persists a selection.
            $attributes['data-vat-autosubmit'] = 1;
        }
        $mform->addElement(
            'text',
            'vatnumber',
            get_string('checkvatnrnumber', 'local_shopping_cart'),
            $attributes
        );
        $mform->setType('vatnumber', PARAM_TEXT);

        if ($country !== vatnumberhelper::COUNTRY_NONEU) {
            $this->add_action_buttons(false, get_string('verify', 'local_shopping_cart'));
        }
    }

    /**
     * The currently chosen country, covering both form lifecycles: the submitted
     * value during a rebuild or submission, and the value cached from the previous
     * step submission on the initial server-side pre-render.
     *
     * @return string
     */
    protected function current_country(): string {
        $country = (string)($this->_ajaxformdata['vatcodecountry'] ?? '');
        if ($country !== '') {
            return $country;
        }
        $cached = static::get_cached_step_data();
        if (is_string($cached) && $cached !== '') {
            return (string)(vatnrchecker::get_input_data($cached)['country'] ?? '');
        }
        return '';
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
