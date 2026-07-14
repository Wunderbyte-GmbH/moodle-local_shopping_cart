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
 * Addresses (and guest registration) checkout step as dynamic form.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\steps;

use local_shopping_cart\local\checkout_process\checkout_step_form;
use local_shopping_cart\local\checkout_process\items\addresses;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;
use local_shopping_cart\local\guestcheckout;
use MoodleQuickForm;
use stdClass;

/**
 * Addresses (and guest registration) checkout step as dynamic form.
 *
 * Contains the guest registration fields (when the current user is a guest
 * checkout user) and one address-card radio per saved address and required
 * address type. Validation runs in build_step_result() via the shared
 * addresses::evaluate_step() core - never in validation(), so an invalid
 * state always reaches the checkout cache (see blueprint §9).
 *
 * The guest login panel and the add/edit/delete address actions live in the
 * form surroundings (addresses::render_form_surroundings()), because they
 * contain their own form elements.
 *
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addresses_form extends checkout_step_form {
    /**
     * Cache key of this step in the checkout manager.
     *
     * @return string
     */
    public static function get_step_key(): string {
        return 'addresses';
    }

    /**
     * Mirrors the checkout item.
     *
     * @return bool
     */
    protected function is_step_mandatory(): bool {
        return addresses::is_mandatory();
    }

    /**
     * Radio selection and guest fields submit live, like the legacy step.
     *
     * @return bool
     */
    public static function is_autosubmit(): bool {
        return true;
    }

    /**
     * Registers the address-card element type once per request.
     *
     * @return void
     */
    protected static function register_element_types(): void {
        global $CFG;
        static $registered = false;
        if (!$registered) {
            MoodleQuickForm::registerElementType(
                'shoppingcartaddresscard',
                $CFG->dirroot . '/local/shopping_cart/classes/local/checkout_process/form_elements/addresscard.php',
                'local_shopping_cart_addresscard_element'
            );
            $registered = true;
        }
    }

    /**
     * Guest registration fields plus one card radio per saved address and
     * required address type.
     */
    public function definition() {
        global $USER;

        self::register_element_types();
        $mform = $this->_form;

        // Guest registration fields (the surrounding panel renders title/login).
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user((int)$USER->id)
        ) {
            $mform->addElement(
                'text',
                'guest_firstname',
                get_string('guestcheckout:firstname', 'local_shopping_cart'),
                ['placeholder' => get_string('guestcheckout:firstnameplaceholder', 'local_shopping_cart')]
            );
            $mform->setType('guest_firstname', PARAM_TEXT);

            $mform->addElement(
                'text',
                'guest_lastname',
                get_string('guestcheckout:lastname', 'local_shopping_cart'),
                ['placeholder' => get_string('guestcheckout:lastnameplaceholder', 'local_shopping_cart')]
            );
            $mform->setType('guest_lastname', PARAM_TEXT);

            $mform->addElement(
                'text',
                'guest_email',
                get_string('guestcheckout:email', 'local_shopping_cart'),
                ['placeholder' => get_string('guestcheckout:emailplaceholder', 'local_shopping_cart')]
            );
            $mform->setType('guest_email', PARAM_TEXT);
        }

        // One card per saved address, grouped per required address type.
        $addressesfromdb = address_operations::get_all_user_addresses((int)$USER->id);
        $countries = get_string_manager()->get_list_of_countries();

        foreach (addresses::get_required_address_data() as $requiredaddress) {
            $addresskey = $requiredaddress['addresskey'];
            $mform->addElement(
                'html',
                '<h5 class="mt-3 mb-2">'
                    . get_string('addresses:select', 'local_shopping_cart', $requiredaddress['addresslabel'])
                    . '</h5>'
            );
            foreach ($addressesfromdb as $address) {
                $card = clone $address;
                $card->country = $countries[$card->state] ?? $card->state;
                $mform->addElement(
                    'shoppingcartaddresscard',
                    'selectedaddress_' . $addresskey,
                    '',
                    '',
                    $card->id,
                    $card
                );
            }
        }
    }

    /**
     * Prefill selection and guest fields from the cached step data.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $cached = static::get_cached_step_data();
        $this->set_data(is_array($cached) ? $cached : []);
    }

    /**
     * Validate via the shared core (guest registration + address selection,
     * incl. cartstore persistence and error feedback collection).
     *
     * @param stdClass $data
     * @return array
     */
    protected function build_step_result(stdClass $data): array {
        global $USER;

        $stepdata = [];
        foreach ((array)$data as $key => $value) {
            if (strpos($key, 'selectedaddress_') === 0) {
                $stepdata[$key] = $value;
            }
            if (in_array($key, ['guest_firstname', 'guest_lastname', 'guest_email'], true)) {
                $stepdata[$key] = clean_param((string)$value, PARAM_TEXT);
            }
        }

        // Keep previously cached values (e.g. selection of an address type
        // whose radios are not part of this submission).
        $cached = static::get_cached_step_data();
        if (is_array($cached)) {
            $stepdata = array_merge($cached, $stepdata);
        }

        $item = new addresses((int)$USER->id);
        $result = $item->evaluate_step($stepdata);

        return [
            'data' => $result['data'],
            'valid' => (bool)$result['valid'],
        ];
    }
}
