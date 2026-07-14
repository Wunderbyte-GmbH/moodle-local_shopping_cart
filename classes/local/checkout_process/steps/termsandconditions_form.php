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
 * Terms-and-conditions checkout step as dynamic form.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\steps;

use local_shopping_cart\local\checkout_process\checkout_step_form;
use local_shopping_cart\local\checkout_process\items\termsandconditions;
use stdClass;

/**
 * Terms-and-conditions checkout step as dynamic form.
 *
 * The step is valid once every configured conditions checkbox is ticked.
 * An unticked checkbox is not a form error (the form always submits), it
 * just keeps the step - and thereby the checkout button - invalid.
 *
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class termsandconditions_form extends checkout_step_form {
    /**
     * Cache key of this step in the checkout manager.
     *
     * @return string
     */
    public static function get_step_key(): string {
        return 'termsandconditions';
    }

    /**
     * Mirrors the checkout item.
     *
     * @return bool
     */
    protected function is_step_mandatory(): bool {
        return termsandconditions::is_mandatory();
    }

    /**
     * Checkbox toggles submit live, like the legacy step.
     *
     * @return bool
     */
    public static function is_autosubmit(): bool {
        return true;
    }

    /**
     * Form definition: conditions text plus one accept checkbox each.
     *
     * No submit buttons - the checkout JS auto-submits on change to keep the
     * live behaviour of the legacy step.
     */
    public function definition() {
        $mform = $this->_form;

        $labels = [
            'accepttermsandconditions' => get_string('confirmterms', 'local_shopping_cart'),
            'acceptadditionalconditions' => get_string('confirmadditionalconditions', 'local_shopping_cart'),
        ];

        foreach (termsandconditions::get_active_conditions() as $fieldname => $conditionstext) {
            // The wrapper class keeps the markup contract of the legacy template.
            $mform->addElement(
                'html',
                '<div class="form_termsandconditions text-muted border p-2 bg-light rounded mb-2">'
                    . format_text($conditionstext, FORMAT_HTML)
                    . '</div>'
            );
            $mform->addElement('advcheckbox', $fieldname, '', $labels[$fieldname]);
        }
    }

    /**
     * Delegates to the shared item validation core
     * (termsandconditions::evaluate_step).
     *
     * @param stdClass $data
     * @return array
     */
    protected function build_step_result(stdClass $data): array {
        global $USER;

        $item = new termsandconditions((int)$USER->id);
        $result = $item->evaluate_step((array)$data);

        return [
            'data' => $result['data'],
            'valid' => $result['valid'],
        ];
    }
}
