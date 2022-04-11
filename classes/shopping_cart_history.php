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
     * @var int
     */
    private $componentname;

    /**
     * pending,online or cash.
     * @var string
     */
    private $paymenttype;

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
            $this->paymenttype = $data->paymenttype;
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

        $sql = "SELECT *
                FROM {local_shopping_cart_history}
                WHERE userid=:userid
                AND (payment='success'
                    OR payment='cash')";

        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Function create_history
     * @param int $userid
     * @return void
     */
    public function create_history(int $userid) {
        $prepareddata = (object)$this->prepare_data_from_cache($userid);
        self::write_to_db($prepareddata);
    }
    /**
     * write_to_db.
     *
     * @param stdClass $data
     * @return bool
     */
    private static function write_to_db(stdClass $data): bool {
        global $DB;

        $now = time();

        if (isset($data->items)) {
            foreach ($data->items as $item) {
                $data = (object)$item;
                $data->timecreated = $now;
                $DB->insert_record('local_shopping_cart_history', $data);
            }
        } else {
            $DB->insert_record('local_shopping_cart_history', $data);
        }

        return true;
    }

    /**
     * Add new entry to shopping_cart_history.
     * Use this if you add data manually, to check for validity.
     *
     * @param integer $userid
     * @param integer $itemid
     * @param string $itemname
     * @param string $price
     * @param string $currency
     * @param string $componentname
     * @param string $identifier
     * @param string $payment
     * @return integer
     */
    public static function create_entry_in_history(
            int $userid,
            int $itemid,
            string $itemname,
            string $price,
            string $currency,
            string $componentname,
            string $identifier,
            string $payment
            ) {

        global $USER;

        $data = new stdClass();
        $now = time();

        $data->userid = $userid;
        $data->itemid = $itemid;
        $data->itemname = $itemname;
        $data->price = $price;
        $data->currency = $currency;
        $data->componentname = $componentname;
        $data->identifier = $identifier;
        $data->payment = $payment;
        $data->usermodified = $USER->id;
        $data->timemodified = $now;
        $data->timecreated = $now;

        $result = self::write_to_db($data);
        return $result;
    }


    /**
     * Return data from DB via identifier.
     * This function won't return data if the payment is already aborted.
     *
     * @param integer $identifier
     * @return array
     */
    public static function return_data_via_identifier(int $identifier):array {

        global $DB;

        if ($data = $DB->get_records('local_shopping_cart_history', ['identifier' => $identifier])) {

            // If there is an error registered, we return null.
            foreach ($data as $record) {
                $aborted = false;
                if ($record->payment == 'aborted') {
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
     * @param integer $identifier
     * @return boolean
     */
    public static function error_occured_for_identifier(int $identifier):bool {

        global $DB;

        if (!$records = self::return_data_via_identifier($identifier)) {
            return false;
        }

        // All the items of one transaction should have the same status.
        // If it's still pending, we set all items to error.

        $pending = 'pending';
        foreach($records as $record) {
            // If we haven't fond a record where it's not pending, we check this one.
            if ($record->payment == 'pending') {
                $record->payment = 'aborted';
                $DB->update_record('local_shopping_cart_history', $record);
            }
        }

        return true;
    }

    /**
     * Sets the payment to success if the payment went successfully through.
     *
     * @param stdClass $records
     * @return boolean
     */
    public static function set_success_in_db(array $records):bool {

        global $DB;

        $success = true;
        $identifier = null;
        $now = time();
        foreach ($records as $record) {

            $identifier = $record->identifier;
            $record->payment = 'success';
            $record->timemodified = $now;

            if (!$DB->update_record('local_shopping_cart_history', $record)) {
                $success = false;
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

        if (!$cachedrawdata = $cache->get($cachekey)) {
            return ['identifier' => ''];
        }

        $totalprice = 0;
        $currency = '';
        foreach ($cachedrawdata["items"] as $item) {
            $data = $item;
            $totalprice += $item['price'];
            $currency = $item['currency'];
            $data['expirationtime'] = $cachedrawdata["expirationdate"];
            $data['identifier'] = $identifier; // The identifier of the cart session.
            $data['usermodified'] = $userfromid; // The user who actually effected the transaction.
            $data['userid'] = $userid; // The user for which the item was bought.]
            $data['payment'] = 'pending';
            $dataarr['items'][] = $data;
        }

        // As the identifier will always stay the same, we pass it here for easy acces.
        $dataarr['identifier'] = $identifier;
        $dataarr['price'] = $totalprice;
        $dataarr['currency'] = $currency;
        return $dataarr;
    }


    /**
     * On loading the checkout.php, the shopping cart is stored in the schistory cache.
     * This is because we don't pass the individual items, but only a total sum and description to the payment provider.
     * To identify the items in the cart, we have to store them with an identifier.
     * To avoid clutterin the table with useless data, we store it temporarily in this cache.
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
     * @param bool $writetodb
     * @return mixed|false
     */
    public function fetch_data_from_schistory_cache(string $identifier, bool $writetodb = false) {

        $cache = \cache::make('local_shopping_cart', 'schistory');

        $shoppingcart = (object)$cache->get($identifier);

        if (!isset($shoppingcart->storedinhistory)) {

            self::write_to_db($shoppingcart);

            $shoppingcart->storedinhistory = true;
            $cache->set($identifier, $shoppingcart);
        }


        return $shoppingcart;
    }

    /**
     * create_unique_cart_identifier
     * By definition, this has to be int.
     *
     * @param int $userid
     * @return string
     */
    public static function create_unique_cart_identifier(int $userid): string {
        return time();
    }


    /**
     * Validate data.
     * @return void
     */
    public function validate_data() {
        if (!isset($this->uid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['assignid'])) {
            throw new \coding_exception('The \'assignid\' value must be set in other.');
        }
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['assignid'])) {
            throw new \coding_exception('The \'assignid\' value must be set in other.');
        }
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['assignid'])) {
            throw new \coding_exception('The \'assignid\' value must be set in other.');
        }
    }
}
