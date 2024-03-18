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
 * Baseurl for download of cash report.
 * @copyright 2023 Wunderbyte Gmbh <info@wunderbyte.at>
 * @author  Bernhard Fischer
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package local_shopping_cart
 */

use local_wunderbyte_table\wunderbyte_table;

require_once("../../config.php");

global $CFG, $PAGE;

require_login();

require_once($CFG->dirroot . '/local/wunderbyte_table/classes/wunderbyte_table.php');

$download = optional_param('download', '', PARAM_ALPHA);
$encodedtable = optional_param('encodedtable', '', PARAM_RAW);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/download_cash_report.php');

// Table will be of an instance of the child class extending wunderbyte_table.
/** @var cash_report_table $table */
$table = wunderbyte_table::instantiate_from_tablecache_hash($encodedtable);

// Re-initialize, otherwise the defining will not work!
$table->headers = [];
$table->columns = [];

// Headers.
$table->define_headers([
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
    get_string('annotation', 'local_shopping_cart'),
    get_string('cashier', 'local_shopping_cart'),
]);

// Columns.
$table->define_columns([
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
    'annotation',
    'usermodified',
]);

// In the shopping cart config settings, we can set a row limit for download.
// This is needed, so download does not crash if the cash report is too big.
$limitpart = '';
if ($limit = get_config('local_shopping_cart', 'downloadcashreportlimit')) {
    if (!empty($limit)) {
        $limitpart = "LIMIT $limit";
        $table->sql->from = str_replace(
            ") s1",
            " $limitpart ) s1", // We inject the LIMIT here.
            $table->sql->from
        );
    }
}

// File name and sheet name.
$fileandsheetname = "cash_report";
$table->is_downloading($download, $fileandsheetname, $fileandsheetname);

$table->printtable(20, true);
