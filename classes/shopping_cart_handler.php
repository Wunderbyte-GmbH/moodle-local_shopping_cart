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

        $mform->addElement('header', 'sch_shoppingcartheader', get_string('pluginname', 'local_shopping_cart'));
        $mform->addElement(
            'advcheckbox',
            'sch_allowinstallment',
            get_string('allowinstallment', 'local_shopping_cart'),
            get_string('allowinstallment_help', 'local_shopping_cart')
        );

        $mform->addElement(
            'text',
            'sch_firstamount',
            get_string('firstamount', 'local_shopping_cart'),
            get_string('firstamount_desc', 'local_shopping_cart')
        );
        $mform->setType('sch_firstamount', PARAM_FLOAT);
        $mform->addHelpButton('sch_firstamount', 'firstamount', 'local_shopping_cart');
        $mform->hideIf('sch_firstamount', 'sch_allowinstallment', 'neq', "1");

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
            get_string('duedatevariable', 'local_shopping_cart'),
            get_string('duedatevariable_desc', 'local_shopping_cart')
        );
        $mform->setDefault('sch_duedatevariable', 0);
        $mform->setType('sch_duedatevariable', PARAM_INT);
        $mform->addHelpButton('sch_duedatevariable', 'duedatevariable', 'local_shopping_cart');
        $mform->hideIf('sch_duedatevariable', 'sch_allowinstallment', 'neq', "1");

        $mform->addElement(
            'date_selector',
            'sch_duedate',
            get_string('duedate', 'local_shopping_cart'),
            '',
            $options
        );
        $mform->addHelpButton('sch_duedate', 'duedate', 'local_shopping_cart');
        $mform->hideIf('sch_duedate', 'sch_allowinstallment', 'neq', "1");
        $mform->disabledIf('sch_duedate', 'sch_duedatevariable', '>', 0);
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

    }

    /**
     * To set data for the form.
     * @param stdClass $data
     * @return void
     */
    public function set_data(stdClass $data) {

    }
}
