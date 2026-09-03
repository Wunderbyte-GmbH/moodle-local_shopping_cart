<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Function to correctly upgrade local_shopping_cart
 *
 * @package    local_shopping_cart
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fix ledger bug
 * @return void
 */
function fix_ledger_bug() {

    global $DB, $CFG;

    $sql = "SELECT sch.*
        FROM {local_shopping_cart_history} sch
        LEFT JOIN {local_shopping_cart_ledger} scl ON sch.identifier = scl.identifier AND sch.itemid = scl.itemid
        WHERE sch.paymentstatus = 2 AND scl.id IS NULL
        AND sch.timemodified > 1711925988 AND sch.timemodified < 1715779807";

    $records = $DB->get_records_sql($sql);

    foreach ($records as $record) {
        $record->schistoryid = $record->id;
        unset($record->id);
        $record->annotation = "Fixed record because of bug in Mai 2024";

        $DB->insert_record('local_shopping_cart_ledger', $record);
    }

    $sql = "SELECT scl.*
            FROM {local_shopping_cart_ledger} scl
            WHERE scl.timecreated IS NULL AND scl.timemodified IS NOT NULL";

    $records = $DB->get_records_sql($sql);

    foreach ($records as $record) {
        $record->timecreated = $record->timemodified;

        $DB->update_record('local_shopping_cart_ledger', $record);
    }
}

/**
 * Fixes missing address informations in ledger tables.
 *
 * @return void
 *
 */
function fix_missing_addresses() {
    global $DB;

    $sql = "SELECT DISTINCT schl.id, sch.address_billing, sch.address_shipping
            FROM {local_shopping_cart_ledger} schl
            LEFT JOIN {local_shopping_cart_history} sch ON schl.identifier = sch.identifier
            WHERE (schl.address_billing IS NULL AND sch.address_billing IS NOT NULL)
            OR (schl.address_shipping IS NULL AND sch.address_shipping IS NOT NULL)";

    $records = $DB->get_records_sql($sql);

    foreach ($records as $record) {
        $data = (object)[
            'id' => $record->id,
            'address_billing' => $record->address_billing,
            'address_shipping' => $record->address_shipping,
        ];
        $DB->update_record('local_shopping_cart_ledger', $data);
    }
}

/**
 * Create the tables and fields the coupon and guest checkout features need.
 *
 * This is idempotent and deliberately callable more than once: every statement checks first
 * whether the table or field is already there.
 *
 * It was originally the body of the 2026073001 upgrade step. Sites of the USI release line never
 * ran that step - their plugin version was already higher than the savepoint when the features
 * arrived, so the step is skipped for them forever. They therefore get the same statements again
 * under a later savepoint (Wunderbyte-GmbH/moodle-local_shopping_cart#204).
 *
 * @return void
 */
function local_shopping_cart_create_coupon_and_guest_tables() {

    global $DB;

    $dbman = $DB->get_manager();

    // Create the local_shopping_cart_guestusers table to track temporary guest
    // checkout accounts that have not yet been converted to real Moodle users.
    $table = new xmldb_table('local_shopping_cart_guestusers');

    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_index('userid_idx', XMLDB_INDEX_UNIQUE, ['userid']);

    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // The separate "auto-create guest users" toggle has been merged into the single
    // guestoncheckout master switch. Front-loading guest users on specific pages is now driven
    // purely by the presence of URL patterns, so the obsolete flag can be removed.
    unset_config('guestautocreateenabled', 'local_shopping_cart');

    // Define table local_shopping_cart_coupons to be created.
    $table = new xmldb_table('local_shopping_cart_coupons');

    // Adding fields to table local_shopping_cart_coupons.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('coupon', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    /* Precision 4,1 so that a 100 % coupon fits; sites that already have the table with the
    original 3,1 get it widened by the 2026082700 upgrade step. */
    $table->add_field('discountpercentage', XMLDB_TYPE_NUMBER, '4, 1', null, null, null, '0');
    $table->add_field('discountabsolute', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, '0');
    $table->add_field('currency', XMLDB_TYPE_CHAR, '10', null, null, null, 'EUR');
    $table->add_field('maxnumber', XMLDB_TYPE_INTEGER, '8', null, null, null, '1');
    $table->add_field('json', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('active', XMLDB_TYPE_INTEGER, '2', null, null, null, '1');
    $table->add_field('coupontype', XMLDB_TYPE_CHAR, '255', null, null, null, null);
    $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    // Adding keys to table local_shopping_cart_coupons.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

    // Adding indexes to table local_shopping_cart_coupons.
    $table->add_index('coupon_idx', XMLDB_INDEX_UNIQUE, ['coupon']);

    // Conditionally launch create table for local_shopping_cart_coupons.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }

    // Sites whose coupons table was created before the coupontype field existed skip the
    // create_table above, so the field has to be added separately for them.
    // Position after 'active' to match install.xml.
    $field = new xmldb_field('coupontype', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'active');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Define field coupon to be added to local_shopping_cart_history.
    $table = new xmldb_table('local_shopping_cart_history');
    $field = new xmldb_field('coupon', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'multipliable');

    // Conditionally launch add field coupon.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Define field coupon to be added to local_shopping_cart_ledger.
    $table = new xmldb_table('local_shopping_cart_ledger');
    $field = new xmldb_field('coupon', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'multipliable');

    // Conditionally launch add field coupon.
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }
}
