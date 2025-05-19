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

namespace local_shopping_cart\form;

use local_shopping_cart\local\checkout_process\items_helper\address_operations;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

use context;
use context_system;
use core_form\dynamic_form;
use local_shopping_cart\addresses;
use moodle_url;
use stdClass;

/**
 * Dynamic new address form with edit functionality
 */
class modal_new_address extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        // Hidden ID field for editing.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Name.
        $attributes = ["placeholder" => get_string('addresses:newaddress:name:placeholder', 'local_shopping_cart')];
        $mform->addElement(
            'text',
            'name',
            get_string('addresses:newaddress:name:label', 'local_shopping_cart'),
            $attributes,
            $this->_ajaxformdata["name"] ?? ''
        );

        // Company Name.
        $attributes = ["placeholder" => get_string('addresses:newaddress:company:label', 'local_shopping_cart')];
        $mform->addElement(
                'text',
                'company',
                get_string('addresses:newaddress:company:label', 'local_shopping_cart'),
                $attributes,
                $this->_ajaxformdata["company"] ?? ''
        );

        // Country.
        $choices = ["" => ""] + get_string_manager()->get_list_of_countries();
        $options = [
            'multiple' => false,
            'placeholder' => get_string('addresses:newaddress:state:placeholder', 'local_shopping_cart'),
            'noselectionstring' => get_string('addresses:newaddress:state:choose', 'local_shopping_cart'),
        ];
        $mform->addElement(
            'autocomplete',
            'state',
            get_string('addresses:newaddress:state:label', 'local_shopping_cart'),
            $choices,
            $options
        );
        $mform->setAdvanced('state', true);

        // Address line 1.
        $options = [
            'placeholder' => get_string('addresses:newaddress:address:placeholder', 'local_shopping_cart'),
        ];
        $mform->addElement(
            'text',
            'address',
            get_string('addresses:newaddress:address:label', 'local_shopping_cart'),
            $options,
            $this->_ajaxformdata["address"] ?? ''
        );

        // Address line 2.
        $options = [
            'placeholder' => get_string('addresses:newaddress:address2:placeholder', 'local_shopping_cart'),
        ];
        $mform->addElement(
            'text',
            'address2',
            get_string('addresses:newaddress:address2:label', 'local_shopping_cart'),
            $options,
            $this->_ajaxformdata["address2"] ?? ''
        );

        // City.
        $options = [
            'placeholder' => get_string('addresses:newaddress:city:placeholder', 'local_shopping_cart'),
        ];
        $mform->addElement(
            'text',
            'city',
            get_string('addresses:newaddress:city:label', 'local_shopping_cart'),
            $options,
            $this->_ajaxformdata["city"] ?? ''
        );

        // Zip.
        $options = [
            'placeholder' => get_string('addresses:newaddress:zip:placeholder', 'local_shopping_cart'),
        ];
        $mform->addElement(
            'text',
            'zip',
            get_string('addresses:newaddress:zip:label', 'local_shopping_cart'),
            $options,
            $this->_ajaxformdata["zip"] ?? ''
        );
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $result = new stdClass();
        if (!empty($data->id)) {
            // Update existing address.
            address_operations::update_address_for_user($data->id, $data);
            $result->isnew = false;
        } else {
            // Add new address.
            $result->isnew = true;
            $newaddressid = address_operations::add_address_for_user($data);
        }
        $result->templatedata = addresses::get_template_render_data();
        $result->newaddressid = $data->id ?? $newaddressid;
        return $result;
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        if (!empty($this->_ajaxformdata['id'])) {
            // Edit mode: Load existing address data.
            $address = address_operations::get_specific_user_address($this->_ajaxformdata['id']);
            $this->set_data($address);
        } else {
            // New address mode: Use defaults.
            $data = new stdClass();
            global $USER;
            $data->name = fullname($USER);
            $data->state = $USER->country ?? '';
            $data->address = $USER->address ?? '';
            $data->address2 = $USER->address2 ?? '';
            $data->city = $USER->city ?? '';
            $data->zip = "";
            $data->phone = $USER->phone1 ?? '';
            $this->set_data($data);
        }
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
    }

    /**
     * Returns form context
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {

        return context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * If the form has arguments (such as 'id' of the element being edited), the URL should
     * also have respective argument.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url("/local/shopping_cart/address.php");
    }

    /**
     * Validate submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];
        $requiredfields = ["name", "state", "address", "city", "zip"];

        foreach ($requiredfields as $requiredfield) {
            if (empty(trim($data[$requiredfield]))) {
                $errors[$requiredfield] = get_string("addresses:newaddress:$requiredfield:error", 'local_shopping_cart');
            }
        }

        return $errors;
    }

    /**
     * Get data from form function
     *
     * @return stdClass
     */
    public function get_data() {
        $data = parent::get_data();
        return $data;
    }
}