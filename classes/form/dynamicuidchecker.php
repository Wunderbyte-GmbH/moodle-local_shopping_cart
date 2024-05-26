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
use local_shopping_cart\local\uidchecker;
use moodle_url;
use stdClass;

/**
 * Dynamic select users form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_shopping_cart
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamicuidchecker extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {

        $mform = $this->_form;

        $mform->addElement('static', 'enteruid', '', get_string('enteruid', 'local_shopping_cart'));
        $mform->addElement('advcheckbox', 'useuid', ' ', get_string('useuid', 'local_shopping_cart'));

        $options = uidchecker::return_countrycodes_array();
        $mform->addElement('select', 'checkuidcountrycode', get_string('checkuidcountrycode', 'local_shopping_cart'), $options);
        $mform->hideIf('checkuidcountrycode', 'useuid', 'neq', '1');
        $mform->addElement('text', 'checkuidnumber', get_string('checkuidnumber', 'local_shopping_cart'), '');
        $mform->hideIf('checkuidnumber', 'useuid', 'neq', '1');
        $mform->setType('checkuidnumber', PARAM_ALPHANUM);

        $mform->addElement('submit',
            'submitbutton',
            get_string('verify', 'local_shopping_cart'),
            ['class' => 'uidchecker-submitbutton']
        );
        $mform->hideIf('submitbutton', 'useuid', 'neq', '1');

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

        // If the user has entered a valid UID, we retrieve here the data fetched during validation.
        if (!empty(uidchecker::$uiddataset)) {

            // In order to have all the relevant data on our Invoice, we save this here.
            $address = uidchecker::$uiddataset->address;
            list($street, $city) = explode(PHP_EOL, $address);
            $data->name = uidchecker::$uiddataset->name;
            $data->street = $street;
            $data->city = $city;

            $cartstore = cartstore::instance($USER->id);
            $cartstore->set_uid_data(
                uidchecker::$uiddataset->countryCode,
                uidchecker::$uiddataset->vatNumber,
                uidchecker::$uiddataset->name,
                $street,
                $city);

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

        global $USER, $CFG;
        $data = new stdClass();

        $cartstore = cartstore::instance($USER->id);

        if ($cartstore->has_uid_data()) {
            $uiddata = $cartstore->get_uid_data();
        }

        $data->checkuidcountrycode = $uiddata['uidcountry'];
        $data->checkuidnumber = $uiddata['uidnumber'];

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
     * @param stdClass $data
     * @param array $files
     * @return void
     */
    public function validation($data, $files) {

        $errors = [];

        // If there actually is a UID number... we check online.
        if (!empty($data['useuid'])) {

            if ($data['checkuidcountrycode'] == 'nouid') {
                $errors['checkuidcountrycode'] = get_string('errorselectcountry', 'local_shopping_cart');
            } else {
                $response = uidchecker::check_uid_number(
                    $data['checkuidcountrycode'],
                    $data['checkuidnumber'],
                );

                $result = json_decode($response);

                if (!isset($result->valid) || !$result->valid) {
                    $a = $data['checkuidcountrycode'] . $data['checkuidnumber'];
                    $errors['checkuidnumber'] = get_string('errorinvaliduid', 'local_shopping_cart', $a);
                } else {
                    // $errors['checkuidnumber'] = $response;
                    uidchecker::$uiddataset = $result;
                }

            }
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
