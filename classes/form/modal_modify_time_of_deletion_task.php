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

use context;
use context_system;
use core_form\dynamic_form;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Dynamic optiondate form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_shopping_cart
 * @author Magdalena Holczik
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_modify_time_of_deletion_task extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        global $USER;

        $mform = $this->_form;

        // The userid ist -1 if we are on cashier site.

        if ($this->_ajaxformdata["userid"] == -1) {
            $userid = shopping_cart::return_buy_for_userid();
        } else if ($this->_ajaxformdata["userid"] == 0) {
            $userid = $USER->id;
        } else {
            $userid = $this->_ajaxformdata["userid"];
        }
        $currentlang = current_language();

        $this->_ajaxformdata["userid"] = $userid;

        $mform->addElement('hidden', 'userid', $userid);
        $mform->addElement('hidden', 'currentlang', $currentlang);

        $mform->addElement('date_time_selector', 'taskdeletiontimestamp', get_string('reservationuntil', 'local_shopping_cart'));
        $mform->setType('taskdeletiontimestamp', PARAM_INT);

        $mform->addElement('static', 'bodytext', '', get_string('appliedtoallitemsincart', 'local_shopping_cart'));
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

        global $USER;

        $data = $this->get_data();

        $userid = empty($data->userid)
            ? $USER->id : $data->userid;

        shopping_cart::add_or_reschedule_addhoc_tasks($data->taskdeletiontimestamp, $userid);

        // We need to set these items permanently.
        $cartstore = cartstore::instance($userid);
        $cartstore->save_cart_to_db();

        return $data;
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
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     *
     * Example:
     *     $this->set_data(get_entity($this->_ajaxformdata['cmid']));
     */
    public function set_data_for_dynamic_submission(): void {
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
     * Validate form.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];

        // This should never happen.
        if (!isset($data['taskdeletiontimestamp']) || !is_numeric($data['taskdeletiontimestamp'])) {
            $errors['taskdeletiontimestamp'] = get_string('somethingwentwrong', 'local_wunderbyte_table');
        }
        // Needs to be in the future.
        if ((int) $data['taskdeletiontimestamp'] <= time()) {
            $errors['taskdeletiontimestamp'] = get_string('choosehighertimestamp', 'local_shopping_cart');
        }

        return $errors;
    }

    /**
     * {@inheritDoc}
     * @see moodleform::get_data()
     */
    public function get_data() {
        $data = parent::get_data();
        return $data;
    }
}
