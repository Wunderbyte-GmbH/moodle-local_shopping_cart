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
use local_shopping_cart\shopping_cart;
use local_shopping_cart\table\cash_report_table;
use local_wunderbyte_table\filters\types\standardfilter;

require_once(__DIR__ . '/../../config.php');

global $DB;

$dbman = $DB->get_manager();

$date = optional_param('date', date('Y-m-d'), PARAM_TEXT); // Default: today.
$download = optional_param('download', '', PARAM_ALPHA);

// Optional debug param.
$debug = optional_param('debug', 0, PARAM_INT);
// 1 ... Show: Table without daily sums and without dismissible alert.
// 2 ... Show: Daily sums and dismissible alert, but no table.
// 3 ... JSON: Output table data as json (no daily sums and no dismissible alert).

// No guest autologin.
require_login(0, false);

$context = context_system::instance();
$PAGE->set_context($context);

$pagebaseurl = new moodle_url('/local/shopping_cart/report.php');
$PAGE->set_url($pagebaseurl);

if (!has_capability('local/shopping_cart:cashier', $context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('accessdenied', 'local_shopping_cart'), 4);
    echo get_string('nopermissiontoaccesspage', 'local_shopping_cart');
    echo $OUTPUT->footer();
    die();
}

// Get payment account from settings.
$accountid = get_config('local_shopping_cart', 'accountid');
$account = null;
if (!empty($accountid)) {
    $account = new \core_payment\account($accountid);
}

// Create selects for each payment gateway.
$colselects = [];
$openorderselects = [];
// Create an array of table names for the payment gateways.
if (!empty($account)) {
    foreach ($account->get_gateways() as $gateway) {
        $gwname = $gateway->get('gateway');
        if ($gateway->get('enabled')) {
            $tablename = "paygw_" . $gwname;

            // If there are open orders tables we create selects for them.
            $openorderstable = "paygw_" . $gwname . "_openorders";
            if ($dbman->table_exists($openorderstable)) {
                $openorderselects[] = "SELECT itemid, '" . $gwname .
                    "' AS gateway, tid FROM {paygw_" . $gwname . "_openorders}";
            }

            $cols = $DB->get_columns($tablename);
            // Generate a select for each table.
            // Only do this, if an orderid exists.

            $orderidexists = false;
            $paymentbrandexists = false;

            $select = "SELECT $gwname.id, $gwname.paymentid";
            $from = "FROM {paygw_$gwname} $gwname";

            foreach ($cols as $key => $value) {
                if (strpos($key, 'orderid') !== false) {
                    $orderidexists = true;

                    $select .= ", $gwname.$key orderid ";
                }
                if (strpos($key, 'paymentbrand') !== false) {
                    $paymentbrandexists = true;
                    $select .= ", $gwname.$key paymentbrand ";
                }
            }
            if ($orderidexists) {

                if (!$paymentbrandexists) {
                    $select .= ", '" . get_string('unknown', 'local_shopping_cart') . "' as paymentbrand ";
                }
                $colselects[] = "$select $from";
            }
        }
    }
}

// If we have open orders tables select statements, we can now UNION them.
if (!empty($openorderselects)) {
    // Some clients do not need the default orderid but the custom orderid from the openorders table.
    $customorderid = "oo.tid AS customorderid, ";
    $openorderselectsstring = implode(' UNION ', $openorderselects);
    $customorderidpart = "LEFT JOIN ($openorderselectsstring) oo ON scl.identifier = oo.itemid AND oo.gateway = p.gateway";
} else {
    // If we do not have any open orders tables, we still keep an empty custom order id column for consistency.
    $customorderid = "'' AS customorderid, ";
    $customorderidpart = '';
}

if (!empty($colselects)) {
    $gatewaysupported = true;
    $uniqueidpart = $DB->sql_concat("scl.id", "' - '",
        // Sql_cast_to_char is available since Moodle 4.1.
        $CFG->version > 2022112800 ? "COALESCE(" . $DB->sql_cast_to_char("p.id") . ",'X')" :
            "COALESCE(CAST(p.id AS VARCHAR),'X')",
        "' - '",
        // Sql_cast_to_char is available since Moodle 4.1.
        $CFG->version > 2022112800 ? "COALESCE(" . $DB->sql_cast_to_char("pgw.id") . ",'X')" :
            "COALESCE(CAST(pgw.id AS VARCHAR),'X')");
    $selectorderidpart = ", pgw.orderid, pgw.paymentbrand";
    $colselectsstring = implode(' UNION ', $colselects);
    $gatewayspart = "LEFT JOIN ($colselectsstring) pgw ON p.id = pgw.paymentid";
} else {
    // Gateway missing or not supported.
    $gatewaysupported = false;
    $gatewayspart = "";
    $selectorderidpart = "";
    $uniqueidpart = $DB->sql_concat("scl.id", "' - '",
        // Sql_cast_to_char is available since Moodle 4.1.
        $CFG->version > 2022112800 ? "COALESCE(" . $DB->sql_cast_to_char("p.id") . ",'X')" :
            "COALESCE(CAST(p.id AS VARCHAR),'X')");
}

