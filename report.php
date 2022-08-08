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

use local_shopping_cart\table\cash_report_table;

require_once(__DIR__ . '/../../config.php');

global $DB;

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
$cashreporttable->defaultdownloadformat = 'pdf';

// Header.
$cashreporttable->define_headers([
    get_string('id', 'local_shopping_cart'),
    get_string('identifier', 'local_shopping_cart'),
    get_string('timecreated', 'local_shopping_cart'),
    get_string('timemodified', 'local_shopping_cart'),
    get_string('price', 'local_shopping_cart'),
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

            // Do not add the table if it does not have exactly 3 columns.
            if (count($cols) != 3) {
                continue;
            }

            // Generate a select for each table.
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
$fields = "sch.id, sch.identifier, sch.price, sch.currency,
        u.lastname, u.firstname, u.email, sch.itemid, sch.itemname, sch.payment, sch.paymentstatus, " .
        $DB->sql_concat("um.firstname", "' '", "um.lastname") . " as usermodified, sch.timecreated, sch.timemodified,
        p.gateway$selectorderidpart";
$from = "{local_shopping_cart_history} sch
        LEFT JOIN {user} u
        ON u.id = sch.userid
        LEFT JOIN {user} um
        ON um.id = sch.usermodified
        LEFT JOIN {payments} p
        ON p.itemid = sch.identifier
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

    // Show daily sums.
    generate_and_output_daily_sums();

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
 */
function generate_and_output_daily_sums() {
    global $DB, $OUTPUT, $USER;

    $commaseparator = current_language() == 'de' ? ',' : '.';

    // SQL to get daily sums.
    $dailysumssql = "SELECT payment, sum(price) dailysum
        FROM {local_shopping_cart_history}
        WHERE timecreated BETWEEN :startoftoday AND :endoftoday
        AND paymentstatus = :paymentsuccess
        GROUP BY payment";

    // SQL params.
    $dailysumsparams = [
        'startoftoday' => strtotime('today 00:00'),
        'endoftoday' => strtotime('today 24:00'),
        'paymentsuccess' => PAYMENT_SUCCESS
    ];

    $dailysumsfromdb = $DB->get_records_sql($dailysumssql, $dailysumsparams);
    foreach ($dailysumsfromdb as $dailysumrecord) {
        $dailysumrecord->dailysumformatted = number_format((float)$dailysumrecord->dailysum, 2, $commaseparator, '');
        switch ($dailysumrecord->payment) {
            case PAYMENT_METHOD_ONLINE:
                $dailysumrecord->paymentmethod = get_string('paymentmethodonline', 'local_shopping_cart');
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier', 'local_shopping_cart');
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CREDITS:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcredits', 'local_shopping_cart');
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER_CASH:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER_CREDITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER_DEBITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
                break;
        }
    }

    // Now get data for current cashier.
    // SQL to get daily sums.
    $dailysumssqlcurrent = "SELECT payment, sum(price) dailysum
        FROM {local_shopping_cart_history}
        WHERE timecreated BETWEEN :startoftoday AND :endoftoday
        AND paymentstatus = :paymentsuccess
        AND usermodified = :userid
        GROUP BY payment";

    // SQL params.
    $dailysumsparamscurrent = [
        'startoftoday' => strtotime('today 00:00'),
        'endoftoday' => strtotime('today 24:00'),
        'paymentsuccess' => PAYMENT_SUCCESS,
        'userid' => $USER->id
    ];

    $dailysumsfromdbcurrentcashier = $DB->get_records_sql($dailysumssqlcurrent, $dailysumsparamscurrent);
    foreach ($dailysumsfromdbcurrentcashier as $dailysumrecord) {
        $dailysumrecord->dailysumformatted = number_format((float)$dailysumrecord->dailysum, 2, $commaseparator, '');
        switch ($dailysumrecord->payment) {
            case PAYMENT_METHOD_ONLINE:
                $dailysumrecord->paymentmethod = get_string('paymentmethodonline', 'local_shopping_cart');
                $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier', 'local_shopping_cart');
                $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CREDITS:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcredits', 'local_shopping_cart');
                $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER_CASH:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER_CREDITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                break;
            case PAYMENT_METHOD_CASHIER_DEBITCARD:
                $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                break;
        }
    }

    if (!empty($dailysumsdata['dailysums'])) {
        $dailysumsdata['dailysums:exist'] = true;
    }

    if (!empty($dailysumsdata['dailysumscurrentcashier'])) {
        $dailysumsdata['dailysumscurrentcashier:exist'] = true;
    }

    $dailysumsdata['currentcashier:fullname'] = "$USER->firstname $USER->lastname";
    $dailysumsdata['currentdate'] = current_language() == 'de' ? date('d.m.Y') : date('Y-m-d');

    echo $OUTPUT->render_from_template('local_shopping_cart/report_daily_sums', $dailysumsdata);
}
