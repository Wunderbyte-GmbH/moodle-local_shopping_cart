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
 * @author      Thomas Winkler
 * @copyright   2021 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use coding_exception;
use context_system;
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

        $mform->addElement('header',
            'sch_shoppingcartheader',
            '<i class="fa fa-fw fa-shopping-cart" aria-hidden="true"></i>&nbsp;' . get_string('pluginname', 'local_shopping_cart'));
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

        $select = [
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
        ];

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
    }

    /**
     * Validate form input.
     * @param array $data
     * @param array $errors
     * @return void
     */
    public function validation(array $data, array &$errors) {
        // Validate.

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

        if (!empty($formdata->sch_allowinstallment)) {
            $this->add_key_to_jsonobject('allowinstallment', $formdata->sch_allowinstallment);
            $this->add_key_to_jsonobject('downpayment', $formdata->sch_downpayment);
            $this->add_key_to_jsonobject('numberofpayments', $formdata->sch_numberofpayments);
            $this->add_key_to_jsonobject('duedatevariable', $formdata->sch_duedatevariable);

            $this->save_iteminfo();
        }
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

        $formdata->sch_allowinstallment = $jsonobject->allowinstallment;
        $formdata->sch_downpayment = $jsonobject->downpayment;
        $formdata->sch_numberofpayments = $jsonobject->numberofpayments;
        $formdata->sch_duedatevariable = $jsonobject->duedatevariable;

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