// SQL query. The subselect will fix the "Did you remember to make the first column something...
// ...unique in your call to get_records?" bug.
$fields = "s1.*";
$from = "(SELECT DISTINCT " . $uniqueidpart .
        " AS uniqueid, scl.id, scl.userid, scl.identifier, scl.price, scl.discount, scl.credits, scl.fee, scl.currency,
        u.lastname, u.firstname, u.email, scl.itemid, scl.itemname, scl.payment, scl.paymentstatus, " .
        $customorderid .
        $DB->sql_concat("um.firstname", "' '", "um.lastname") . " as usermodified, scl.timecreated, scl.timemodified,
        scl.annotation,
        p.gateway$selectorderidpart
        FROM {local_shopping_cart_ledger} scl
        LEFT JOIN {payments} p
        ON p.itemid = scl.identifier
        $customorderidpart
        LEFT JOIN {user} u
        ON u.id = scl.userid
        LEFT JOIN {user} um
        ON um.id = scl.usermodified
        $gatewayspart
        ORDER BY scl.id DESC
        ) s1";
$where = "1 = 1";
$params = [];

// Setup the table.
// File name and sheet name.
$fileandsheetname = "cash_report";

if ($debug != 2) {
    $table = new cash_report_table('cash_report_table');
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER INIT:<br>$encodedtable", DEBUG_ALL);
    }

    $table->is_downloading($download, $fileandsheetname, $fileandsheetname);

    $downloadbaseurl = new moodle_url('/local/shopping_cart/download_cash_report.php');
    $downloadbaseurl->remove_params('page');
    $table->define_baseurl($downloadbaseurl);

    // Headers.
    $headers = [
        get_string('id', 'local_shopping_cart'),
        get_string('userid', 'local_shopping_cart'),
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
        get_string('paymentbrand', 'local_shopping_cart'),
        get_string('paymentstatus', 'local_shopping_cart'),
        get_string('gateway', 'local_shopping_cart'),
        get_string('orderid', 'local_shopping_cart'),
        get_string('annotation', 'local_shopping_cart'),
        get_string('cashier', 'local_shopping_cart'),
        get_string('receipt', 'local_shopping_cart'),
    ];
    // Columns.
    $columns = [
        'id',
        'userid',
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
        'paymentbrand',
        'paymentstatus',
        'gateway',
    ];
    if (get_config('local_shopping_cart', 'cashreportshowcustomorderid')) {
        // Only show custom order id if config setting is turned on.
        $columns[] = 'customorderid';
    } else {
        // Default.
        $columns[] = 'orderid';
    }
    $columns[] = 'annotation';
    $columns[] = 'usermodified';
    $columns[] = 'receipt';

    if (!$gatewaysupported) {
        // We remove orderid if no gateway is set or if gateway is not supported.
        if (($oid = array_search(get_string('orderid', 'local_shopping_cart'), $headers)) !== false) {
            unset($headers[$oid]);
            $headers = array_values($headers); // Re-index so we do not mess order.
        }
        if (($oid = array_search('orderid', $columns)) !== false) {
            unset($columns[$oid]);
            $columns = array_values($columns); // Re-index so we do not mess order.
        }
    }

    $table->define_headers($headers);
    $table->define_columns($columns);

    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER DEFINE HEADERS & COLS:<br>$encodedtable", DEBUG_ALL);
    }

    // Table cache.
    $table->define_cache('local_shopping_cart', 'cachedcashreport');
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER DEFINE CACHE:<br>$encodedtable", DEBUG_ALL);
    }
    $table->showdownloadbutton = true;

    // Now build the table.
    $table->set_sql($fields, $from, $where, $params);
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER SET_SQL:<br>$encodedtable", DEBUG_ALL);
    }

    $table->sortable(true, 'id', SORT_DESC);
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER SORTABLE:<br>$encodedtable", DEBUG_ALL);
    }

    $standardfilter = new standardfilter('payment', get_string('payment', 'local_shopping_cart'));
    $standardfilter->add_options([
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE =>
            get_string('paymentmethodonline', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER =>
            get_string('paymentmethodcashier', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS =>
            get_string('paymentmethodcredits', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH =>
            get_string('paymentmethodcreditspaidbackcash', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER =>
            get_string('paymentmethodcreditspaidbacktransfer', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION =>
            get_string('paymentmethodcreditscorrection', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_REBOOKING_CREDITS_CORRECTION =>
            get_string('paymentmethodrebookingcreditscorrection', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH =>
            get_string('paymentmethodcashier:cash', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CREDITCARD =>
            get_string('paymentmethodcashier:creditcard', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_DEBITCARD =>
            get_string('paymentmethodcashier:debitcard', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL =>
            get_string('paymentmethodcashier:manual', 'local_shopping_cart'),
    ]);

    $table->add_filter($standardfilter);

    $standardfilter = new standardfilter('paymentstatus', get_string('paymentstatus', 'local_shopping_cart'));
    $standardfilter->add_options([
        LOCAL_SHOPPING_CART_PAYMENT_PENDING => get_string('paymentpending', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_ABORTED => get_string('paymentaborted', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_SUCCESS => get_string('paymentsuccess', 'local_shopping_cart'),
        LOCAL_SHOPPING_CART_PAYMENT_CANCELED => get_string('paymentcanceled', 'local_shopping_cart'),
    ]);
    $table->add_filter($standardfilter);

    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER FILTERCOLS:<br>$encodedtable", DEBUG_ALL);
    }

    // Sortable columns.
    $sortablecols = [
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
        'paymentbrand',
        'paymentstatus',
        'gateway',
        'orderid',
        'annotation',
        'usermodified',
    ];

    // Columns used for fulltext search.
    $fulltextsearchcols = [
        'identifier',
        'lastname',
        'firstname',
        'email',
        'itemname',
        'gateway',
        'orderid',
        'annotation',
    ];

    if (!$gatewaysupported) {
        // We remove orderid if no gateway is set or if gateway is not supported.
        if (($oid = array_search('orderid', $sortablecols)) !== false) {
            unset($sortablecols[$oid]);
            $sortablecols = array_values($sortablecols); // Re-index so we do not mess order.
        }
        if (($oid = array_search('orderid', $fulltextsearchcols)) !== false) {
            unset($fulltextsearchcols[$oid]);
            $fulltextsearchcols = array_values($fulltextsearchcols); // Re-index so we do not mess order.
        }
    }
    // Now we can define the columns.
    $table->define_sortablecolumns($sortablecols);
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER SORTABLECOLS:<br>$encodedtable", DEBUG_ALL);
    }
    $table->define_fulltextsearchcolumns($fulltextsearchcols);
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER FULLTEXTSEARCHCOLS:<br>$encodedtable", DEBUG_ALL);
    }
    $table->pageable(true);
    if ($debug == 3) {
        $encodedtable = json_encode($table);
        debugging("TABLE AFTER PAGEABLE:<br>$encodedtable", DEBUG_ALL);
    }
}

// Table will be shown normally.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cashreport', 'local_shopping_cart'));

// Debug-Mode 1 + 3: We do not show daily sums.
if ($debug != 1 && $debug != 3) {

    // Check if daily sums are turned on in settings.
    if (get_config('local_shopping_cart', 'showdailysums')) {
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
            echo $OUTPUT->render_from_template('local_shopping_cart/report_daily_sums',
                shopping_cart::get_daily_sums_data($date, $selectorformoutput));
        } else {
            // Show daily sums.
            echo $OUTPUT->render_from_template('local_shopping_cart/report_daily_sums',
                shopping_cart::get_daily_sums_data($date, $selectorformoutput));
        }
    }

    // Dismissible alert containing the description of the report.
    echo '<div class="alert alert-secondary alert-dismissible fade show" role="alert">' .
        get_string('cashreport_desc', 'local_shopping_cart') .
        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
        </button>
    </div>';

    // Dismissible alert showing a warning if payment gateway is missing or not supported.
    if (!$gatewaysupported) {
        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">' .
            get_string('error:gatewaymissingornotsupported', 'local_shopping_cart') .
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>';
    }
}

// Debug-mode 2 and 3 we do not output the table.
if ($debug != 2 && $debug != 3) {
    $table->out(30, false);
}
if ($debug == 3) {
    $encodedtable = json_encode($table);
    debugging("FINAL TABLE:<br>$encodedtable", DEBUG_ALL);
}

echo $OUTPUT->footer();
