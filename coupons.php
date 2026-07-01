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
 * Shopping cart coupon management.
 *
 * @package     local_shopping_cart
 * @copyright   2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Georg Maißer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\table\coupons_table;

require_once(__DIR__ . '/../../config.php');

global $DB;

$download = optional_param('download', '', PARAM_ALPHA);

// No guest autologin.
require_login(0, false);

$context = context_system::instance();
$PAGE->set_context($context);

$pagebaseurl = new moodle_url('/local/shopping_cart/coupons.php');
$PAGE->set_url($pagebaseurl);

if (!has_capability('local/shopping_cart:editcoupons', $context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('accessdenied', 'local_shopping_cart'), 4);
    echo get_string('nopermissiontoaccesspage', 'local_shopping_cart');
    echo $OUTPUT->footer();
    die();
}

// SQL query. The subselect will fix the "Did you remember to make the first column something...
// ...unique in your call to get_records?" bug.
$fields = "*";
$from = "{local_shopping_cart_coupons}";
$where = "1 = 1";
$params = [];

// Setup the table.
// File name and sheet name.
$fileandsheetname = "coupons";

$table = new coupons_table('coupons_table');

$columns = [
    'id' => get_string('id', 'local_shopping_cart'),
    'coupon' => get_string('coupon', 'local_shopping_cart'),
    'discountpercentage' => get_string('discountpercent', 'local_shopping_cart') . ' (%)',
    'discountabsolute' => get_string('discountabsolute', 'local_shopping_cart'),
    'currency' => get_string('currency', 'local_shopping_cart'),
    'maxnumber' => get_string('maxnumber', 'local_shopping_cart'),
    'active' => get_string('active', 'core'),
    'coupontype' => get_string('coupontype', 'local_shopping_cart'),
    'starttime' => get_string('startdate', 'local_shopping_cart'),
    'endtime' => get_string('enddate', 'local_shopping_cart'),
    'usermodified' => get_string('usermodified', 'local_shopping_cart'),
    'timecreated' => get_string('timecreated', 'local_shopping_cart'),
    'timemodified' => get_string('timemodified', 'local_shopping_cart'),
    'action' => get_string('action', 'local_shopping_cart'),
];

$table->define_columns(array_keys($columns));
$table->define_headers(array_values($columns));


$table->is_downloading($download, $fileandsheetname, $fileandsheetname);

// Table cache.
$table->define_cache('local_shopping_cart', 'cachedcouponstable');

$table->showdownloadbutton = true;

// Now build the table.
$table->set_sql($fields, $from, $where, $params);

$table->sortable(true, 'id', SORT_DESC);

// Sortable columns.
$sortablecols = array_keys($columns);

// Now we can define the columns.
$table->define_sortablecolumns($sortablecols);

$table->pageable(true);

// Table will be shown normally.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecoupons', 'local_shopping_cart'));

// Render standalone "Add coupon" button — visible even when the table is empty.
$addcouponbutton = [
    'showactionbuttons' => [
        [
            'label' => get_string('addcoupon', 'local_shopping_cart'),
            'class' => 'btn btn-primary mb-3',
            'href' => '#',
            'formname' => 'local_shopping_cart\\form\\addedit_coupon',
            'nomodal' => false,
            'selectionmandatory' => false,
            'id' => 0,
            'data' => [
                ['key' => 'id', 'value' => 0],
                ['key' => 'titlestring', 'value' => 'addcoupon'],
                ['key' => 'bodystring', 'value' => 'addcoupon'],
                ['key' => 'submitbuttonstring', 'value' => 'addcoupon'],
                ['key' => 'component', 'value' => 'local_shopping_cart'],
                ['key' => 'labelcolumn', 'value' => 'coupon'],
                ['key' => 'noselectionbodystring', 'value' => 'addcoupon'],
            ],
        ],
    ],
];

echo $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', $addcouponbutton);
$PAGE->requires->js_call_amd('local_wunderbyte_table/actionbutton', 'initializeActionButton', ['body', '', '']);

$table->out(30, false);

echo $OUTPUT->footer();
