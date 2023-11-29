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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

use context;
use context_system;
use core_form\dynamic_form;
use local_shopping_cart\shopping_cart_history;
use moodle_url;
use stdClass;

/**
 * Dynamic cash transfer form.
 * @copyright   Wunderbyte GmbH <info@wunderbyte.at>
 * @package     local_shopping_cart
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_cashtransfer extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {
        global $OUTPUT;

        $mform = $this->_form;

        $cashiertemplates[0] = '';
        $listofcashiers = get_users_by_capability(context_system::instance(), 'local/shopping_cart:cashier');
        foreach ($listofcashiers as $cashier) {
            $cashiertemplates[$cashier->id] = $OUTPUT->render_from_template(
                'local_shopping_cart/form-user-selector-suggestion', $cashier);
        }

        // From cashier...
        $options = [
            'multiple' => false,
            'noselectionstring' => get_string('choose...', 'local_shopping_cart'),
        ];
        $mform->addElement('autocomplete', 'cashtransfercashierfrom',
            get_string('cashtransfercashierfrom', 'local_shopping_cart'), $cashiertemplates, $options);
        $mform->addHelpButton('cashtransfercashierfrom', 'cashtransfercashierfrom', 'local_shopping_cart');

        // ...to cashier.
        $mform->addElement('autocomplete', 'cashtransfercashierto',
            get_string('cashtransfercashierto', 'local_shopping_cart'), $cashiertemplates, $options);
        $mform->addHelpButton('cashtransfercashierto', 'cashtransfercashierto', 'local_shopping_cart');

        $mform->addElement('float', 'cashtransferamount',
            get_string('cashtransferamount', 'local_shopping_cart'),
            get_string('cashtransferamount_desc', 'local_shopping_cart'));
        $mform->setDefault('cashtransferamount', 0.00);
        $mform->addHelpButton('cashtransferamount', 'cashtransferamount', 'local_shopping_cart');

        $mform->addElement('text', 'cashtransferreason',
            get_string('cashtransferreason', 'local_shopping_cart'),
            get_string('cashtransferreason_desc', 'local_shopping_cart'));

    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/shopping_cart:cashtransfer', $this->get_context_for_dynamic_submission());
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * Submission data can be accessed as: $this->get_data()
     *
     * @return mixed
     */
    public function process_dynamic_submission() {

        $data = $this->get_data();

        shopping_cart_history::create_entry_in_history(
            0, // Userid is 0, it doesn't concern a user.
            0, // Itemid is 0, it doesn't concern an item.
            get_string('cash', 'local_shopping_cart'),
            (-1) * (float)$data->cashtransferamount,
            0,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'cash',
            0,
            LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH,
            LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, null, 0, 0, null, null, null, null,
            $data->cashtransferreason,
            $data->cashtransfercashierfrom
        );

        shopping_cart_history::create_entry_in_history(
            0, // Userid is 0, it doesn't concern a user.
            0, // Itemid is 0, it doesn't concern an item.
            get_string('cash', 'local_shopping_cart'),
            $data->cashtransferamount,
            0,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'cash',
            0,
            LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH,
            LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, null, 0, 0, null, null, null, null,
            $data->cashtransferreason,
            $data->cashtransfercashierto
        );

        return $data;
    }


    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     *
     * Example:
     *     $this->set_data(get_entity($this->_ajaxformdata['cmid']));
     */
    public function set_data_for_dynamic_submission(): void {
        $data = new stdClass();
        $this->set_data($data);
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

        // We don't need it, as we only use it in modal.
        return new moodle_url('/');
    }

    /**
     * Validate dates.
     * @param stdClass $data
     * @param array $files
     * @return void
     */
    public function validation($data, $files) {

        $errors = [];

        if (empty($data['cashtransferamount']) || $data['cashtransferamount'] <= 0) {
            $errors['cashtransferamount'] = get_string('cashtransfernopositiveamount', 'local_shopping_cart');
        }

        if (empty($data['cashtransferreason'])) {
            $errors['cashtransferreason'] = get_string('cashtransferreasonnecessary', 'local_shopping_cart');
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
