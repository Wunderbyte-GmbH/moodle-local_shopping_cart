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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_shopping_cart
 * @category    upgrade
 * @copyright   2021 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_shopping_cart upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_shopping_cart_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2022050400) {

        // Define table local_shopping_cart_credits to be created.
        $table = new xmldb_table('local_shopping_cart_credits');

        // Adding fields to table local_shopping_cart_credits.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('credits', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('currency', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('balance', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table local_shopping_cart_credits.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_shopping_cart_credits.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022050400, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022071600) {

        // Define field id to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('canceluntil', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022071600, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022072100) {

        // Changing type of field price on table local_shopping_cart_history to number.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'itemname');

        // Launch change of type for field price.
        $dbman->change_field_type($table, $field);

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022072100, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022072500) {

        // Changing type of field credits on table local_shopping_cart_credits to number.
        $table = new xmldb_table('local_shopping_cart_credits');

        $credits = new xmldb_field('credits', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null, 'userid');
        // Launch change of type for field credits.
        $dbman->change_field_type($table, $credits);
        // Launch change of precision for field credits.
        $dbman->change_field_precision($table, $credits);

        $balance = new xmldb_field('balance', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null, 'currency');
        // Launch change of type for field balance.
        $dbman->change_field_type($table, $balance);
        // Launch change of precision for field balance.
        $dbman->change_field_precision($table, $balance);

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022072500, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022081400) {

        // Define field discount to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('discount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'price');

        // Conditionally launch add field discount.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022081400, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022081500) {

        // Define table local_shopping_cart_ledger to be created.
        $table = new xmldb_table('local_shopping_cart_ledger');

        // Adding fields to table local_shopping_cart_ledger.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('discount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('credits', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('fee', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('currency', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('componentname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('identifier', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('payment', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('paymentstatus', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('accountid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('canceluntil', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table local_shopping_cart_ledger.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for local_shopping_cart_ledger.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022081500, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022101000) {

        // Define field id to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('serviceperiodstart', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'canceluntil');

        // Conditionally launch add field serviceperiodstart.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('serviceperiodend', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'serviceperiodstart');

        // Conditionally launch add field serviceperiodend.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022101000, 'local', 'shopping_cart');
    }
    if ($oldversion < 2022110300) {

        // Changing type of field itemid on table local_shopping_cart_history to int.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'userid');

        // Launch change of type for field itemid.
        $dbman->change_field_type($table, $field);

        // Define index idxuse (not unique) to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch add index idxuse.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index usepay (not unique) to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $index = new xmldb_index('usepay', XMLDB_INDEX_NOTUNIQUE, ['userid', 'paymentstatus']);

        // Conditionally launch add index usepay.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index identifier (not unique) to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $index = new xmldb_index('identifier', XMLDB_INDEX_NOTUNIQUE, ['identifier']);

        // Conditionally launch add index identifier.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index idxuse (not unique) to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_credits');
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch add index idxuse.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index userid (not unique) to be added to local_shopping_cart_ledger.
        $table = new xmldb_table('local_shopping_cart_ledger');
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index itemid (not unique) to be added to local_shopping_cart_ledger.
        $table = new xmldb_table('local_shopping_cart_ledger');
        $index = new xmldb_index('itemid', XMLDB_INDEX_NOTUNIQUE, ['itemid']);

        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index identifier (not unique) to be added to local_shopping_cart_ledger.
        $table = new xmldb_table('local_shopping_cart_ledger');
        $index = new xmldb_index('identifier', XMLDB_INDEX_NOTUNIQUE, ['identifier']);

        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022110300, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022112900) {
        // Add tax information to history and ledger tables.

        $historytable = new xmldb_table('local_shopping_cart_history');
        $ledgertable = new xmldb_table('local_shopping_cart_ledger');

        $taxfield = new xmldb_field('tax', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'price');
        $taxpercentagefield = new xmldb_field('taxpercentage', XMLDB_TYPE_NUMBER, '5, 4', null, null, null, null, 'tax');
        $taxcategoryfield = new xmldb_field('taxcategory', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'taxpercentage');

        // Add new fields to local_shopping_cart_history table.
        if (!$dbman->field_exists($historytable, $taxfield)) {
            $dbman->add_field($historytable, $taxfield);
        }
        if (!$dbman->field_exists($historytable, $taxpercentagefield)) {
            $dbman->add_field($historytable, $taxpercentagefield);
        }
        if (!$dbman->field_exists($historytable, $taxcategoryfield)) {
            $dbman->add_field($historytable, $taxcategoryfield);
        }

        // Add new fields to local_shopping_cart_ledger table.
        if (!$dbman->field_exists($ledgertable, $taxfield)) {
            $dbman->add_field($ledgertable, $taxfield);
        }
        if (!$dbman->field_exists($ledgertable, $taxpercentagefield)) {
            $dbman->add_field($ledgertable, $taxpercentagefield);
        }
        if (!$dbman->field_exists($ledgertable, $taxcategoryfield)) {
            $dbman->add_field($ledgertable, $taxcategoryfield);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022112900, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022120400) {
        // Define index userid-currency (not unique) to be added to local_shopping_cart_credits.
        $table = new xmldb_table('local_shopping_cart_credits');
        $index = new xmldb_index('userid-currency', XMLDB_INDEX_NOTUNIQUE, ['userid, currency']);

        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022120400, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022120700) {
        $historytable = new xmldb_table('local_shopping_cart_history');
        $taxfield = new xmldb_field('tax', XMLDB_TYPE_NUMBER, '10, 3', null, null, null, null, 'price');

        // Add new fields to local_shopping_cart_history table.
        if ($dbman->field_exists($historytable, $taxfield)) {
            $dbman->change_field_precision($historytable, $taxfield);
        }
        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022120700, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022120701) {
        $table = new xmldb_table('local_shopping_cart_ledger');
        $taxfield = new xmldb_field('tax', XMLDB_TYPE_NUMBER, '10, 3', null, null, null, null, 'price');

        // Add new fields to local_shopping_cart_history table.
        if ($dbman->field_exists($table, $taxfield)) {
            $dbman->change_field_precision($table, $taxfield);
        }
        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022120701, 'local', 'shopping_cart');
    }

    if ($oldversion < 2022121500) {

        // Define field area to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'serviceperiodend');

        // Conditionally launch add field area.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field area to be added to local_shopping_cart_ledger.
        $table = new xmldb_table('local_shopping_cart_ledger');
        $field = new xmldb_field('area', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'canceluntil');

        // Conditionally launch add field area.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2022121500, 'local', 'shopping_cart');
    }

    if ($oldversion < 2023052200) {

        // Define table local_shopping_cart_id to be created.
        $table = new xmldb_table('local_shopping_cart_id');

        // Adding fields to table local_shopping_cart_id.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table local_shopping_cart_id.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_shopping_cart_id.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2023052200, 'local', 'shopping_cart');
    }

    if ($oldversion < 2023061600) {

        // Define field annotation to be added to local_shopping_cart_ledger.
        $table = new xmldb_table('local_shopping_cart_ledger');
        $field = new xmldb_field('annotation', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'area');

        // Conditionally launch add field annotation.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2023061600, 'local', 'shopping_cart');
    }

    if ($oldversion < 2023090601) { // Replace XXXXXXXXXX with the required version number.

        // Define the table structure.
        $table = new xmldb_table('local_shopping_cart_invoices');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('identifier', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('invoiceid', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Define keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('identifier', XMLDB_INDEX_UNIQUE, ['identifier']);

        // Create the table.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // MOODLE has been upgraded to the required version.
        upgrade_plugin_savepoint(true, 2023090601, 'local', 'shopping_cart');
    }

    if ($oldversion < 2023101100) {

        // Define field usecredit to be added to local_shopping_cart_history.
        $table = new xmldb_table('local_shopping_cart_history');
        $field = new xmldb_field('usecredit', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'discount');

        // Conditionally launch add field usecredit.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2023101100, 'local', 'shopping_cart');
    }

    if ($oldversion < 2023102300) {

        // Define field costcenter to be added to local_shopping_cart_ledger and to local_shopping_cart_history.
        $scledger = new xmldb_table('local_shopping_cart_ledger');
        $schistory = new xmldb_table('local_shopping_cart_history');
        $costcenter = new xmldb_field('costcenter', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'componentname');

        // Conditionally launch add field costcenter.
        if (!$dbman->field_exists($scledger, $costcenter)) {
            $dbman->add_field($scledger, $costcenter);
        }
        if (!$dbman->field_exists($schistory, $costcenter)) {
            $dbman->add_field($schistory, $costcenter);
        }
        // Shopping_cart savepoint reached.
        upgrade_plugin_savepoint(true, 2023102300, 'local', 'shopping_cart');
    }

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    return true;
}
