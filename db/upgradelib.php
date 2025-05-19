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
