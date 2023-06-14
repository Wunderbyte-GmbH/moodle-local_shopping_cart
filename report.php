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
 * Shopping cart cash report.
 *
 * @package     local_shopping_cart
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\form\daily_sums_date_selector_form;
use local_shopping_cart\table\cash_report_table;

require_once(__DIR__ . '/../../config.php');

global $DB;

$date = optional_param('date', date('Y-m-d'), PARAM_TEXT); // Default: today.
$download = optional_param('download', '', PARAM_ALPHA);

// No guest autologin.
require_login(0, false);

$context = context_system::instance();
$PAGE->set_context($context);

$baseurl = new moodle_url('/local/shopping_cart/report.php');
$PAGE->set_url($baseurl);

if (!has_capability('local/shopping_cart:cashier', $context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('accessdenied', 'local_shopping_cart'), 4);
    echo get_string('nopermissiontoaccesspage', 'local_shopping_cart');
    echo $OUTPUT->footer();
    die();
}

// File name and sheet name.
$fileandsheetname = "cash_report";

$cashreporttable = new cash_report_table('cash_report_table');

$cashreporttable->is_downloading($download, $fileandsheetname, $fileandsheetname);

$tablebaseurl = $baseurl;
$tablebaseurl->remove_params('page');
$cashreporttable->define_baseurl($tablebaseurl);

// Header.
$cashreporttable->define_headers([
    get_string('id', 'local_shopping_cart'),
    get_string('identifier', 'local_shopping_cart'),
    get_string('timecreated', 'local_shopping_cart'),
    get_string('timemodified', 'local_shopping_cart'),
    get_string('paid', 'local_shopping_cart'),
    get_string('discount', 'local_shopping_cart'),
    get_string('credit', 'local_shopping_cart'),
    get_string('cancelationfee', 'local_shopping_cart'),
    get_string('currency', 'local_shopping_cart'),
    get_string('lastname', 'local_shopping_cart'),
    get_string('firstname', 'local_shopping_cart'),
    get_string('email', 'local_shopping_cart'),
    get_string('itemid', 'local_shopping_cart'),
    get_string('itemname', 'local_shopping_cart'),
    get_string('payment', 'local_shopping_cart'),
    get_string('paymentstatus', 'local_shopping_cart'),
    get_string('gateway', 'local_shopping_cart'),
    get_string('orderid', 'local_shopping_cart'),
    get_string('usermodified', 'local_shopping_cart')
]);

// Columns.
$cashreporttable->define_columns([
    'id',
    'identifier',
    'timecreated',
    'timemodified',
    'price',
    'discount',
    'credits',
    'fee',
    'currency',
    'lastname',
    'firstname',
    'email',
    'itemid',
    'itemname',
    'payment',
    'paymentstatus',
    'gateway',
    'orderid',
    'usermodified'
]);

// Get payment account from settings.
$accountid = get_config('local_shopping_cart', 'accountid');
$account = null;
if (!empty($accountid)) {
    $account = new \core_payment\account($accountid);
}

// Create selects for each payment gateway.
$colselects = [];
// Create an array of table names for the payment gateways.
if (!empty($account)) {
    foreach ($account->get_gateways() as $gateway) {
        $gwname = $gateway->get('gateway');
        if ($gateway->get('enabled')) {
            $tablename = "paygw_" . $gwname;

            $cols = $DB->get_columns($tablename);
            // Generate a select for each table.
            // Only do this, if an orderid exists.
            foreach ($cols as $key => $value) {
                if (strpos($key, 'orderid') !== false) {
                    $colselects[] =
                        "SELECT $gwname.paymentid, $gwname.$key orderid
                        FROM {paygw_$gwname} $gwname";
                }
            }
        }
    }
}

$selectorderidpart = "";
if (!empty($colselects)) {
    $selectorderidpart = ", pgw.orderid";
    $colselectsstring = implode(' UNION ', $colselects);
    $gatewayspart = "LEFT JOIN ($colselectsstring) pgw ON p.id = pgw.paymentid";
}

// SQL query. The subselect will fix the "Did you remember to make the first column something...
// ...unique in your call to get_records?" bug.
$fields = "DISTINCT " . $DB->sql_concat("scl.id", "' - '", "COALESCE(pgw.orderid,'')") .
        " AS uniqueid, scl.id, scl.identifier, scl.price, scl.discount, scl.credits, scl.fee, scl.currency,
        u.lastname, u.firstname, u.email, scl.itemid, scl.itemname, scl.payment, scl.paymentstatus, " .
        $DB->sql_concat("um.firstname", "' '", "um.lastname") . " as usermodified, scl.timecreated, scl.timemodified,
        p.gateway$selectorderidpart";
$from = "{local_shopping_cart_ledger} scl
        LEFT JOIN {user} u
        ON u.id = scl.userid
        LEFT JOIN {user} um
        ON um.id = scl.usermodified
        LEFT JOIN {payments} p
        ON p.itemid = scl.identifier
        $gatewayspart";
$where = "1 = 1";
$params = [];

// Now build the table.
$cashreporttable->set_sql($fields, $from, $where, $params);

