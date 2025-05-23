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
 * Shopping_cart_history class for local shopping cart.
 * @package     local_shopping_cart
 * @author      Thomas Winkler
 * @copyright   2021 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\local\reservations;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use coding_exception;
use context_system;
use dml_exception;
use Exception;
use local_shopping_cart\event\payment_added;
use local_shopping_cart\local\cartstore;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Class shopping_cart_history.
 * @author      Thomas Winkler
 * @copyright   2021 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_history {
    /**
     * @var int
     */
    private $id;

    /**
     *
     * @var int
     */
    private $itemid;

    /**
     *
     * @var string
     */
    private $itemname;

    /**
     * @var string
     */
    private $componentname;

    /**
     * @var string
     */
    private $area;

    /**
     * History constructor
     * @param ?stdClass $data
     * @return void
     */
    public function __construct(?stdClass $data = null) {
        if ($data) {
            $this->itemid = $data->itemid;
            $this->itemname = $data->itemname;
            $this->componentname = $data->componentname;
            $this->area = $data->area;
        }
    }

    /**
     * Prepare submitted form data for writing to db.
     *
     * @param int $userid
     * @return array
     */
    public static function get_history_list_for_user(int $userid): array {

        global $CFG, $DB;

        $dbman = $DB->get_manager();

        // We need this in case we load via webservice to resolve the constants.
        require_once(__DIR__ . '/../lib.php');

        // Get payment account from settings.
        $accountid = get_config('local_shopping_cart', 'accountid');
        $account = null;

        if (!empty($accountid)) {
            $account = new \core_payment\account($accountid);
        } else {
            // If we have no payment accounts then print static text instead.
            $urlobject = new stdClass();
            $urlobject->link = (new moodle_url('/payment/accounts.php'))->out(false);
            $errmsg = get_string('nopaymentaccounts', 'local_shopping_cart');
            $errmsg .= ' ' . get_string('nopaymentaccountsdesc', 'local_shopping_cart', $urlobject);
            echo $errmsg;
            exit();
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

                    // For some gateways, we store a merchantref or customorderid in the openorders table.
                    $tidpart = 'tid';
                    $customorderidpart = 'NULL AS customorderid';

                    if ($dbman->table_exists($openorderstable)) {
                        $openorderscols = $DB->get_columns($openorderstable);
                        foreach ($openorderscols as $key => $value) {
                            if (strpos($key, 'merchantref') !== false) {
                                $tidpart = 'merchantref AS tid';
                            }
                            if (strpos($key, 'customorderid') !== false) {
                                $customorderidpart = 'customorderid';
                            }
                        }
                        $openorderselects[] = "SELECT itemid, '" . $gwname .
                            "' AS gateway, $tidpart, $customorderidpart
                            FROM {paygw_" . $gwname . "_openorders}";
                    }

                    $cols = $DB->get_columns($tablename);
                    foreach ($cols as $key => $value) {
                        if (strpos($key, 'orderid') !== false) {
                            // Generate a select for each table.
                            // Only do this, if an orderid exists.
                            $colselects[] =
                               "SELECT $gwname.id, $gwname.paymentid, $gwname.$key orderid
                                FROM {paygw_$gwname} $gwname";
                        }
                    }
                }
            }
        }

        // If we have open orders tables select statements, we can now UNION them.

        if (!empty($openorderselects)) {
            $ootid = "oo.tid AS ootid, ";
            $oocustomorderid = "oo.customorderid, ";
            $openorderselectsstring = implode(' UNION ', $openorderselects);
            $ootidpart = "LEFT JOIN ($openorderselectsstring) oo ON sch.identifier = oo.itemid AND oo.gateway = p.gateway";
        } else {
            // If we do not have any open orders tables, we still keep an empty custom order id column for consistency.
            $ootid = "NULL AS ootid, ";
            $oocustomorderid = "NULL AS customorderid, ";
            $ootidpart = '';
        }

        if (!empty($colselects)) {
            // Sql_cast_to_char is available since Moodle 4.1.
            if ($CFG->version > 2022112800) {
                $uniqueidpart = $DB->sql_concat(
                    "sch.id",
                    "' - '",
                    "COALESCE(" . $DB->sql_cast_to_char("p.id") . ",'X')",
                    "' - '",
                    "COALESCE(" . $DB->sql_cast_to_char("pgw.id") . ",'X')"
                );
                $uniqueidpart .= " AS uniqueid, ";
            } else {
                $uniqueidpart = $DB->sql_concat("sch.id", "' - '", "COALESCE(pgw.orderid,'') AS uniqueid, ");
            }
            $selectorderidpart = ", pgw.orderid";
            $colselectsstring = implode(' UNION ', $colselects);
            $gatewayspart = "LEFT JOIN ($colselectsstring) pgw ON p.id = pgw.paymentid";
        } else {
            // Sql_cast_to_char is available since Moodle 4.1.
            if ($CFG->version > 2022112800) {
                $uniqueidpart = $DB->sql_concat("sch.id", "' - '", "COALESCE(" . $DB->sql_cast_to_char("p.id") . ",'X')");
                $uniqueidpart .= " AS uniqueid, ";
            } else {
                $uniqueidpart = '';
            }
            $gatewayspart = '';
            $selectorderidpart = "";
        }

        $sql = "SELECT DISTINCT
                $uniqueidpart sch.*, " . $ootid . $oocustomorderid . "p.gateway$selectorderidpart
                FROM {local_shopping_cart_history} sch
                LEFT JOIN {payments} p
                ON p.itemid = sch.identifier AND p.userid=sch.userid
                $ootidpart
                $gatewayspart
                WHERE sch.userid = :userid
                AND sch.paymentstatus >= :paymentstatus
                AND NOT (sch.componentname LIKE 'local_shopping_cart' AND sch.area LIKE 'installments%' )
                ORDER BY sch.timecreated DESC";

        $records = $DB->get_records_sql($sql, ['userid' => $userid, 'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS]);

        // If the setting to show custom order IDs is turned on...
        // ... then we replace the order ID with the custom order ID.
        if (get_config('local_shopping_cart', 'cashreportshowcustomorderid')) {
            foreach ($records as &$record) {
                if (!empty($record->ootid)) {
                    $record->orderid = $record->ootid;
                }
            }
        }

        return $records;
    }

    /**
     * Returns a list of users who are booked for this option.
     *
     * @param int $optionid
     * @param string $componentname
     * @param string $area
     * @return array
     */
    public static function get_user_list_for_option(int $optionid, string $componentname, string $area) {
        global $DB;
        $sql = "SELECT sch.id, u.id as userid, u.firstname, u.lastname, u.email, sch.itemid, sch.price, sch.currency
                FROM {user} u
                JOIN {local_shopping_cart_history} sch
                ON u.id=sch.userid
                WHERE sch.itemid=:optionid
                AND sch.componentname=:componentname
                AND sch.area=:area
                AND sch.paymentstatus=2";

        $params = ['optionid' => $optionid,
                   'componentname' => $componentname,
                   'area' => $area,
                ];

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * write_to_db.
     *
     * @param stdClass $data
     * @return int true if the history was written to the database, false otherwise
     *  (e.g. if record already exists)
     */
    public static function write_to_db(stdClass $data): int {
        global $DB, $USER;

        $now = time();

        $addresses = checkout_manager::return_stored_addresses_for_user($data->userid);
        $vatnumbercountrycode = checkout_manager::return_stored_vatnuber_country_code($data->userid);

        $returnid = 0;
        if (isset($data->items)) {
            foreach ($data->items as $item) {
                $item['taxcountrycode'] = $data->taxcountrycode ?? null;
                $item['address_billing'] = $addresses["selectedaddress_billing"] ?? null;
                $item['address_shipping'] = $addresses["selectedaddress_shipping"] ?? null;
                $item['taxcountrycode'] = $vatnumbercountrycode["taxcountrycode"] ?? null;
                $item['vatnumber'] = $vatnumbercountrycode["vatnumber"] ?? null;
                if (self::write_to_db((object)$item) == 0) {
                    $returnid = 0;
                }
            }
        } else if ($data->itemid === 0) {
            /* For cash transactions, cash transfer and other entries that should
            only be logged in ledger table, we always have itemid 0. */
            shopping_cart::add_record_to_ledger_table($data);
        } else {
            if (
                !$DB->record_exists(
                    'local_shopping_cart_history',
                    [
                        'userid' => $data->userid,
                        'itemid' => $data->itemid,
                        'componentname' => $data->componentname ?? null,
                        'identifier' => $data->identifier,
                        'area' => $data->area,
                    ]
                )
            ) {
                $data->timecreated = $now;
                $data->usecredit = shopping_cart_credits::use_credit_fallback(null, $data->userid);
                if ($id = $DB->insert_record('local_shopping_cart_history', $data)) {
                    // We also need to insert the record into the ledger table.
                    // We only write the old schistoryid, if we have it.
                    $data->schistoryid = $data->schistoryid ?? $id;
                    $data->id = $id;

                    /* There is one exception, when we don't write to ledger.
                     The reason is that we want to write installments in a separate process.
                     if (!($data->componentname === 'local_shopping_cart'
                         && strpos($data->area, 'installments') !== false)) {

                         shopping_cart::add_record_to_ledger_table($data);
                     }
                    */
                    $success = true;

                    $context = context_system::instance();
                    // Trigger item deleted event.
                    $event = payment_added::create([
                        'context' => $context,
                        'userid' => $USER->id,
                        'relateduserid' => $data->userid,
                        'objectid' => $id,
                        'other' => [
                            'identifier' => $data->identifier,
                            'itemid' => $data->itemid,
                            'component' => $data->componentname,
                        ],
                    ]);

                    $event->trigger();

                    $returnid = $id;

                    // Update timestamp of deletion.
                    if (
                        !empty($additionalminutes = get_config('local_shopping_cart', 'prolongedpaymenttime'))
                    ) {
                        $time = time();
                        $additionalseconds = $additionalminutes * 60;
                        $time += $additionalseconds;
                        shopping_cart::add_or_reschedule_addhoc_tasks($time, $data->userid);
                    }
                } else {
                    $returnid = 0;
                }
            } else {
                $returnid = 0;
            }
        }
        return $returnid;
    }

    /**
     * Add new entry to shopping_cart_history.
     * Use this if you add data manually, to check for validity.
     *
     * @param int $userid
     * @param int $itemid
     * @param string $itemname
     * @param float $price
     * @param float $discount
     * @param string $currency
     * @param string $componentname
     * @param string $area
     * @param string $identifier
     * @param string $payment
     * @param int $paymentstatus
     * @param int|null $canceluntil
     * @param int|null $serviceperiodstart
     * @param int|null $serviceperiodend
     * @param float|null $tax
     * @param float|null $taxpercentage
     * @param string|null $taxcategory
     * @param string|null $costcenter
     * @param string|null $annotation
     * @param int|null $usermodified
     * @param int|null $schistoryid
     * @param int|null $installments
     * @param string|null $json
     * @param int|null $addressbilling
     * @param int|null $addressshipping
     * @param string|null $taxcountrycode
     * @param string|null $vatnumber
     *
     * @return int
     *
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function create_entry_in_history(
        int $userid,
        int $itemid,
        string $itemname,
        float $price,
        float $discount,
        string $currency,
        string $componentname,
        string $area,
        string $identifier,
        string $payment,
        int $paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_PENDING,
        ?int $canceluntil = null,
        ?int $serviceperiodstart = 0,
        ?int $serviceperiodend = 0,
        ?float $tax = null,
        ?float $taxpercentage = null,
        ?string $taxcategory = null,
        ?string $costcenter = null,
        ?string $annotation = null,
        ?int $usermodified = null,
        ?int $schistoryid = null,
        ?int $installments = null,
        ?string $json = null,
        ?int $addressbilling = 0,
        ?int $addressshipping = 0,
        ?string $taxcountrycode = null,
        ?string $vatnumber = null
    ) {

        global $USER;

        $data = new stdClass();
        $now = time();

        $data->userid = $userid;
        $data->itemid = $itemid;
        $data->itemname = $itemname;
        $data->price = $price;
        $data->discount = $discount;
        $data->currency = $currency;
        $data->componentname = $componentname;
        $data->area = $area;
        $data->identifier = $identifier;
        $data->payment = $payment;
        $data->paymentstatus = $paymentstatus;
        $data->usermodified = $usermodified ?? $USER->id;
        $data->timemodified = $now;
        $data->timecreated = $now;
        $data->canceluntil = $canceluntil;
        $data->serviceperiodstart = $serviceperiodstart;
        $data->serviceperiodend = $serviceperiodend;
        $data->tax = empty($tax) ? null : round($tax, 2);
        $data->taxpercentage = empty($taxpercentage) ? null : round($taxpercentage, 2);
        $data->taxcategory = $taxcategory;
        $data->costcenter = $costcenter;
        $data->annotation = $annotation;
        $data->schistoryid = $schistoryid;
        $data->installments = $installments;
        $data->json = $json;
        $data->address_billing = $addressbilling;
        $data->address_shipping = $addressshipping;
        $data->taxcountrycode = $taxcountrycode;
        $data->vatnumber = $vatnumber;

        return self::write_to_db($data);
    }

    /**
     * This function updates the entry in shopping cart history and sets the status to "canceled".
     *
     * @param int $itemid
     * @param int $userid
     * @param string $componentname
     * @param string $area
     * @param int|null $entryid
     * @param float|null $credit
     * @return array
     */
    public static function cancel_purchase(
        int $itemid,
        int $userid,
        string $componentname,
        string $area,
        ?int $entryid = null,
        ?float $credit = null
    ): array {

        global $DB;

        // Identify record.
        if ($entryid) {
            $record = $DB->get_record('local_shopping_cart_history', [
                'id' => $entryid,
                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            ]);
        } else {
            // Only return successful payments.
            // We only take the last record.
            $sql = "SELECT *
                    FROM {local_shopping_cart_history}
                    WHERE itemid=:itemid
                    AND  userid=:userid
                    AND componentname=:componentname
                    AND area=:area
                    AND paymentstatus = " . LOCAL_SHOPPING_CART_PAYMENT_SUCCESS . "
                    ORDER BY timemodified
                    LIMIT 1";

            $params = ['itemid' => $itemid,
                       'userid' => $userid,
                        'componentname' => $componentname,
                        'area' => $area,
            ];
            $record = $DB->get_record_sql($sql, $params);
        }

        if (!$record) {
            return [0, 'noentryinhistoryfound'];
        }

        // Now update found record as canceled.

        $record->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_CANCELED;
        $record->timemodified = time();

        try {
            $DB->update_record('local_shopping_cart_history', $record);

            // NOTE: Ledger entry will be inserted in shopping_cart::cancel_purchase function!

            // There might have been a credit value set manually by the cashier.
            // The credit can be the whole price, or it can be just a fraction.
            // If there is no price or the credit is higher than the price, we use the price.
            // This is to prevent malusage, where users get higher credit than they actually paid for.
            if (
                empty($credit)
                || ($credit > $record->price)
            ) {
                return [1, '', $record->price, $record->currency, $record];
            } else {
                // If the credit is smaller than the price, we use the credit.
                return [1, '', $credit, $record->currency, $record];
            }
        } catch (Exception $e) {
            return [0, 'failureduringupdateofentry'];
        }
    }

    /**
     * Return data from DB via identifier.
     * This function won't return data if the payment is already aborted.
     *
     * @param int $identifier
     * @return array
     */
    public static function return_data_via_identifier(int $identifier): array {
        global $DB;
        if ($data = $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier])) {
            // If there is an error registered, we return null.
            foreach ($data as $record) {
                $aborted = false;
                // Status LOCAL_SHOPPING_CART_PAYMENT_ABORTED is 1. Fails in adhoc task if constant is used. Weird.
                if ($record->paymentstatus == 1) {
                    $aborted = true;
                }
            }
            if ($aborted) {
                return [];
            }

            return $data;
        }

        return [];
    }

    /**
     * Return ledger data from DB via identifier (cash report data).
     * This function won't return data if the payment is already aborted.
     *
     * @param int $identifier
     * @return array
     */
    public static function return_data_from_ledger_via_identifier(int $identifier): array {
        global $DB;
        if (
            $data = $DB->get_records('local_shopping_cart_ledger', ['identifier' => $identifier])
        ) {
            // If there is an error registered, we return null.
            foreach ($data as $record) {
                $aborted = false;
                // Status LOCAL_SHOPPING_CART_PAYMENT_ABORTED is 1. Fails in adhoc task if constant is used. Weird.
                if ($record->paymentstatus == 1) {
                    $aborted = true;
                }
            }
            if ($aborted) {
                return [];
            }
            return $data;
        }
        return [];
    }

    /**
     * Return extra ledger data from DB via userid
     *
     * @param int $userid
     * @return array
     */
    public static function return_extra_lines_from_ledger(int $userid): array {
        global $DB;

        [$inorequal, $params] = $DB->get_in_or_equal(
            [
                LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION,
                LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH,
                LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER,
                LOCAL_SHOPPING_CART_PAYMENT_METHOD_REBOOKING_CREDITS_CORRECTION,
            ],
            SQL_PARAMS_NAMED
        );

        $sql = "SELECT *
                FROM {local_shopping_cart_ledger} scl
                WHERE scl.userid = :userid
                AND scl.payment $inorequal
                ";
        $params['userid'] = $userid;
        if (
            $data = $DB->get_records_sql($sql, $params)
        ) {
            return $data;
        }
        return [];
    }

    /**
     * Return ledger data from DB via ledger id (cash report data).
     * This function won't return data if the payment is already aborted.
     *
     * @param int $id
     * @return array
     */
    public static function return_data_from_ledger_via_id(int $id): array {
        global $DB;
        if (
            $data = $DB->get_records('local_shopping_cart_ledger', ['id' => $id])
        ) {
            // If there is an error registered, we return null.
            foreach ($data as $record) {
                $aborted = false;
                // Status LOCAL_SHOPPING_CART_PAYMENT_ABORTED is 1. Fails in adhoc task if constant is used. Weird.
                if ($record->paymentstatus == 1) {
                    $aborted = true;
                }
            }
            if ($aborted) {
                return [];
            }
            return $data;
        }
        return [];
    }

    /**
     * Sets the cart item to payment => 'aborted' if it was still pending.
     * Won't change other status.
     *
     * @param int $identifier
     * @param int $userid
     * @return bool
     */
    public static function error_occured_for_identifier(int $identifier, int $userid): bool {

        global $DB;

        if (!$records = self::return_data_via_identifier($identifier)) {
            return false;
        }

        // All the items of one transaction should have the same status.
        // If it's still pending, we set all items to error.
        foreach ($records as $record) {
            // If we haven't found a record where it's not pending, we check this one.
            if ($record->paymentstatus == LOCAL_SHOPPING_CART_PAYMENT_PENDING) {
                $record->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_ABORTED;
                $record->timemodified = time();
                $DB->update_record('local_shopping_cart_history', $record);
            }
        }

        return true;
    }

    /**
     * Sets the payment to success if the payment went successfully through.
     *
     * @param array $records
     * @return bool
     */
    public static function set_success_in_db(array $records): bool {

        global $DB, $USER;

        $success = true;
        $identifier = null;
        $now = time();
        foreach ($records as $record) {
            $identifier = $record->identifier;
            $record->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
            $record->timemodified = $now;

            $areaarray = explode('-', $record->area);
            $area = $areaarray[0];
            $addinfo = $areaarray[1] ?? null;

            if (
                $record->componentname === 'local_shopping_cart'
                && $area === 'installments'
            ) {
                // GH-94: Fix paymentstatus for installments in shopping cart history.
                $updaterecord = new stdClass();
                $updaterecord->id = $record->id;
                $updaterecord->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
                $updaterecord->timemodified = $record->timemodified;
                $DB->update_record('local_shopping_cart_history', $updaterecord);

                // We retrieve the item from history and update it for the installments.
                $historyitem = self::return_item_from_history($record->itemid);

                // Now we manipulate our entry to have a correct ledger.
                $ledgerrecord = $record;
                $ledgerrecord->itemid = $historyitem->itemid;
                $ledgerrecord->area = $historyitem->area;
                $ledgerrecord->componentname = $historyitem->componentname;
                $ledgerrecord->usermodified = $USER->id;
                $ledgerrecord->timecreated = $now;

                // Get Information about the current payment.
                $jsonobject = json_decode($historyitem->json);
                foreach ($jsonobject->installments->payments as $key => $payment) {
                    if ($payment->id == $addinfo) {
                        $jsonobject->installments->payments[$key]->paid = 1;
                        $a = $payment; // This is the payment which is currently treated.
                    }
                }

                // Still some additional Info for the ledger.
                $ledgerrecord->schistoryid = $historyitem->id;
                $ledgerrecord->annotation =
                    get_string('ledgerinstallment', 'local_shopping_cart', $a);

                // Now we manipulate the orignal entry.
                $newrecord = $historyitem;
                $newrecord->price += $record->price;
                $newrecord->tax += $record->tax;
                $newrecord->timemodified = $record->timemodified;
                $newrecord->installments--;
                $newrecord->paymentstatus = $record->paymentstatus;
                $newrecord->json = json_encode($jsonobject);

                $record = $newrecord;
            } else {
                $record->schistoryid = $record->id;
            }

            // If until now we have no ledger record, we duplicate from record.
            if (empty($ledgerrecord)) {
                $ledgerrecord = $record;
            }

            if (empty($record->id)) {
                if (!$DB->insert_record('local_shopping_cart_history', $record)) {
                    $success = false;
                }
            } else if (!$DB->update_record('local_shopping_cart_history', $record)) {
                $success = false;
            }

            if ($success) {
                // Only on payment success, we add a new record to the ledger table!
                unset($ledgerrecord->id);

                // We always use this function to add a new record to the ledger table!
                shopping_cart::add_record_to_ledger_table($ledgerrecord);
            }
        }

        // Clean the cache here, after successful checkout.
        $cache = \cache::make('local_shopping_cart', 'schistory');
        $cache->delete('schistorycache');

        return $success;
    }

    /**
     * On loading the checkout.php, the shopping cart is stored in the schistory cache.
     * This is because we don't pass the individual items, but only a total sum and description to the payment provider.
     * To identify the items in the cart, we have to store them with an identifier.
     * To avoid cluttering the table with useless data, we store it temporarily in this cache.
     *
     * @param array $data
     * @return bool|null
     */
    public static function store_in_schistory_cache(array $data) {

        if (!isset($data['identifier'])) {
            return null;
        }

        $cache = \cache::make('local_shopping_cart', 'schistory');
        $cache->set('schistorycache', $data);

        return true;
    }

    /**
     * Get data from schistory cache.
     * @param string $identifier
     * @return mixed|false
     */
    public static function fetch_data_from_schistory_cache(string $identifier) {

        global $USER;

        $cache = \cache::make('local_shopping_cart', 'schistory');
        $shoppingcart = $cache->get('schistorycache');

        // If we have no valid cache, we need to get the information via the identifier.
        if (!$shoppingcart) {
            $nocache = true;
            $shoppingcart = reservations::get_json_from_db_via_identifier($identifier);

            if (empty($shoppingcart)) {
                throw new moodle_exception('noshoppingcartfound', 'local_shopping_cart');
            }
        } else if (
            // If there is a cart but it has the wrong identifier, we need to restore the cart from the database.
            isset($shoppingcart['identifier'])
            && $shoppingcart['identifier'] != $identifier
        ) {
            $shoppingcart = reservations::get_json_from_db_via_identifier($identifier);
            $shoppingcart['storedinhistory'] = true;
            self::store_in_schistory_cache($shoppingcart);
        } else if (reservations::different_cart_with_same_identifier($shoppingcart, $identifier, true)) {
            // The cart must still correspond to the identifier we first used.
            // If there is nothing stored, we add it here.
            $shoppingcart = reservations::get_json_from_db_via_identifier($identifier);
            $shoppingcart['storedinhistory'] = true;
            self::store_in_schistory_cache($shoppingcart);
        }

        // This should, in every case, only happen once.
        if (isset($shoppingcart['identifier']) && !isset($shoppingcart['storedinhistory'])) {
            self::write_to_db((object)$shoppingcart);
            // Here we are before checkout.
            $expirationtime = shopping_cart::get_expirationtime();

            // Add or reschedule all delete_item_tasks for all the items in the cart.
            shopping_cart::add_or_reschedule_addhoc_tasks($expirationtime, $USER->id);

            $shoppingcart['storedinhistory'] = true;
            self::store_in_schistory_cache($shoppingcart);
        } else if (!isset($shoppingcart['identifier'])) {
            throw new moodle_exception('noidentifierfound', 'local_shopping_cart');
        }

        return $shoppingcart;
    }

    /**
     * create_unique_cart_identifier
     * By definition, this has to be int.
     * To be really sure of uniqueness, we use a a dedicated table.
     * Also, we throw error if the identifier is too big.
     *
     * @param int $userid
     * @return int
     */
    public static function create_unique_cart_identifier(int $userid): int {

        global $DB;

        $vatnr = $DB->insert_record('local_shopping_cart_id', [
            'userid' => $userid,
            'timecreated' => time(),
        ]);

        $basevalue = (int)get_config('local_shopping_cart', 'uniqueidentifier') ?? 0;

        // The base value defines the number of digits.
        $vatnr = $basevalue + $vatnr;

        // We need to keep it below 7 digits.
        if ((!empty($basevalue) && (($vatnr / $basevalue) > 10))) {
            throw new moodle_exception('vatnristoobig', 'local_shopping_cart');
        }

        return $vatnr;
    }

    /**
     * Return an item for shopping card history table via the historyid.
     *
     * @param int $historyid
     * @return bool|stdClass
     */
    public static function return_item_from_history(int $historyid) {
        global $DB;

        return $DB->get_record(
            'local_shopping_cart_history',
            [
                'id' => $historyid,
            ]
        );
    }

    /**
     * Returns the most recent uncancelled history item.
     * @param string $componentname
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return stdClass
     * @throws dml_exception
     */
    public static function get_most_recent_historyitem(string $componentname, string $area, int $itemid, int $userid) {
        global $DB;

        $record = $DB->get_record(
            'local_shopping_cart_history',
            [
                'componentname' => $componentname,
                'area' => $area,
                'itemid' => $itemid,
                'userid' => $userid,
                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            ]
        );

        if (!empty($record)) {
            return $record;
        }

        return (object)[];
    }

    /**
     * Returns items from shopping card history table.
     * We might have bought the same item multiple times (because of cancelation).
     *
     * @param int $itemid
     * @param string $component
     * @param string $area
     * @param int $userid
     * @return array
     */
    public static function return_items_from_history(
        int $itemid,
        string $component,
        string $area,
        int $userid
    ) {

        global $DB;

        return $DB->get_records(
            'local_shopping_cart_history',
            [
                'itemid' => $itemid,
                'componentname' => $component,
                'area' => $area,
                'userid' => $userid,
            ]
        );
    }

    /**
     * Marks an item for rebooking.
     * @param int $historyid
     * @param int $userid
     * @param ?bool $remove can be set to true, if we already know that we remove
     * @return array
     */
    public static function toggle_mark_for_rebooking(int $historyid, int $userid, ?bool $remove = null): array {

        $userid = shopping_cart::set_user($userid);

        $cachekey = 'rebook_userid_' . $userid;
        $cache = \cache::make('local_shopping_cart', 'cacherebooking');

        $marked = 1;
        // First, we see if we have sth in the cache.
        if (
            (($itemstorebook = $cache->get($cachekey))
            && is_array($itemstorebook)) || $remove
        ) {
            if (in_array($historyid, $itemstorebook) || $remove) {
                $itemstorebook = array_filter($itemstorebook, fn($a) => $a != $historyid);
                $marked = 0;
                shopping_cart::delete_item_from_cart('local_shopping_cart', 'rebookitem', $historyid, $userid);

                $cartstore = cartstore::instance($userid);

                if (!$cartstore->is_rebooking()) {
                    $cartstore->delete_rebookingfee();
                }
            } else {
                // If so, decide if a add or remove.
                $itemstorebook[] = $historyid;
            }
        } else {
            $itemstorebook = [$historyid];
        }

        $cache->set($cachekey, $itemstorebook);

        if (!empty($marked) && empty($remove)) {
            $response = shopping_cart::add_item_to_cart('local_shopping_cart', 'rebookitem', $historyid, $userid);

            if ($response['success'] === LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS) {
                // Before we add the item to the cart, let's make sure there is no booking fee currently applied.
                shopping_cart_rebookingcredit::delete_booking_fee($userid);

                if (get_config('local_shopping_cart', 'rebookingfee') > 0) {
                    // We check if we need to add the rebookingfee.
                    $record = self::return_item_from_history($historyid);
                    $now = time();

                    if ($record->canceluntil <= $now) {
                        shopping_cart::add_item_to_cart('local_shopping_cart', 'rebookingfee', 1, $userid);
                    }
                }
            } else {
                // Remove the toggle again, because it was not successful.
                self::toggle_mark_for_rebooking($historyid, $userid, true);
            }
        }

        // Else we return the toggled value.
        return ['marked' => $marked];
    }

    /**
     * Return true or false, depending on item.
     * @param int $historyid
     * @param int $userid
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function is_marked_for_rebooking(int $historyid, int $userid): bool {

        $cachekey = 'rebook_userid_' . $userid;
        $cache = \cache::make('local_shopping_cart', 'cacherebooking');

        if (
            ($markeditems = $cache->get($cachekey))
            && is_array($markeditems)
            && in_array($historyid, $markeditems)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Gets list of items to rebook.
     * @param mixed $userid
     * @return array|void
     * @throws coding_exception
     */
    public static function return_list_of_items_to_rebook($userid) {

        global $DB;

        $cachekey = 'rebook_userid_' . $userid;
        $cache = \cache::make('local_shopping_cart', 'cacherebooking');

        if (
            ($markeditems = $cache->get($cachekey))
            && is_array($markeditems)
        ) {
            [$inorequal, $params] = $DB->get_in_or_equal($markeditems, SQL_PARAMS_NAMED);
            $sql = "SELECT *
                    FROM {local_shopping_cart_history}
                    WHERE id $inorequal";

            $records = $DB->get_record_sql($sql, $params);

            return $records;
        } else {
            return [];
        }
    }

    /**
     * Check for successful checkout via identifier.
     * @param int $identifier
     * @return bool
     */
    public static function has_successful_checkout(int $identifier) {
        // Make sure we actually have a success.
        $success = false;
        if ($records = self::return_data_via_identifier($identifier)) {
            foreach ($records as $record) {
                if (LOCAL_SHOPPING_CART_PAYMENT_SUCCESS == $record->paymentstatus) {
                    $success = true;
                } else {
                    $success = false;
                }
            }
        }
        return $success;
    }

    /**
     * Get installmentdata.
     * @param object $shistoryitem
     * @return array
     */
    public static function get_installmentdata($shistoryitem): array {
        global $DB;
            // If installments are given, modify the informations.
        if (empty($shistoryitem) || empty($shistoryitem->installments)) {
            return [];
        }
        $data = json_decode($shistoryitem->json);
        if (!isset($data->installments)) {
            return [];
        }
        return (array) $data->installments;
    }
}
