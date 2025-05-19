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
 * Privacy subsystem implementation for local_shopping_cart
 *
 * @package    local_shopping_cart
 * @copyright  Wunderbyte <info@wunderbyte.at>
 * @author     Thomas Winkler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_shopping_cart\privacy;

use coding_exception;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_system;
use dml_exception;
use moodle_exception;

/**
 * Privacy subsystem implementation for local_shopping_cart
 *
 * @package    local_shopping_cart
 * @copyright  Wunderbyte <info@wunderbyte.at>
 * @author     Thomas Winkler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider,
                          \core_privacy\local\request\plugin\provider,
                          \core_privacy\local\request\core_userlist_provider {

    /**
     * Get data from tables.
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_shopping_cart_history',
            [
                'userid' => 'privacy:metadata:local_shopping_cart_history:userid',
                'itemid' => 'privacy:metadata:local_shopping_cart_history:itemid',
                'itemname' => 'privacy:metadata:local_shopping_cart_history:itemname',
                'price' => 'privacy:metadata:local_shopping_cart_history:price',
                'tax' => 'privacy:metadata:local_shopping_cart_history:tax',
                'taxpercentage' => 'privacy:metadata:local_shopping_cart_history:taxpercentage',
                'discount' => 'privacy:metadata:local_shopping_cart_history:discount',
                'currency' => 'privacy:metadata:local_shopping_cart_history:currency',
                'componentname' => 'privacy:metadata:local_shopping_cart_history:componentname',
                'identifier' => 'privacy:metadata:local_shopping_cart_history:identifier',
                'payment' => 'privacy:metadata:local_shopping_cart_history:payment',
                'paymentstatus' => 'privacy:metadata:local_shopping_cart_history:paymentstatus',
                'usermodified' => 'privacy:metadata:local_shopping_cart_history:usermodified',
                'timecreated' => 'privacy:metadata:local_shopping_cart_history:timecreated',
                'canceluntil' => 'privacy:metadata:local_shopping_cart_history:canceluntil',
                'serviceperiodstart' => 'privacy:metadata:local_shopping_cart_history:serviceperiodstart',
                'serviceperiodend' => 'privacy:metadata:local_shopping_cart_history:serviceperiodend',
                'area' => 'privacy:metadata:local_shopping_cart_history:area',
                'usecredit' => 'privacy:metadata:local_shopping_cart_history:usecredit',
                'costcenter' => 'privacy:metadata:local_shopping_cart_history:costcenter',
            ],
            'privacy:metadata:local_shopping_cart_history'
        );

        $collection->add_database_table(
            'local_shopping_cart_credits',
            [
                'userid' => 'privacy:metadata:local_shopping_cart_history:userid',
                'credits' => 'privacy:metadata:local_shopping_cart_history:credits',
                'currency' => 'privacy:metadata:local_shopping_cart_history:currency',
                'balance' => 'privacy:metadata:local_shopping_cart_history:balance',
                'usermodified' => 'privacy:metadata:local_shopping_cart_history:usermodified',
                'timecreated' => 'privacy:metadata:local_shopping_cart_history:timecreated',
                'timemodified' => 'privacy:metadata:local_shopping_cart_history:timemodified',
            ],
            'privacy:metadata:local_shopping_cart_credits'
        );

        $collection->add_database_table(
            'local_shopping_cart_ledger',
            [
                'userid' => 'privacy:metadata:local_shopping_cart_history:userid',
                'itemid' => 'privacy:metadata:local_shopping_cart_history:itemid',
                'itemname' => 'privacy:metadata:local_shopping_cart_history:itemname',
                'price' => 'privacy:metadata:local_shopping_cart_history:price',
                'tax' => 'privacy:metadata:local_shopping_cart_history:tax',
                'taxpercentage' => 'privacy:metadata:local_shopping_cart_history:taxpercentage',
                'taxcategory' => 'privacy:metadata:local_shopping_cart_history:taxcategory',
                'discount' => 'privacy:metadata:local_shopping_cart_history:discount',
                'credits' => 'privacy:metadata:local_shopping_cart_history:credits',
                'fee' => 'privacy:metadata:local_shopping_cart_history:fee',
                'currency' => 'privacy:metadata:local_shopping_cart_history:currency',
                'componentname' => 'privacy:metadata:local_shopping_cart_history:componentname',
                'identifier' => 'privacy:metadata:local_shopping_cart_history:identifier',
                'payment' => 'privacy:metadata:local_shopping_cart_history:payment',
                'paymentstatus' => 'privacy:metadata:local_shopping_cart_history:paymentstatus',
                'usermodified' => 'privacy:metadata:local_shopping_cart_history:usermodified',
                'timemodified' => 'privacy:metadata:local_shopping_cart_history:timemodified',
                'timecreated' => 'privacy:metadata:local_shopping_cart_history:timecreated',
                'canceluntil' => 'privacy:metadata:local_shopping_cart_history:canceluntil',
                'area' => 'privacy:metadata:local_shopping_cart_history:area',
                'annotation' => 'privacy:metadata:local_shopping_cart_history:annotiation',
                'costcenter' => 'privacy:metadata:local_shopping_cart_history:costcenter',
            ],
            'privacy:metadata:local_shopping_cart_ledger'
        );

        $collection->add_database_table(
            'local_shopping_cart_invoices',
            [
                'identifier' => 'privacy:metadata:local_shopping_cart_history:identifier',
                'timecreated' => 'privacy:metadata:local_shopping_cart_history:timecreated',
                'invoiceid' => 'privacy:metadata:local_shopping_cart_history:invoiceid',
            ],
            'privacy:metadata:local_shopping_cart_invoices'
        );

        return $collection;
    }

    /**
     * Shopping cart only has context system.
     * @param int $userid
     * @return contextlist
     * @throws dml_exception
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "
            SELECT ctx.id
            FROM {context} ctx
            WHERE ctx.instanceid = :instanceid
            ";
        $contextlist->add_from_sql($sql, ['instanceid' => 0]);

        return $contextlist;
    }

    /**
     * Export all user data.
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        // Check if there is at least one record.
        if (!$record = $DB->get_records('local_shopping_cart_history', ['userid' => $user->id])) {
            return;
        }

        $path = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue; // Only interested in course context.
            }

            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object)array_merge((array)$contextdata, []);
            writer::with_context($context)->export_data($path, $contextdata);
        }

        self::export_all_historyitems($contextlist);
        self::export_all_ledgeritems($contextlist);
        self::export_all_creditsitems($contextlist);
    }

    /**
     * This will delete all the data, because we only have one context.
     * Therefore, we can just clear all the tables.
     * @param context $context
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if (!$context) {
            return;
        }
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_shopping_cart_history');
        $DB->delete_records('local_shopping_cart_credits');

        // Ledger and invoices should never be deleted, regardless what.
        $DB->delete_records('local_shopping_cart_ledger');
        $DB->delete_records('local_shopping_cart_invoices');
    }

    /**
     * Delete userdata.
     * @param approved_contextlist $contextlist
     * @return void
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        $user = $contextlist->get_user();
        // Check if there is at least one record.
        if (!$records = $DB->get_records('local_shopping_cart_history', ['userid' => $user->id])) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue; // Only interested in the course context.
            }

            $DB->delete_records('local_shopping_cart_history', ['userid' => $user->id]);
            $DB->delete_records('local_shopping_cart_credits', ['userid' => $user->id]);

            foreach ($records as $record) {
                // Delete identifier.
                $DB->delete_records('local_shopping_cart_invoices', ['identifier' => $record->identifier]);
            }

            if (!empty(get_config('local_shopping_cart', 'deleteledger'))) {
                $DB->delete_records('local_shopping_cart_leger', ['userid' => $user->id]);
            }
        }
    }

    /**
     * Get userdata in context.
     * @param userlist $userlist
     * @return void
     * @throws dml_exception
     */
    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $sql = "
            SELECT sch.userid
            FROM {local_shopping_cart_history} sch
            ";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Delete data for users.
     * @param approved_userlist $userlist
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        if (!$userids = $userlist->get_userids()) {
            return;
        }
        list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $sql = "
            SELECT sch.identifier
            FROM {local_shopping_cart_history} sch
            WHERE sch.userid $usql
            ";

        if ($identifiers = $DB->get_fieldset_sql($sql, $params)) {
            $DB->delete_records_list('local_shopping_cart_invoices', 'identifier', $identifiers);
        }

        $DB->delete_records_list('local_shopping_cart_history', 'userid', $userids);
        $DB->delete_records_list('local_shopping_cart_credits', 'userid', $userids);

        if (!empty(get_config('local_shopping_cart', 'deleteledger'))) {
            $DB->delete_records_list('local_shopping_cart_ledger', 'userid', $userids);
        }
    }

    /**
     * Export all historyitems.
     * @param mixed $contextlist
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    private static function export_all_historyitems($contextlist) {

        global $DB;

        $user = $contextlist->get_user();

        $context = context_system::instance();
        $writer = \core_privacy\local\request\writer::with_context($context);

        $sql = "
            SELECT sch.*, u.firstname, u.lastname
            FROM {local_shopping_cart_history} sch
            JOIN {user} u ON sch.userid=u.id
            WHERE sch.userid = :userid
            ";
        $params = [
            'userid' => $user->id,
        ];

        $schout = [];
        foreach ($DB->get_records_sql($sql, $params) as $sch) {

            $subcontext = [
                get_string('pluginname', 'local_shopping_cart'),
                get_string('history', 'local_shopping_cart'),
                $sch->itemname];

            $schout = (object)[
                'userid' => "$sch->userid $sch->firstname $sch->lastname",
                'itemid' => $sch->itemid,
                'itemname' => $sch->itemname,
                'price' => $sch->price,
                'tax' => $sch->tax,
                'taxpercentage' => $sch->taxpercentage,
                'discount' => $sch->discount,
                'currency' => $sch->currency,
                'componentname' => $sch->componentname,
                'identifier' => $sch->identifier,
                'payment' => $sch->payment,
                'paymentstatus' => $sch->paymentstatus,
                'usermodified' => $sch->usermodified,
                'timecreated' => empty($sch->timecreated) ? 'not set' : userdate($sch->timecreated),
                'timemodified' => empty($sch->timemodified) ? 'not set' : userdate($sch->timemodified),
                'canceluntil' => empty($sch->canceluntil) ? 'not set' : userdate($sch->canceluntil),
                'serviceperiodstart' => empty($sch->serviceperiodstart) ? 'not set' : userdate($sch->serviceperiodstart),
                'serviceperiodend' => empty($sch->serviceperiodend) ? 'not set' : userdate($sch->serviceperiodend),
                'area' => $sch->area,
                'usecredit' => $sch->usecredit,
                'costcenter' => $sch->costcenter,
            ];

            $writer->export_data($subcontext, $schout);
        }
    }

    /**
     * Export all ledger items.
     * @param mixed $contextlist
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    private static function export_all_ledgeritems($contextlist) {

        global $DB;

        $user = $contextlist->get_user();

        $context = context_system::instance();
        $writer = \core_privacy\local\request\writer::with_context($context);

        $sql = "
            SELECT scl.*, sci.invoiceid, u.firstname, u.lastname
            FROM {local_shopping_cart_ledger} scl
            LEFT JOIN {local_shopping_cart_invoices} sci ON scl.identifier=sci.identifier
            JOIN {user} u ON scl.userid=u.id
            WHERE scl.userid = :userid
            ";
        $params = [
            'userid' => $user->id,
        ];

        $sclout = [];
        foreach ($DB->get_records_sql($sql, $params) as $scl) {

            $subcontext = [
                get_string('pluginname', 'local_shopping_cart'),
                get_string('ledger', 'local_shopping_cart'),
                !empty($scl->itemname) ? $scl->itemname : "Credits transfer: $scl->id Credits $scl->credits"];

            $sclout = (object)[
                'userid' => "$scl->userid $scl->firstname $scl->lastname",
                'itemid' => $scl->itemid,
                'itemname' => $scl->itemname,
                'price' => $scl->price,
                'tax' => $scl->tax,
                'taxpercentage' => $scl->taxpercentage,
                'taxcategory' => $scl->taxcategory,
                'discount' => $scl->discount,
                'credits' => $scl->credits,
                'fee' => $scl->fee,
                'currency' => $scl->currency,
                'componentname' => $scl->componentname,
                'identifier' => $scl->identifier,
                'payment' => $scl->payment,
                'paymentstatus' => $scl->paymentstatus,
                'accountid' => $scl->accountid,
                'usermodified' => $scl->usermodified,
                'timecreated' => empty($scl->timecreated) ? 'not set' : userdate($scl->timecreated),
                'timemodified' => empty($scl->timemodified) ? 'not set' : userdate($scl->timemodified),
                'canceluntil' => $scl->canceluntil,
                'area' => $scl->area,
                'annotation' => $scl->annotation,
                'costcenter' => $scl->costcenter,
                'invoiceid' => $scl->invoiceid,
            ];

            $writer->export_data($subcontext, $sclout);
        }
    }

    /**
     * Export all credit items.
     * @param mixed $contextlist
     * @return void
     * @throws dml_exception
     * @throws coding_exception
     */
    private static function export_all_creditsitems($contextlist) {

        global $DB;

        $user = $contextlist->get_user();

        $context = context_system::instance();
        $writer = \core_privacy\local\request\writer::with_context($context);

        $sql = "
                SELECT sch.*, u.firstname, u.lastname
                FROM {local_shopping_cart_credits} sch
                JOIN {user} u ON sch.userid=u.id
                WHERE sch.userid = :userid
                ";
        $params = [
            'userid' => $user->id,
        ];

        $schout = [];
        foreach ($DB->get_records_sql($sql, $params) as $scc) {

            $subcontext = [
                get_string('pluginname', 'local_shopping_cart'),
                get_string('credits', 'local_shopping_cart'),
                $scc->id];

            $schout = (object)[
                'userid' => "$scc->userid $scc->firstname $scc->lastname",
                'credits' => $scc->credits,
                'currency' => $scc->currency,
                'balance' => $scc->balance,
                'usermodified' => $scc->usermodified,
                'timecreated' => empty($scc->timecreated) ? 'not set' : userdate($scc->timecreated),
                'timemodified' => empty($scc->timemodified) ? 'not set' : userdate($scc->timemodified),
            ];
            $writer->export_data($subcontext, $schout);
        }
    }
}
