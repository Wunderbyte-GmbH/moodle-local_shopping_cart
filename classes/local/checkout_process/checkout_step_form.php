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
 * Base class for checkout steps implemented as dynamic (AJAX) forms.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process;

use context;
use context_system;
use core_form\dynamic_form;
use moodle_url;
use stdClass;

/**
 * Base class for checkout steps implemented as dynamic forms.
 *
 * A step form replaces the legacy render_body()/check_status() contract of
 * checkout_base_item: rendering, data collection and per-field validation are
 * handled by the Moodle forms API. On every (auto-)submit the validated result
 * is written into the checkout-manager session cache and the response carries
 * the re-rendered button/progress partials in the same shape as the
 * control_checkout_process webservice, so the existing JS can update the page.
 *
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class checkout_step_form extends dynamic_form {
    /**
     * The step key, i.e. the short classname of the corresponding checkout
     * item (= the key used in the manager cache under 'steps').
     *
     * @return string
     */
    abstract public static function get_step_key(): string;

    /**
     * Whether this step is mandatory for checkout.
     *
     * @return bool
     */
    abstract protected function is_step_mandatory(): bool;

    /**
     * Whether the form auto-submits on every change (live updates like the
     * legacy steps) or only via its explicit submit button. Steps with
     * expensive submissions (e.g. external VIES calls) keep a submit button.
     *
     * @return bool
     */
    public static function is_autosubmit(): bool {
        return false;
    }

    /**
     * Build the step result from the submitted (already validated) form data.
     *
     * Per-field errors belong into validation(). This method decides the
     * step validity in the checkout sense: a step can be "not valid yet"
     * (e.g. checkbox not ticked) without being a form error.
     *
     * @param stdClass $data The submitted form data.
     * @return array ['data' => array, 'valid' => bool]
     */
    abstract protected function build_step_result(stdClass $data): array;

    /**
     * The checkout always runs in system context.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Guest checkout users are real (auto-created) users, so require_login suffices.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_login();
    }

    /**
     * Page url for the form.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/shopping_cart/checkout.php');
    }

    /**
     * Prefill the form from the cached step data.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(static::get_cached_step_data());
    }

    /**
     * Returns the cached step data of the current user.
     *
     * Note: legacy steps stored arbitrary shapes (the VAT step e.g. a JSON
     * string), so no return type is enforced here.
     *
     * @return mixed
     */
    protected static function get_cached_step_data() {
        global $USER;
        $managercache = checkout_manager::get_cache($USER->id);
        return $managercache['steps'][static::get_step_key()]['data'] ?? [];
    }

    /**
     * Persist the step result and return the partial-update payload for the JS.
     *
     * @return array Same shape as the control_checkout_process webservice response.
     */
    public function process_dynamic_submission() {
        global $USER;

        // Moodle returns null for an empty (but valid) submission, e.g. when
        // no radio is selected and the form has no other value elements.
        $data = $this->get_data() ?? new stdClass();
        $result = $this->build_step_result($data);

        return checkout_manager::store_form_step_result(
            (int)$USER->id,
            static::get_step_key(),
            [
                'data' => $result['data'],
                'valid' => (bool)$result['valid'],
                'mandatory' => $this->is_step_mandatory(),
            ]
        );
    }
}
