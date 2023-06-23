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

use Exception;
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
    private $uid;

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
     * @param stdClass $data
     * @return void
     */
    public function __construct(stdClass $data = null) {
        if ($data) {
            $this->uid = $data->uid;
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

        global $DB;

        // We need this in case we load via webservice to resolve the constants.
        require_once(__DIR__ . '/../lib.php');

        // Get payment account from settings.
        $accountid = get_config('local_shopping_cart', 'accountid');
        $account = null;

        if (!empty($accountid)) {
            $account = new \core_payment\account($accountid);
        } else {
            // If we have no payment accounts then print static text instead.
            $urlobject = new stdClass;
            $urlobject->link = (new moodle_url('/payment/accounts.php'))->out(false);
            $errmsg = get_string('nopaymentaccounts', 'local_shopping_cart');
            $errmsg .= ' '.get_string('nopaymentaccountsdesc', 'local_shopping_cart', $urlobject);
            echo $errmsg;
            exit();
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
                    foreach ($cols as $key => $value) {
                        if (strpos($key, 'orderid') !== false) {
                            // Generate a select for each table.
                            // Only do this, if an orderid exists.
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
        } else {
            $gatewayspart = '';
        }

        $sql = "SELECT DISTINCT " . $DB->sql_concat("sch.id", "' - '", "COALESCE(pgw.orderid,'')") .
                " AS uniqueid,  sch.*, p.gateway$selectorderidpart
                FROM {local_shopping_cart_history} sch
                LEFT JOIN {payments} p
                ON p.itemid = sch.identifier AND p.userid=sch.userid
                $gatewayspart
                WHERE sch.userid = :userid AND sch.paymentstatus >= :paymentstatus
                ORDER BY sch.timemodified DESC";

        return $DB->get_records_sql($sql, ['userid' => $userid, 'paymentstatus' => PAYMENT_SUCCESS]);
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
     * @return bool true if the history was written to the database, false otherwise
     */
    private static function write_to_db(stdClass $data): bool {
        global $DB;

        $now = time();

        if (isset($data->items)) {
            foreach ($data->items as $item) {

                $data = (object)$item;
                $data->timecreated = $now;
                $record = $DB->insert_record('local_shopping_cart_history', $data);
                // We also need to insert the record into the ledger table.
                shopping_cart::add_record_to_ledger_table($data);
            }
        } else {
            $data->timecreated = $now;
            $record = $DB->insert_record('local_shopping_cart_history', $data);
            // We also need to insert the record into the ledger table.
            shopping_cart::add_record_to_ledger_table($data);
        }
        if ($record > 0) {
            return true;
        } else {
            return false;
        }
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
     * @param string|null $annotation
     * @return void
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
            int $paymentstatus = PAYMENT_PENDING,
            int $canceluntil = null,
            int $serviceperiodstart = 0,
            int $serviceperiodend = 0,
            float $tax = null,
            float $taxpercentage = null,
            string $taxcategory = null,
            string $annotation = null
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
        $data->usermodified = $USER->id;
        $data->timemodified = $now;
        $data->timecreated = $now;
        $data->canceluntil = $canceluntil;
        $data->tax = empty($tax) ? null : round($tax, 2);
        $data->taxpercentage = empty($taxpercentage) ? null : round($taxpercentage, 2);
        $data->taxcategory = $taxcategory;
        $data->annotation = $annotation;

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
     * @param float $credit
     * @return array
     */
    public static function cancel_purchase(int $itemid, int $userid, string $componentname, string $area, int $entryid = null,
        $credit = null): array {

        global $DB;

        // Identify record.
        if ($entryid) {
            $record = $DB->get_record('local_shopping_cart_history', ['id' => $entryid]);
        } else {
            // Only return successful payments.
            // We only take the last record.
            $sql = "SELECT *
                    FROM {local_shopping_cart_history}
                    WHERE itemid=:itemid
                    AND  userid=:userid
                    AND componentname=:componentname
                    AND area=:area
                    AND paymentstatus = " . PAYMENT_SUCCESS . "
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

        $record->paymentstatus = PAYMENT_CANCELED;
        $record->timemodified = time();

        try {
            $DB->update_record('local_shopping_cart_history', $record);

            // NOTE: Ledger entry will be inserted in shopping_cart::cancel_purchase function!

            // There might have been a credit value set manually by the cashier.
            // The credit can be the whole price, or it can be just a fraction.
            // If there is no price or the credit is higher than the price, we use the price.
            // This is to prevent malusage, where users get higher credit than they actually paid for.
            if (empty($credit)
                || ($credit > $record->price)) {
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
    public static function return_data_via_identifier(int $identifier):array {

        global $DB;
        if ($data = $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier])) {

            // If there is an error registered, we return null.
            foreach ($data as $record) {
                $aborted = false;
                if ($record->paymentstatus == PAYMENT_ABORTED) {
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
    public static function error_occured_for_identifier(int $identifier, int $userid):bool {

        global $DB;

        if (!$records = self::return_data_via_identifier($identifier, $userid)) {
            return false;
        }

        // All the items of one transaction should have the same status.
        // If it's still pending, we set all items to error.
        foreach ($records as $record) {
            // If we haven't found a record where it's not pending, we check this one.
            if ($record->paymentstatus == PAYMENT_PENDING) {
                $record->paymentstatus = PAYMENT_ABORTED;
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
    public static function set_success_in_db(array $records):bool {

        global $DB;

        $success = true;
        $identifier = null;
        $now = time();
        foreach ($records as $record) {

            $identifier = $record->identifier;
            $record->paymentstatus = PAYMENT_SUCCESS;
            $record->timemodified = $now;

            if (!$DB->update_record('local_shopping_cart_history', $record)) {
                $success = false;
            } else {
                // Only on payment success, we add a new record to the ledger table!
                unset($record->id);

                // We always use this function to add a new record to the ledger table!
                shopping_cart::add_record_to_ledger_table($record);
            }
        }

        // Clean the cache here, after successful checkout.
        $cache = \cache::make('local_shopping_cart', 'schistory');
        $cache->delete($identifier);

        return $success;
    }

    /**
     * Function prepare_data_from_cache
     *
     * @param int $userid
     * @return array
     */
    public function prepare_data_from_cache(int $userid): array {
        global $USER;
        $identifier = self::create_unique_cart_identifier($userid);
        $userfromid = $USER->id;
        $userid = $USER->id;
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';
        $dataarr = [];

        $taxesenabled = get_config('local_shopping_cart', 'enabletax') == 1;
        if ($taxesenabled) {
            $taxcategories = taxcategories::from_raw_string(
                    get_config('local_shopping_cart', 'defaulttaxcategory'),
                    get_config('local_shopping_cart', 'taxcategories')
            );
        } else {
            $taxcategories = null;
        }

        if (!$cachedrawdata = $cache->get($cachekey)) {
            return ['identifier' => ''];
        }

        $currency = '';
        if (!isset($cachedrawdata["items"])) {
            $cachedrawdata["items"] = [];
        }
        $items = shopping_cart::update_item_price_data(array_values($cachedrawdata['items']), $taxcategories);
        foreach ($items as $item) {
            $data = $item;
            $currency = $item['currency'];
            $data['expirationtime'] = $cachedrawdata["expirationdate"];
            $data['identifier'] = $identifier; // The identifier of the cart session.
            $data['usermodified'] = $userfromid; // The user who actually effected the transaction.
            $data['userid'] = $userid; // The user for which the item was bought.
            $data['payment'] = PAYMENT_METHOD_ONLINE; // This function is only used for online payment.
            $data['paymentstatus'] = PAYMENT_PENDING;
            $data['discount'] = $item['discount'] ?? null;
            $dataarr['items'][] = $data;
        }

        // As the identifier will always stay the same, we pass it here for easy acces.
        $dataarr['identifier'] = $identifier;
        if (!empty($items)) {
            $dataarr['price'] = shopping_cart::calculate_total_price($dataarr["items"]);
            if ($taxesenabled) {
                $dataarr['price_net'] = shopping_cart::calculate_total_price($dataarr["items"], true);
            }
        } else {
            $dataarr['price'] = 0.00;
        }
        $dataarr['currency'] = $currency;
        return $dataarr;
    }


    /**
     * On loading the checkout.php, the shopping cart is stored in the schistory cache.
     * This is because we don't pass the individual items, but only a total sum and description to the payment provider.
     * To identify the items in the cart, we have to store them with an identifier.
     * To avoid cluttering the table with useless data, we store it temporarily in this cache.
     *
     * @param array $dataarray
     * @return bool|null
     */
    public function store_in_schistory_cache(array $dataarray) {

        if (!isset($dataarray['identifier'])) {
            return null;
        }

        $cache = \cache::make('local_shopping_cart', 'schistory');
        $identifier = $dataarray['identifier'];
        $cache->set($identifier, $dataarray);

        return true;
    }

    /**
     * Get data from schistory cache.
     * If the flag is set, we also trigger writing to tb and set the approbiate cache flag to do it only once.
     * @param string $identifier
     * @return mixed|false
     */
    public function fetch_data_from_schistory_cache(string $identifier) {

        $cache = \cache::make('local_shopping_cart', 'schistory');

        $shoppingcart = (object)$cache->get($identifier);

        if (isset($shoppingcart->identifier) && !isset($shoppingcart->storedinhistory)) {

            self::write_to_db($shoppingcart);

            $shoppingcart->storedinhistory = true;
            $cache->set($identifier, $shoppingcart);
        } else if (!isset($shoppingcart->identifier)) {
            throw new moodle_exception('noidentifierstoredincache', 'local_shopping_cart');
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

        $uid = $DB->insert_record('local_shopping_cart_id', [
            'userid' => $userid,
            'timecreated' => time()
        ]);

        $basevalue = (int)get_config('local_shopping_cart', 'uniqueidentifier') ?? 0;

        // The base value defines the number of digits.
        $uid = $basevalue + $uid;

        // We need to keep it below 7 digits.
        if ((!empty($basevalue) && (($uid / $basevalue) > 10))) {
            throw new moodle_exception('uidistoobig', 'local_shopping_cart');
        }

        return $uid;
    }

    /**
     * Return an item for shopping card history table via the historyid.
     *
     * @param int $historyid
     * @param int $itemid
     * @param string $area
     * @param int $userid
     * @return bool|stdClass
     */
    public static function return_item_from_history(int $historyid, int $itemid, string $area, int $userid) {
        global $DB;

        return $DB->get_record('local_shopping_cart_history',
            ['id' => $historyid, 'itemid' => $itemid, 'area' => $area, 'userid' => $userid]);
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
            int $userid) {

        global $DB;

        return $DB->get_records('local_shopping_cart_history',
            ['itemid' => $itemid, 'componentname' => $component, 'area' => $area, 'userid' => $userid]);
    }
}