// Table is shown normally.
if (!$cashreporttable->is_downloading()) {

    // Table will be shown normally.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('cashreport', 'local_shopping_cart'));

    // Initialize the Moodle form for filtering the table.
    $mform = new daily_sums_date_selector_form();

    ob_start();
    $mform->display();
    $selectorformoutput = ob_get_contents();
    ob_end_clean();

    // Form processing and displaying is done here.
    if ($fromform = $mform->get_data()) {
        $dailysumsdate = $fromform->dailysumsdate;
        $date = date('Y-m-d', $dailysumsdate);
        generate_and_output_daily_sums($date, $selectorformoutput);
    } else {
        // Show daily sums.
        generate_and_output_daily_sums($date, $selectorformoutput);
    }

    // Dismissible alert containing the description of the report.
    echo '<div class="alert alert-secondary alert-dismissible fade show" role="alert">' .
        get_string('cashreport_desc', 'local_shopping_cart') .
        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
        </button>
    </div>';

    $cashreporttable->out(50, false);

    echo $OUTPUT->footer();

} else {
    // The table is being downloaded.
    $cashreporttable->setup();
    $cashreporttable->query_db(TABLE_SHOW_ALL_PAGE_SIZE);
    $cashreporttable->build_table();
    $cashreporttable->finish_output();
}

/**
 * Internal helper function to create daily sums.
 *
 * @param string $date date in the form 'YYYY-MM-DD'
 * @param string $selectorformoutput the HTML of the date selector form
 */
function generate_and_output_daily_sums(string $date, string $selectorformoutput) {
    global $DB, $OUTPUT, $USER;

    $commaseparator = current_language() == 'de' ? ',' : '.';

    // SQL to get daily sums.
    $dailysumssql = "SELECT payment, sum(price) dailysum
        FROM {local_shopping_cart_ledger}
        WHERE timecreated BETWEEN :startofday AND :endofday
        AND paymentstatus = :paymentsuccess
        GROUP BY payment";

    // SQL params.
    $dailysumsparams = [
        'startofday' => strtotime($date . ' 00:00'),
        'endofday' => strtotime($date . ' 24:00'),
        'paymentsuccess' => PAYMENT_SUCCESS
    ];

    $dailysumsfromdb = $DB->get_records_sql($dailysumssql, $dailysumsparams);
    foreach ($dailysumsfromdb as $dailysumrecord) {
        $dailysumrecord->dailysumformatted = number_format((float)$dailysumrecord->dailysum, 2, $commaseparator, '');
        switch ($dailysumrecord->payment) {
            case PAYMENT_METHOD_ONLINE:
                $dailysumrecord->paymentmethod = get_string('paymentmethodonline', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CREDITS:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcredits', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CREDITS_PAID_BACK:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcreditspaidback', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_CASH:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_CREDITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_DEBITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_MANUAL:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
                break;
        }
        $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
    }

    // Now get data for current cashier.
    // SQL to get daily sums.
    $dailysumssqlcurrent = "SELECT payment, sum(price) dailysum
        FROM {local_shopping_cart_history}
        WHERE timecreated BETWEEN :startofday AND :endofday
        AND paymentstatus = :paymentsuccess
        AND usermodified = :userid
        GROUP BY payment";

    // SQL params.
    $dailysumsparamscurrent = [
        'startofday' => strtotime($date . ' 00:00'),
        'endofday' => strtotime($date . ' 24:00'),
        'paymentsuccess' => PAYMENT_SUCCESS,
        'userid' => $USER->id
    ];

    $dailysumsfromdbcurrentcashier = $DB->get_records_sql($dailysumssqlcurrent, $dailysumsparamscurrent);
    foreach ($dailysumsfromdbcurrentcashier as $dailysumrecord) {
        $dailysumrecord->dailysumformatted = number_format((float)$dailysumrecord->dailysum, 2, $commaseparator, '');
        switch ($dailysumrecord->payment) {
            case PAYMENT_METHOD_ONLINE:
                $dailysumrecord->paymentmethod = get_string('paymentmethodonline', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CREDITS:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcredits', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CREDITS_PAID_BACK:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcreditspaidback', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_CASH:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_CREDITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_DEBITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                break;
            case PAYMENT_METHOD_CASHIER_MANUAL:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
                break;
        }
        $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
    }

    if (!empty($dailysumsdata['dailysums'])) {
        $dailysumsdata['dailysums:exist'] = true;
    }

    if (!empty($dailysumsdata['dailysumscurrentcashier'])) {
        $dailysumsdata['dailysumscurrentcashier:exist'] = true;
    }

    $dailysumsdata['currentcashier:fullname'] = "$USER->firstname $USER->lastname";

    // Transform date to German format if current language is German.
    if (current_language() == 'de') {
        list($year, $month, $day) = explode('-', $date);
        $dailysumsdata['date'] = $day . '.' . $month . '.' . $year;
    } else {
        $dailysumsdata['date'] = $date;
    }

    $dailysumsdata['selectorform'] = $selectorformoutput;

    echo $OUTPUT->render_from_template('local_shopping_cart/report_daily_sums', $dailysumsdata);
}
