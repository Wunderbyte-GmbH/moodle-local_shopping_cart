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

use local_shopping_cart\local\coupon;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

use context;
use context_system;
use core_form\dynamic_form;
use moodle_url;

/**
 * Dynamic add edit coupon form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_shopping_cart
 * @author Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addedit_coupon extends dynamic_form {
    /**
     * Define the form fields.
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden ID for editing.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Coupon code.
        $mform->addElement('text', 'coupon', get_string('coupon', 'local_shopping_cart'));
        $mform->setType('coupon', PARAM_RAW_TRIMMED);
        $mform->addRule('coupon', null, 'maxlength', 1333);

        // Discount type selector.
        $mform->addElement('select', 'discounttype', get_string('discounttype', 'local_shopping_cart'), [
            'percentage' => get_string('discountpercent', 'local_shopping_cart') . ' (%)',
            'absolute'   => get_string('discountabsolute', 'local_shopping_cart') .
                ' (' . get_string('absolute', 'local_shopping_cart') . ')',
        ]);
        $mform->setType('discounttype', PARAM_ALPHA);
        $mform->setDefault('discounttype', 'percentage');

        // Discount percentage.
        $mform->addElement(
            'float',
            'discountpercentage',
            get_string('discountpercent', 'local_shopping_cart') . ' (%)'
        );
        $mform->setType('discountpercentage', PARAM_FLOAT);
        $mform->setDefault('discountpercentage', 0);
        $mform->hideIf('discountpercentage', 'discounttype', 'neq', 'percentage');

        // Absolute discount.
        $mform->addElement(
            'float',
            'discountabsolute',
            get_string('discountabsolute', 'local_shopping_cart') .
            ' (' . get_string('absolute', 'local_shopping_cart') . ')'
        );
        $mform->setType('discountabsolute', PARAM_FLOAT);
        $mform->setDefault('discountabsolute', 0);
        $mform->hideIf('discountabsolute', 'discounttype', 'neq', 'absolute');

        // Currency (use Moodle core list).
        $currencies = get_string_manager()->get_list_of_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'moodle'), $currencies);
        $mform->setType('currency', PARAM_ALPHANUMEXT);
        $mform->setDefault('currency', 'EUR');
        $mform->hideIf('currency', 'discounttype', 'neq', 'absolute');

        // Maximum use count.
        $mform->addElement('text', 'maxnumber', get_string('maxnumber', 'local_shopping_cart'));
        $mform->setType('maxnumber', PARAM_INT);
        $mform->setDefault('maxnumber', 1);

        // Active.
        $mform->addElement(
            'select',
            'active',
            get_string('active', 'moodle'),
            [
                1 => get_string('yes'),
                0 => get_string('no'),
            ]
        );
        $mform->setDefault('active', 1);
        $mform->setType('active', PARAM_INT);

        // Coupon type.
        $mform->addElement(
            'select',
            'coupontype',
            get_string('coupontype', 'local_shopping_cart'),
            [
                'couponoptin' => get_string('couponoptin', 'local_shopping_cart'),
                'couponoptout'   => get_string('couponoptout', 'local_shopping_cart'),
            ]
        );
        $mform->setType('coupontype', PARAM_ALPHA);
        // Opt-out is the safe default: an opt-in coupon has no effect at all until
        // items explicitly enable it in their item configuration.
        $mform->setDefault('coupontype', 'couponoptout');
        $mform->addHelpButton('coupontype', 'coupontype', 'local_shopping_cart');

        // Start time.
        $mform->addElement(
            'date_time_selector',
            'starttime',
            get_string('startdate', 'local_shopping_cart'),
            [
                'optional' => true,
            ]
        );
        $mform->setType('starttime', PARAM_INT);

        // End time.
        $mform->addElement(
            'date_time_selector',
            'endtime',
            get_string('enddate', 'local_shopping_cart'),
            [
                'optional' => true,
            ]
        );
        $mform->setType('endtime', PARAM_INT);
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['discounttype'] === 'percentage') {
            if ($data['discountpercentage'] < 0 || $data['discountpercentage'] > 100) {
                $errors['discountpercentage'] = get_string('invalidpercentage', 'local_shopping_cart');
            }
        } else {
            if ($data['discountabsolute'] < 0) {
                $errors['discountabsolute'] = get_string('invalidabsolute', 'local_shopping_cart');
            }
        }

        if (
            !empty($data['endtime'])
            && !empty($data['starttime'])
            && $data['endtime'] < $data['starttime']
        ) {
            $errors['endtime'] = get_string('endbeforestart', 'local_shopping_cart');
        }

        return $errors;
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

        if ($data->discounttype === 'percentage') {
            $data->discountabsolute = 0;
            $data->currency = '';
        } else {
            $data->discountpercentage = 0;
        }

        coupon::add_edit_coupon(
            $data->id,
            $data->coupon,
            $data->discountpercentage,
            $data->discountabsolute,
            $data->currency,
            $data->maxnumber,
            $data->active,
            $data->starttime,
            $data->endtime,
            $USER->id,
            $data->coupontype
        );

        $data->reload = true;
        return $data;
    }

    /**
     * Load existing data.
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $data = $this->_ajaxformdata ?? $this->_customdata ?? [];
        $id = $data['id'] ?? 0;
        if ($id) {
            $record = $DB->get_record('local_shopping_cart_coupons', ['id' => $id], '*', MUST_EXIST);
            $record->discounttype = ($record->discountabsolute > 0) ? 'absolute' : 'percentage';
            $this->set_data($record);
        }
    }

    /**
     * Where the form submits to.
     */
    protected function get_page_url(): moodle_url {
        return new moodle_url('/local/shopping_cart/coupons.php');
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
     * Check access for dynamic submission.
     *
     * @return void
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/shopping_cart:editcoupons', $this->get_context_for_dynamic_submission());
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
        return new moodle_url("/local/shopping_cart/coupons.php");
    }
}
