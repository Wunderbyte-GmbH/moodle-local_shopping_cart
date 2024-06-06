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
use local_shopping_cart\local\vatnrchecker;
use moodle_url;
use stdClass;

/**
 * Dynamic select users form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_shopping_cart
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamicvatnrchecker extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        global $USER;

        $mform = $this->_form;

        $mform->addElement('static', 'entervatnr', '', get_string('entervatnr', 'local_shopping_cart'));
        $mform->addElement('advcheckbox', 'usevatnr', ' ', get_string('usevatnr', 'local_shopping_cart'));

        $options = vatnrchecker::return_countrycodes_array();
        $mform->addElement('select', 'checkvatnrcountrycode', get_string('checkvatnrcountrycode', 'local_shopping_cart'), $options);
        $mform->hideIf('checkvatnrcountrycode', 'usevatnr', 'neq', '1');
        $mform->addElement('text', 'checkvatnrnumber', get_string('checkvatnrnumber', 'local_shopping_cart'), '');
        $mform->hideIf('checkvatnrnumber', 'usevatnr', 'neq', '1');
        $mform->setType('checkvatnrnumber', PARAM_ALPHANUM);

        $mform->addElement('submit',
            'submitbutton',
            get_string('verify', 'local_shopping_cart'),
            ['class' => 'vatnrchecker-submitbutton']
        );
        $mform->hideIf('submitbutton', 'usevatnr', 'neq', '1');

        $cartstore = cartstore::instance($USER->id);
        if ($cartstore->has_vatnr_data()) {
            $vatnrdata = $cartstore->get_vatnr_data();
            $mform->addElement('static',
                'printvatnrdata',
                '',
                $vatnrdata['companyname'] . "<br>"
                . $vatnrdata['street'] . "<br>"
                . $vatnrdata['place'] . "<br>");

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

        // If the user has entered a valid VATNR, we retrieve here the data fetched during validation.
        if (!empty(vatnrchecker::$vatnrdataset)) {

            $cartstore = cartstore::instance($USER->id);

            if (vatnrchecker::$vatnrdataset->vatNumber === false) {

                $cartstore->delete_vatnr_data();

            } else {
                // In order to have all the relevant data on our Invoice, we save this here.
                $address = vatnrchecker::$vatnrdataset->address;
                list($street, $city) = explode(PHP_EOL, $address);
                $data->name = vatnrchecker::$vatnrdataset->name;
                $data->street = $street;
                $data->city = $city;

                $cartstore->set_vatnr_data(
                    vatnrchecker::$vatnrdataset->countryCode,
                    vatnrchecker::$vatnrdataset->vatNumber,
                    vatnrchecker::$vatnrdataset->name,
                    $street,
                    $city);
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

        global $USER;

        $ajaxformdata = $this->_ajaxformdata;
        $mform = $this->_form;

        $data = new stdClass();

        $cartstore = cartstore::instance($USER->id);

        if ($cartstore->has_vatnr_data()) {
            $vatnrdata = $cartstore->get_vatnr_data();

            $data->usevatnr = 1;
            $data->checkvatnrcountrycode = $vatnrdata['vatnrcountry'];
            $data->checkvatnrnumber = $vatnrdata['vatnrnumber'];

        } else if (!empty($mform->getSubmitValue('checkvatnrnumber'))) {
            $data->usevatnr = 1;
            // phpcs:disable
            // $data->checkvatnrcountrycode = $mform->getSubmitValue('checkvatnrnumber');
            // $data->checkvatnrnumber = $mform->getSubmitValue('checkvatnrcountrycode');
            // phpcs:enable
        }

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
     * Validate form.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {

        $errors = [];

        // If there actually is a VATNR number... we check online.
        if (!empty($data['usevatnr'])) {

            if ($data['checkvatnrcountrycode'] == 'novatnr'
                && !empty($data['checkvatnrnumber'])) {
                $errors['checkvatnrcountrycode'] = get_string('errorselectcountry', 'local_shopping_cart');
            } else if ($data['checkvatnrcountrycode'] == 'novatnr'
                && empty($data['checkvatnrnumber'])) {

                vatnrchecker::$vatnrdataset = (object)[
                    'vatNumber' => false,
                    'countryCode' => false,
                ];
            } else {
                $response = vatnrchecker::check_vatnr_number(
                    $data['checkvatnrcountrycode'],
                    $data['checkvatnrnumber'],
                );

                $result = json_decode($response);

                if (!isset($result->valid) || !$result->valid) {
                    $a = $data['checkvatnrcountrycode'] . $data['checkvatnrnumber'];
                    $errors['checkvatnrnumber'] = get_string('errorinvalidvatnr', 'local_shopping_cart', $a);
                } else {
                    // phpcs:ignore
                    // $errors['checkvatnrnumber'] = $response;
                    vatnrchecker::$vatnrdataset = $result;
                }

            }
        } else {
            vatnrchecker::$vatnrdataset = (object)[
                'vatNumber' => false,
                'countryCode' => false,
            ];
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
