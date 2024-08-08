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
use local_shopping_cart\shopping_cart;
use moodle_url;
use stdClass;

/**
 * Dynamic optiondate form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package local_shopping_cart
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_cancel_addcredit extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;

        $cancelationfee = get_config('local_shopping_cart', 'cancelationfee');

        if (!$cancelationfee || $cancelationfee < 0) {
            $cancelationfee = 0;
        }

        $mform->addElement('hidden', 'price', $this->_ajaxformdata["price"]);
        $mform->addElement('hidden', 'historyid', $this->_ajaxformdata["historyid"]);
        $mform->addElement('hidden', 'itemid', $this->_ajaxformdata["itemid"]);
        $mform->addElement('hidden', 'userid', $this->_ajaxformdata["userid"]);
        $mform->addElement('hidden', 'currency', $this->_ajaxformdata["currency"]);
        $mform->addElement('hidden', 'componentname', $this->_ajaxformdata["componentname"]);
        $mform->addElement('hidden', 'area', $this->_ajaxformdata["area"]);

        if (get_config('local_shopping_cart', 'calculateconsumation')) {
            $consumed = (object)shopping_cart::get_quota_consumed(
                $this->_ajaxformdata["componentname"],
                $this->_ajaxformdata["area"],
                $this->_ajaxformdata["itemid"],
                $this->_ajaxformdata["userid"],
                $this->_ajaxformdata["historyid"],
            );
            $consumed->percentage = $consumed->quota * 100 . '%';
            $consumed->price = $consumed->initialprice;
            $consumed->credit = $consumed->remainingvalue;
        } else {
            $consumed = (object)[
                'quota' => 0,
                'remainingvalue' => $this->_ajaxformdata["price"],
                'currency' => $this->_ajaxformdata["currency"],
                'price' => $this->_ajaxformdata["price"],
                'cancelationfee' => $cancelationfee,

            ];
        }

        if (empty($consumed->quota)) {
            $remainingvalue = $this->_ajaxformdata["price"];
            $mform->addElement('static', 'bodytext', '',
                get_string('confirmcancelbody', 'local_shopping_cart', $consumed));
        } else if ($consumed->quota == 1) {
            $remainingvalue = 0;
            $cancelationfee = 0;
            $mform->addElement('static', 'bodytext', '',
                get_string('confirmcancelbodynocredit', 'local_shopping_cart', $consumed));
        } else {
            $remainingvalue = $consumed->remainingvalue;
            $mform->addElement('static', 'bodytext', '',
                get_string('confirmcancelbodyconsumption', 'local_shopping_cart', $consumed));
        }

        $mform->addElement('float', 'credittopayback', get_string('credittopayback', 'local_shopping_cart'));
        $mform->addElement('float', 'cancelationfee', get_string('cancelationfee', 'local_shopping_cart'));

        $mform->addElement('advcheckbox', 'applytocomponent', get_string('applytocomponent', 'local_shopping_cart'),
            get_string('applytocomponent_desc', 'local_shopping_cart'));

        $mform->setDefault('cancelationfee', $cancelationfee);
        $mform->setDefault('credittopayback', $remainingvalue);
        $mform->setDefault('applytocomponent', 1);
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

        // Set cancellation fee.
        $cancelationfee = $data->cancelationfee ?? 0;

        $credittopayback = $data->credittopayback ?? 0;

        if ($data->cancelationfee < 0) {
                $cancelationfee = 0;
        }

        if ($data->credittopayback < 0) {
            $credittopayback = 0;
        }

        $applytocomponent = $data->applytocomponent;

        // Subtract cancellation fee from credit to get credit for the user.
        $credit = $credittopayback - $cancelationfee;

        shopping_cart::cancel_purchase($data->itemid, $data->area, $data->userid, $data->componentname, $data->historyid,
            $credit, $cancelationfee, $applytocomponent);

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
     * Validate data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {

        $errors = [];

        if (isset($data["credittopayback"]) && $data["credittopayback"] < 0) {
            $errors["credittopayback"] = get_string('error:negativevaluenotallowed', 'local_shopping_cart');
        }

        if (isset($data["cancelationfee"]) && $data["cancelationfee"] < 0) {
            $errors["cancelationfee"] = get_string('error:negativevaluenotallowed', 'local_shopping_cart');
        }

        if (isset($data["credittopayback"]) && isset($data["cancelationfee"])
            && $data["cancelationfee"] > $data["credittopayback"]) {
            $errors["cancelationfee"] = get_string('error:cancelationfeetoohigh', 'local_shopping_cart');
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
