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
use core_user;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Dynamic credits manager form.
 * @copyright   Wunderbyte GmbH <info@wunderbyte.at>
 * @package     local_shopping_cart
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modal_creditsmanager extends dynamic_form {

    /**
     * {@inheritdoc}
     * @see moodleform::definition()
     */
    public function definition() {
        global $USER;

        $userid = $this->_ajaxformdata['userid'];
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $userid = (int) $userid;

        $mform = $this->_form;
        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            $mform->addElement('html', get_string('nopermissiontoaccesspage', 'local_shopping_cart'));
            return;
        }
        $user = core_user::get_user($userid, 'id, firstname, lastname, email');
        $a = new stdClass();
        $a->username = "$user->firstname $user->lastname";
        $a->userid = $userid;

        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('html', '<div class="alert alert-info">' .
                get_string('creditsmanager:infotext', 'local_shopping_cart', $a) . '</div>');

        $modes = [
            0 => get_string('choose...', 'local_shopping_cart'),
            1 => get_string('creditsmanager:correctcredits', 'local_shopping_cart'),
            2 => get_string('creditsmanager:payback', 'local_shopping_cart'),
        ];
        $mform->addElement('select', 'creditsmanagermode',
            get_string('creditsmanagermode', 'local_shopping_cart'), $modes);
        $mform->setDefault('creditsmanagermode', 0);

        $mform->addElement('float', 'creditsmanagercredits',
            get_string('creditsmanagercredits', 'local_shopping_cart'));
        $mform->setDefault('creditsmanagercredits', 0.00);
        $mform->addHelpButton('creditsmanagercredits', 'creditsmanagercredits', 'local_shopping_cart');
        $mform->hideIf('creditsmanagercredits', 'creditsmanagermode', 'eq', 0);

        $paymentmethods = [
            0 => get_string('choose...', 'local_shopping_cart'),
            LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH =>
                get_string('paymentmethodcreditspaidbackcash', 'local_shopping_cart'),
            LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER =>
                get_string('paymentmethodcreditspaidbacktransfer', 'local_shopping_cart'),
        ];
        $mform->addElement('select', 'creditsmanagerpaymentmethod',
            get_string('paymentmethod', 'local_shopping_cart'), $paymentmethods);
        $mform->setDefault('creditsmanagerpaymentmethod', 0);
        $mform->hideIf('creditsmanagerpaymentmethod', 'creditsmanagermode', 'eq', 0);
        $mform->disabledIf('creditsmanagerpaymentmethod', 'creditsmanagermode', 'eq', 1);

        $mform->addElement('text', 'creditsmanagerreason',
            get_string('creditsmanagerreason', 'local_shopping_cart'));
        $mform->hideIf('creditsmanagerreason', 'creditsmanagermode', 'eq', 0);

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

        // At first, we need to get the mode, so we know if credits are added or paid back.
        $mode = $data->creditsmanagermode;
        switch ($mode) {
            case 1: // Correct credits.
                if (!shopping_cart_credits::creditsmanager_correct_credits($data)) {
                    $data->error = 'notenoughcredits';
                }
                break;
            case 2: // Pay back credits.
                if (!self::creditsmanager_payback_credits($data)) {
                    $data->error = 'notenoughcredits';
                }
                break;
        }
        return $data;
    }

    /**
     * Pay back credits.
     * @param stdClass $data the form data
     * @return bool true if successful, false if not
     */
    public static function creditsmanager_payback_credits(stdClass $data): bool {
        global $DB, $USER;
        $now = time();
        $userid = $data->userid;

        // Get the current credit balance.
        list($currentbalance, $currency) = shopping_cart_credits::get_balance($userid);

        $newbalance = $currentbalance - $data->creditsmanagercredits;
        if ($newbalance < 0) {
            return false;
        }

        $creditrecord = new stdClass;
        $creditrecord->userid = $userid;
        $creditrecord->credits = -$data->creditsmanagercredits;
        $creditrecord->balance = $newbalance; // The new balance.
        $creditrecord->currency = $currency;
        $creditrecord->usermodified = $USER->id;
        $creditrecord->timemodified = $now;
        $creditrecord->timecreated = $now;

        if (!$DB->insert_record('local_shopping_cart_credits', $creditrecord)) {
            return false;
        }

        // We always have to add the cache.
        $cartstore = cartstore::instance($userid);
        $cartstore->set_credit($creditrecord->balance, $creditrecord->currency);

        // At last, we log it to ledger.
        $ledgerrecord = new stdClass;
        $ledgerrecord->userid = $data->userid;
        $ledgerrecord->itemid = 0;
        $ledgerrecord->price = (float)(-1.0) * $data->creditsmanagercredits;
        $ledgerrecord->credits = (float)(-1.0) * $data->creditsmanagercredits;
        $ledgerrecord->currency = $currency;
        $ledgerrecord->componentname = 'local_shopping_cart';
        $ledgerrecord->payment = $data->creditsmanagerpaymentmethod;
        $ledgerrecord->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
        $ledgerrecord->usermodified = $USER->id;
        $ledgerrecord->timemodified = $now;
        $ledgerrecord->timecreated = $now;
        $ledgerrecord->annotation = $data->creditsmanagerreason;
        shopping_cart::add_record_to_ledger_table($ledgerrecord);

        return true;
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
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {

        $errors = [];

        if (empty($data['creditsmanagermode'])) {
            $errors['creditsmanagermode'] = get_string('error:choosevalue', 'local_shopping_cart');
        }
        if (empty($data['creditsmanagercredits']) ||
            ($data['creditsmanagermode'] == 2 && $data['creditsmanagercredits'] <= 0)) {
            $errors['creditsmanagercredits'] = get_string('error:notpositive', 'local_shopping_cart');
        }
        if (isset($data['creditsmanagermode']) && $data['creditsmanagermode'] == 2 && empty($data['creditsmanagerpaymentmethod'])) {
            $errors['creditsmanagerpaymentmethod'] = get_string('error:choosevalue', 'local_shopping_cart');
        }
        if (empty($data['creditsmanagerreason'])) {
            $errors['creditsmanagerreason'] = get_string('error:noreason', 'local_shopping_cart');
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
