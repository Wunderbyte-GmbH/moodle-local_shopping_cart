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

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    return true;
}
