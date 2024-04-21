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
 * Shopping_cart_credits class for local shopping cart.
 *
 * @package     local_shopping_cart
 * @author      Georg Maißer
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use context_system;
use local_shopping_cart\local\cartstore;
use moodle_exception;
use stdClass;

/**
 * Class shopping_cart_credits.
 *
 * @author      Georg Maißer
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_credits {

    /**
     * Returns the current balance of the given user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_balance(int $userid): array {

        global $CFG, $DB;

        // Just in case, we do not find it in credits table.
        $currency = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';

        $currencies = self::credits_get_used_currencies($userid);
        if (empty($currencies)) {
            // This means, we have no entries in credits table yet.
            return [0, $currency];
        } else if (count($currencies) > 1) {
            throw new moodle_exception('nomulticurrencysupportyet', 'local_shopping_cart');
        }

        // Get the latest balance.
        if (!$balancerecord = $DB->get_record_sql("SELECT balance, currency
            FROM {local_shopping_cart_credits}
            WHERE userid = :userid
            ORDER BY id DESC
            LIMIT 1", ['userid' => $userid])) {

            $balance = 0;
        } else {
            $balance = $balancerecord->balance;
            $currency = $balancerecord->currency;
        }

        if ($CFG->debug == DEBUG_DEVELOPER) {
            // Only in developer mode, we check if balance matches with total sum of credits.
            if (!$sumofcredits = $DB->get_field_sql("SELECT SUM(credits) sumofcredits
                FROM {local_shopping_cart_credits}
                WHERE userid =:userid", ['userid' => $userid])) {

                $sumofcredits = 0;
            }
            if (round($sumofcredits, 2) != round($balance, 2)) {
                throw new moodle_exception(get_string('creditnotmatchbalance', 'local_shopping_cart', strval($userid)));
            }
        }

        return [round($balance, 2), $currency];
    }

    /**
     * This adds and changes keys of data object to account for credits and its consumption.
     * This also takes into account discounts.
     *
     * @param array $data
     * @param int $userid
     * @param int $usecredit
     * @return void
     */
    public static function prepare_checkout(array &$data, int $userid, $usecredit = null) {

        /* Decide if we want to use credit when cached value already got lost. */
        $item = !empty($data['items']) ? reset($data['items']) : null;
        if (!empty($item) && isset($item->usecredit)) {
            $usecredit = (int) $item->usecredit;
        } else {
            $usecredit = self::use_credit_fallback($usecredit, $userid);
        }

        list($balance, $currency) = self::get_balance($userid);

        // If there is no price key, we need to calculate it from items.
        if (!isset($data['price']) && isset($data['items'])) {
            $price = 0;
            foreach ($data['items'] as $item) {
                $price += $item->price;
            }
            $data['price'] = $price;
        }

        $data['initialtotal'] = $data['price'];

        // Prices can never be negative, so we use 0 in this case.

        $pricebelowzero = shopping_cart_rebookingcredit::correct_total_price_for_rebooking($data);
        $usecredit = $pricebelowzero ? 0 : $usecredit;
        $balance = $pricebelowzero ? 0 : $balance;

        if (isset($data['price_net'])) {
            $data['initialtotal_net'] = $data['price_net'];
        }

        $data['currency'] = $currency ?: $data['currency'];

        // Now we account for discounts.
        if (isset($data['discount'])) {

            // If setting to round discounts is turned on, we round to full int.
            $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;
            $data['discount'] = round($data['discount'], $discountprecision);

            $data['initialtotal'] = $data['initialtotal'] + $data['discount'];

            $context = context_system::instance();
            // Right now, only the cashier has the right to use discounts.
            if (!has_capability('local/shopping_cart:cashier', $context)) {

                $data['price'] = $data['price'] + $data['discount'];
            }
        }

        // Only if the user has any credit at all, we apply the function.
        if ($balance > 0) {

            // We always calculate the deductible.
            if ($data['price'] <= $balance) {
                $deductible = $data['price'];
            } else {
                $deductible = $balance;
            }

            // We reduce the price if we use the credit.
            if ($usecredit) {

                $remainingtotal = $data['price'] - $deductible;
                $remainingcredit = $balance - $deductible;

                $data['usecredit'] = true;

            } else {
                $remainingcredit = $balance;
                $remainingtotal = $data['price'];
            }

            $data['credit'] = round($balance, 2);
            $data['deductible'] = round($deductible, 2);
            $data['price'] = round($remainingtotal, 2);
            $data['remainingcredit'] = round($remainingcredit, 2);
            $data['checkboxid'] = bin2hex(random_bytes(3));
        }
    }

    /**
     * Adds the given credit to the current users balance.
     * This is somewhat expensive, as we always run checks on the consistency of the ledger.
     * Returns the total balance of the user.
     *
     * @param int $userid
     * @param float $credit
     * @param string $currency
     * @return array
     */
    public static function add_credit(int $userid, float $credit, string $currency): array {

        global $DB, $USER;

        list($balance, $newcurrency) = self::get_balance($userid);

        $now = time();

        $data = new stdClass();

        $data->userid = $userid;
        $data->credits = $credit;
        $data->currency = !empty($newcurrency) ? $newcurrency : $currency;
        $data->balance = $balance + $credit; // Balance hold the new balance after this transaction.
        $data->usermodified = $USER->id;
        $data->timemodified = $now;
        $data->timecreated = $now;

        if ($data->balance >= 0) {
            $DB->insert_record('local_shopping_cart_credits', $data);
        } else {
            // User cannot have a negative balance!
            throw new moodle_exception('negativebalancenotallowed');
        }

        list($newbalance, $currency) = self::get_balance($userid);

        if ($newbalance > 0) {
            // We add the right cache.

            $cartstore = cartstore::instance($userid);
            $cartstore->set_credit($newbalance, $currency);

        }

        return [$newbalance, $currency];
    }

    /**
     * This function only uses the data already calculated in prepare checkout...
     * ...and stores the result in DB.
     *
     * @param int $userid
     * @param array $checkoutdata
     * @return void
     */
    public static function use_credit(int $userid, $checkoutdata) {

        global $DB, $USER;

        $now = time();
        $data = new stdClass();

        $data->userid = $userid;
        $data->credits = -$checkoutdata['deductible'];
        $data->balance = $checkoutdata['remainingcredit']; // Balance hold the new balance after this transaction.
        $data->currency = $checkoutdata['currency'];
        $data->usermodified = $USER->id;
        $data->timemodified = $now;
        $data->timecreated = $now;

        $DB->insert_record('local_shopping_cart_credits', $data);

        $cartstore = cartstore::instance($userid);
        $cartstore->set_credit($data->balance, $data->currency);
    }

    /**
     * This function just gets the current balance and sets it to 0.
     *
     * @param int $userid
     * @param int $method
     * @return bool
     */
    public static function credit_paid_back(int $userid,
        int $method = LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH) {
        global $USER;

        list($balance, $currency) = self::get_balance($userid);

        $data = [];

        $data['deductible'] = round($balance, 2);
        $data['remainingcredit'] = 0;
        $data['currency'] = $currency;

        self::use_credit($userid, $data);

        // Also record this in the ledger table.
        $ledgerrecord = new stdClass;
        $now = time();
        $ledgerrecord->userid = $userid;
        $ledgerrecord->itemid = 0;
        $ledgerrecord->price = (float) (-1.0) * $data['deductible'];
        $ledgerrecord->credits = (float) (-1.0) * $data['deductible'];
        $ledgerrecord->currency = $currency;
        $ledgerrecord->componentname = 'local_shopping_cart';
        $ledgerrecord->payment = $method;
        $ledgerrecord->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
        $ledgerrecord->usermodified = $USER->id;
        $ledgerrecord->timemodified = $now;
        $ledgerrecord->timecreated = $now;
        shopping_cart::add_record_to_ledger_table($ledgerrecord);

        return true;
    }

    /**
     * This function calculates the price to be paid from the shopping cart, while taking account credits and usecredit status.
     *
     * @param stdClass $shoppingcart
     * @return float
     */
    public static function get_price_from_shistorycart($shoppingcart): float {

        // First we need to get the userid from the cart.
        $userid = 0;
        $currency = '';
        $data = [];
        $data['price'] = $shoppingcart->price;

        if (isset($shoppingcart->items)) {
            foreach ($shoppingcart->items as $item) {
                if (!empty($item['userid'])) {
                    $userid = $item['userid'];
                    $currency = $item['currency'];
                    break;
                }
            }
        }

        if ($userid != 0) {
            $data['currency'] = $currency;
            self::prepare_checkout($data, $userid);
        }

        return round($data['price'], 2);
    }

    /**
     * Fallback in case of undefined $usecredit to fetch from cache.
     *
     * @param bool|null $usecredit
     * @param int $userid
     * @return int
     */
    public static function use_credit_fallback($usecredit, int $userid): int {
        // If usecredit is null, we know we got the data from history.
        // Therefore, we need to get the information from cache, if we want to use the credit.
        if ($usecredit === null) {
            $tempusecredit = shopping_cart::get_saved_usecredit_state($userid);
            if ($tempusecredit === null) {
                // If nothing is saved, we fall back to true.
                $usecredit = 1;
            } else {
                $usecredit = $tempusecredit;
            }
        }
        return $usecredit;
    }

    /**
     * Helper function to check if only one currency is used.
     * Currently, we have no multicurrency support yet.
     * So this should always return an empty array or ['EUR'].
     *
     * @param int $userid
     * @return array an array of strings with currencies
     */
    public static function credits_get_used_currencies(int $userid) {
        global $DB;

        $sql = "SELECT DISTINCT currency
            FROM {local_shopping_cart_credits}
            WHERE userid = :userid";
        $params = ['userid' => $userid];

        $records = $DB->get_records_sql($sql, $params);
        $currencies = [];
        foreach ($records as $record) {
            $currencies[] = $record->currency;
        }
        return $currencies;
    }
}
