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
use mod_booking\singleton_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

use context;
use context_system;
use core_form\dynamic_form;
use html_writer;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use mod_booking\booking_option;
use moodle_url;
use stdClass;

/**
 * Dynamic optiondate form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package local_shopping_cart
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_cancel_all_addcredit extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        $itemid = $this->_ajaxformdata['itemid'];
        $componentname = $this->_ajaxformdata['componentname'];
        $area = $this->_ajaxformdata['area'];

        if (!$bookedusers = shopping_cart_history::get_user_list_for_option($itemid, $componentname, $area)) {
            $bookedusers = [];
        }

        $data = new stdClass();

        $list = '';
        foreach ($bookedusers as $user) {
            $content = "$user->firstname $user->lastname $user->email, $user->price $user->currency";
            $list .= html_writer::tag('li', $content);
        }

        if (count($bookedusers) == 0) {
            $list = get_string('nousersfound', 'local_shopping_cart');
        }

        $content = html_writer::tag('ul', $list);
        $data->userlist = html_writer::tag('p', $content);

        $cancelationfee = get_config('local_shopping_cart', 'cancelationfee');

        if (!$cancelationfee || $cancelationfee < 0) {
            $cancelationfee = 0;
        }

        $mform->addElement('hidden', 'itemid', $itemid);
        $mform->setType('itemid', PARAM_TEXT);
        $mform->addElement('hidden', 'componentname', $componentname);
        $mform->setType('componentname', PARAM_TEXT);
        $mform->addElement('hidden', 'area', $area);
        $mform->setType('area', PARAM_TEXT);

        $mform->addElement('static', 'bodytext', '', get_string('confirmcancelallbody', 'local_shopping_cart', $data));

        $mform->addElement('float', 'cancelationfee', get_string('cancelationfee', 'local_shopping_cart'));
        $mform->setDefault('cancelationfee', $cancelationfee);
        $mform->setType('cancelationfee', PARAM_FLOAT);

    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/shopping_cart:cashier', $this->get_context_for_dynamic_submission());
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

        $bookedusers = shopping_cart_history::get_user_list_for_option($data->itemid, $data->componentname, $data->area);

        $cancelationfee = $data->cancelationfee ?? 0;

        if ($data->cancelationfee < 0) {
                $cancelationfee = 0;
        }

        $componentname = $data->componentname;
        $area = $data->area;

        foreach ($bookedusers as $buser) {

            $credit = $buser->price - $cancelationfee;

            // Negative credits are not allowed.
            if ($credit < 0.0) {
                $credit = 0.0;
            }

            shopping_cart::cancel_purchase($buser->itemid, $data->area, $buser->userid, $componentname,
                $buser->id, $credit, $cancelationfee);

        }

        // For the booking component, we have a special treatment here.
        if ($componentname === 'mod_booking'
            && $area === 'option') {
            $pluginmanager = \core_plugin_manager::instance();
            $plugins = $pluginmanager->get_plugins_of_type('mod');
            if (isset($plugins['booking'])) {
                booking_option::cancelbookingoption($data->itemid);
            }
        }

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
     * @return array
     */
    public function validation($data, $files) {

        $errors = [];

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
