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
 * Shopping_cart_history class for local shopping cart.
 * @package     local_shopping_cart
 * @author      Georg Maißer
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use coding_exception;
use context_system;
use core_payment\helper;
use dml_exception;
use Exception;
use local_shopping_cart\event\payment_added;
use moodle_exception;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Class shopping_cart_handler.
 * @author      Georg Maißer
 * @copyright   2024 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_handler {

    /**
     * @var int
     */
    private $itemid;

    /**
     * @var string
     */
    private $componentname;

    /**
     * @var string
     */
    private $area;

    /**
     * @var stdClass
     */
    private $jsonobject;

    /**
     * Handler constructor
     * @param string $componentname
     * @param string $area
     * @param int $itemid
     * @return void
     */
    public function __construct(string $componentname, string $area, int $itemid = 0) {

        $this->componentname = $componentname ?? '';
        $this->area = $area ?? '';
        $this->itemid = $itemid ?? 0;

    }

    /**
     * Add the form elements to the mform.
     * @param MoodleQuickForm $mform
     * @param array $formdata
     * @return void
     * @throws coding_exception
     */
    public function definition(MoodleQuickForm &$mform, array $formdata) {

        if (get_config('local_shopping_cart', 'enableinstallments')
            || get_config('local_shopping_cart', 'allowrebooking')
            || get_config('local_shopping_cart', 'allowchooseaccount')) {
            $mform->addElement('header',
                'sch_shoppingcartheader',
                '<i class="fa fa-fw fa-shopping-cart" aria-hidden="true"></i>&nbsp;'
                . get_string('pluginname', 'local_shopping_cart'));
        }

        if (has_capability('local/shopping_cart:changepaymentaccount', context_system::instance())) {
            if (get_config('local_shopping_cart', 'allowchooseaccount')) {

                $paymentaccountrecords = helper::get_payment_accounts_to_manage(context_system::instance(), false);

                $paymentaccounts = [];
                foreach ($paymentaccountrecords as $paymentaccountrecord) {
                    $paymentaccounts[$paymentaccountrecord->get('id')] = $paymentaccountrecord->get('name');
                }

                if (!empty($paymentaccounts)) {
                    $mform->addElement(
                        'select',
                        'sch_paymentaccountid',
                        get_string('sch_paymentaccountid', 'local_shopping_cart'),
                        $paymentaccounts
                    );

                    if (!empty(get_config('local_shopping_cart', 'accountid'))) {
                        $mform->setDefault('sch_paymentaccountid', get_config('local_shopping_cart', 'accountid'));
                    }
                }
            }
        }

        // Fields for Rebooking.
        if (get_config('local_shopping_cart', 'allowrebooking')) {

            $mform->addElement(
                'advcheckbox',
                'sch_allowrebooking',
                get_string('allowrebooking', 'local_shopping_cart'),
                get_string('allowrebooking_desc', 'local_shopping_cart')
            );
        }

        // Fields for Installments.
        if (get_config('local_shopping_cart', 'enableinstallments')) {
            $mform->addElement(
                'advcheckbox',
                'sch_allowinstallment',
                get_string('allowinstallment', 'local_shopping_cart'),
                get_string('allowinstallment_help', 'local_shopping_cart')
            );

            $mform->addElement(
                'text',
                'sch_downpayment',
                get_string('downpayment', 'local_shopping_cart')
            );
            $mform->setType('sch_downpayment', PARAM_FLOAT);
            $mform->addHelpButton('sch_downpayment', 'downpayment', 'local_shopping_cart');
            $mform->hideIf('sch_downpayment', 'sch_allowinstallment', 'neq', "1");

            $i = 1;
            while ($i <= 30) {
                $select[$i] = $i;
                $i++;
            }
            $mform->addElement(
                'select',
                'sch_numberofpayments',
                get_string('numberofpayments', 'local_shopping_cart'),
                $select
            );
            $mform->setType('sch_numberofpayments', PARAM_INT);
            $mform->addHelpButton('sch_numberofpayments', 'numberofpayments', 'local_shopping_cart');
            $mform->hideIf('sch_numberofpayments', 'sch_allowinstallment', 'neq', "1");

            $options = [
                'startyear' => date('Y', time()),
                'stopyear'  => date('Y', strtotime('now + 1 year')),
                'timezone'  => 99,
            ];

            $mform->addElement(
                'text',
                'sch_duedatevariable',
                get_string('duedatevariable', 'local_shopping_cart')
            );
            $mform->setDefault('sch_duedatevariable', 0);
            $mform->setType('sch_duedatevariable', PARAM_INT);
            $mform->addHelpButton('sch_duedatevariable', 'duedatevariable', 'local_shopping_cart');
            $mform->hideIf('sch_duedatevariable', 'sch_allowinstallment', 'neq', "1");
            // phpcs:ignore
            // $mform->hideIf('sch_duedatevariable', 'sch_duedaysbeforecoursestart', 'neq', "0");

            $mform->addElement(
                'text',
                'sch_duedaysbeforecoursestart',
                get_string('duedaysbeforecoursestart', 'local_shopping_cart')
            );
            $mform->setDefault('sch_duedaysbeforecoursestart', 0);
            $mform->setType('sch_duedaysbeforecoursestart', PARAM_INT);
            $mform->addHelpButton('sch_duedaysbeforecoursestart', 'duedaysbeforecoursestart', 'local_shopping_cart');
            $mform->hideIf('sch_duedaysbeforecoursestart', 'sch_allowinstallment', 'neq', "1");
        }

    }

    /**
     * Validate form input.
     * @param array $data
     * @param array $errors
     * @return void
     */
    public function validation(array $data, array &$errors) {
        // Validate.

        if (!empty($data['sch_duedatevariable'])
            && !empty($data['sch_duedaysbeforecoursestart'])) {

            $errors['sch_duedatevariable'] = get_string('onlyone', 'local_shopping_cart');
            $errors['sch_duedaysbeforecoursestart'] = get_string('onlyone', 'local_shopping_cart');
        }

    }

    /**
     * To save data in table.
     * @param stdClass $formdata
     * @param stdClass $data
     * @return void
     */
    public function save_data(stdClass $formdata, stdClass $data) {

        $this->itemid = $formdata->id ?? $data->id ?? 0;

        if (empty($this->itemid)) {
            // We can't save anything if we have no itemid.
            throw new moodle_exception('noitemid', 'local_shoping_cart');
        }

        if (has_capability('local/shopping_cart:changepaymentaccount', context_system::instance())) {
            if (get_config('local_shopping_cart', 'allowchooseaccount')) {
                $paymentaccountid = $formdata->sch_paymentaccountid ?? get_config('local_shopping_cart', 'accountid');
                $this->add_key_to_jsonobject('paymentaccountid', $paymentaccountid);
            } else {
                $paymentaccountid = get_config('local_shopping_cart', 'accountid');
                $this->add_key_to_jsonobject('paymentaccountid', $paymentaccountid);
            }
        }

        if (isset($formdata->sch_allowinstallment)) {
            $this->add_key_to_jsonobject('allowinstallment', $formdata->sch_allowinstallment);
            $this->add_key_to_jsonobject('downpayment', $formdata->sch_downpayment);
            $this->add_key_to_jsonobject('numberofpayments', $formdata->sch_numberofpayments);

            // Make sure only one of these values can be not null.
            if (empty($formdata->sch_duedatevariable)) {
                $this->add_key_to_jsonobject('duedaysbeforecoursestart', $formdata->sch_duedaysbeforecoursestart ?? 0);
                $this->add_key_to_jsonobject('duedatevariable', 0);
            } else {
                $this->add_key_to_jsonobject('duedatevariable', $formdata->sch_duedatevariable ?? 0);
                $this->add_key_to_jsonobject('duedaysbeforecoursestart', 0);
            }
        }

        if (isset($formdata->sch_allowrebooking)) {

            $this->add_key_to_jsonobject('allowrebooking', $formdata->sch_allowrebooking);
        }

        $this->save_iteminfo();
    }

    /**
     * To set data for the form.
     * @param stdClass $formdata
     * @return void
     * @throws dml_exception
     */
    public function set_data(stdClass $formdata) {

        global $DB;

        $this->itemid = $formdata->id ?? 0;

        if (empty($this->itemid)) {
            // We can't save anything if we have no itemid.
            // Here we don't need an error. During creation, itemid can be 0.
            return;
        }
        $data = [
            'itemid' => $this->itemid,
            'componentname' => $this->componentname,
            'area' => $this->area,
        ];

        if (!$record = $DB->get_record('local_shopping_cart_iteminfo', $data)) {
            return;
        }

        $jsonobject = json_decode($record->json);

        if (!$jsonobject || empty($jsonobject)) {
            return;
        }

        $formdata->sch_paymentaccountid = $jsonobject->paymentaccountid ?? get_config('local_shopping_cart', 'accountid') ?? 0;
        $formdata->sch_allowinstallment = $jsonobject->allowinstallment ?? 0;
        $formdata->sch_downpayment = $jsonobject->downpayment ?? 0;
        $formdata->sch_numberofpayments = $jsonobject->numberofpayments ?? 0;
        $formdata->sch_duedatevariable = $jsonobject->duedatevariable ?? 0;
        $formdata->sch_duedaysbeforecoursestart = $jsonobject->duedaysbeforecoursestart ?? 0;

        // Rebooking.
        $formdata->sch_allowrebooking = $jsonobject->allowrebooking
            ?? get_config('local_shopping_cart', 'allowrebooking') ?: 0;

    }

    /**
     * Fetches the json object from db and adds the value.
     * This function does not save but only stores in the instance of the class.
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws dml_exception
     */
    private function add_key_to_jsonobject(string $key, $value) {

        global $DB;

        // Make sure the class has a current version of the json object.
        if ($this->jsonobject === null) {
            $data = [
                'itemid' => $this->itemid,
                'componentname' => $this->componentname,
                'area' => $this->area,
            ];

            if (!$record = $DB->get_record('local_shopping_cart_iteminfo', $data)) {
                $this->jsonobject = new stdClass();
            } else {
                $this->jsonobject = json_decode($record->json);
            }
        }

        if (!isset($this->jsonobject)) {
            $this->jsonobject = new stdClass();
        }

        $this->jsonobject->{$key} = $value;

    }


    /**
     * This function writes the current instance of the class to the db.
     * @return void
     * @throws dml_exception
     */
    private function save_iteminfo() {

        global $DB, $USER;

        $data = [
            'itemid' => $this->itemid,
            'componentname' => $this->componentname,
            'area' => $this->area,
        ];

        if (!$record = $DB->get_record('local_shopping_cart_iteminfo', $data)) {
            $data['allowinstallment'] = !empty($this->jsonobject->allowinstallment) ? 1 : 0;
            $data['json'] = json_encode($this->jsonobject);
            $data['timemodified'] = time();
            $data['usermodified'] = $USER->id;

            $data['allowrebooking'] = !empty($this->jsonobject->allowrebooking) ? 1 : 0;

            $DB->insert_record('local_shopping_cart_iteminfo', $data);
        } else {

            // We never save a totally empty jsonobject but assume that we then would want to keep the current.
            if (empty($this->jsonobject)) {
                $this->jsonobject = json_decode($record->json);
            }
            $data['id'] = $record->id;
            $data['allowinstallment'] = !empty($this->jsonobject->allowinstallment) ? 1 : 0;
            $data['json'] = json_encode($this->jsonobject);
            $data['timemodified'] = time();
            $data['timecreated'] = time();
            $data['usermodified'] = $USER->id;

            $data['allowrebooking'] = !empty($this->jsonobject->allowrebooking) ? 1 : 0;

            $DB->update_record('local_shopping_cart_iteminfo', $data);
        }
    }

    /**
     * Function to for fast check if there is a possibility for installments.
     * This still goes on the DB and can't be used for a list of items.
     * If it's needed on a list, we would need to make a cached version of this.
     * @param string $componentname
     * @param string $area
     * @param int $itemid
     * @return bool
     * @throws dml_exception
     */
    public static function installment_exists(string $componentname, string $area, int $itemid) {
        global $DB;

        $data = [
            'itemid' => $itemid,
            'componentname' => $componentname,
            'area' => $area,
            'allowinstallment' => 1,
        ];
        return $DB->record_exists('local_shopping_cart_iteminfo', $data);
    }
}
