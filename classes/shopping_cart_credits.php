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
     * @param string $costcenter
     * @return array
     */
    public static function get_balance(int $userid, string $costcenter = ''): array {

        global $CFG, $DB;

        // Just in case, we do not find it in credits table.
        $currency = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';
        $samecostcenterforcredits = get_config('local_shopping_cart', 'samecostcenterforcredits') ?? 0;

        $currencies = self::credits_get_used_currencies($userid);
        if (empty($currencies)) {
            // This means, we have no entries in credits table yet.
            return [0, $currency];
        } else if (count($currencies) > 1) {
            throw new moodle_exception('nomulticurrencysupportyet', 'local_shopping_cart');
        }

        $params = [
            'userid1' => $userid,
            'userid2' => $userid,
        ];
        $additionalsql = " COALESCE(NULLIF(costcenter, ''), '') = :costcenter ";
        $params['costcenter'] = $costcenter;
        if (!empty($samecostcenterforcredits)) {
            $defaultcostcenter = get_config('local_shopping_cart', 'defaultcostcenterforcredits');
            if (empty($defaultcostcenter) || $defaultcostcenter == $costcenter) {
                $defaultcostcentersql = " OR COALESCE(NULLIF(costcenter, ''), '') = '' ";
            } else {
                $defaultcostcentersql = '';
            }
        } else {
            $defaultcostcentersql = '';
        }

        $sql = 'SELECT SUM(balance) AS balance, MAX(currency) as currency
                FROM {local_shopping_cart_credits}
                WHERE userid = :userid1
                AND id IN (
                    SELECT MAX(id)
                    FROM {local_shopping_cart_credits}
                    WHERE userid = :userid2
                    AND ( ' . $additionalsql .
                        $defaultcostcentersql . ' )
                    GROUP BY COALESCE(NULLIF(costcenter, \'\'), \'nocostcenter\')
                )';

        // Get the latest balance of the given costcenter.
        if (!$balancerecord = $DB->get_record_sql($sql, $params)) {
            $balance = 0;
        } else {
            $balance = $balancerecord->balance ?? 0;
            $currency = $balancerecord->currency ?? 0;
        }

        return [round($balance, 2), $currency];
    }


    /**
     * Returns the current balance of the given user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_balance_for_all_costcenters(int $userid): array {

        global $CFG, $DB;

        $params = [
            'userid' => $userid,
            'userid1' => $userid,
        ];
        $sql = 'SELECT id, balance, currency, costcenter
                FROM {local_shopping_cart_credits}
                WHERE userid = :userid
                AND id IN (
                    SELECT MAX(id)
                    FROM {local_shopping_cart_credits}
                    WHERE userid = :userid1
                    GROUP BY COALESCE(NULLIF(costcenter, \'\'), \'\')
                )

                ORDER BY costcenter ASC';

        // Get the latest balance of the given costcenter.
        if (!$balancerecords = $DB->get_records_sql($sql, $params)) {
            return [];
        }

        $translations = get_config('local_shopping_cart', 'costcenterstrings');
        $translationsarray = [];
        if (!empty($translations)) {
            $translations = explode(PHP_EOL, $translations);

            foreach ($translations as $translation) {
                $kvpair = explode(',', $translation);

                if (($kvpair[0] ?? false) && ($kvpair[1] ?? false)) {
                    $translationsarray[$kvpair[0]] = $kvpair[1];
                }
            }
        }

        $returnarray = array_map(fn($a) => [
            'id' => $a->id,
            'costcenter' => $a->costcenter,
            'costcenterlabel' => $translationsarray[$a->costcenter] ?? $a->costcenter,
            'balance' => round($a->balance, 2),
            'currency' => $a->currency,
        ], $balancerecords);

        return $returnarray;
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

        if (empty($data['costcenter'])) {
            foreach ($data['items'] as $item) {
                $item = (array)$item;
                $data['costcenter'] = empty($data['costcenter']) ? ($item['costcenter'] ?? '') : $data['costcenter'];
            }
        }

        [$balance, $currency] = self::get_balance($userid, $data['costcenter']);

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
            if (
                !has_capability('local/shopping_cart:cashier', $context)
            ) {
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
     * @param int $userid
     * @param float $credit
     * @param string $currency
     * @param string $costcenter
     *
     * @return array
     *
     */
    public static function add_credit(
        int $userid,
        float $credit,
        string $currency,
        string $costcenter = ''
    ): array {

        global $DB, $USER;

        [$balance, $newcurrency] = self::get_balance($userid, $costcenter);

        $now = time();

        $data = new stdClass();

        $data->userid = $userid;
        $data->credits = $credit;
        $data->currency = !empty($newcurrency) ? $newcurrency : $currency;
        $data->balance = $balance + $credit; // Balance hold the new balance after this transaction.
        $data->usermodified = $USER->id;
        $data->timemodified = $now;
        $data->timecreated = $now;
        $data->costcenter = $costcenter;

        if ($data->balance >= 0) {
            $DB->insert_record('local_shopping_cart_credits', $data);
        } else {
            // User cannot have a negative balance!
            throw new moodle_exception('negativebalancenotallowed');
        }

        [$newbalance, $currency] = self::get_balance($userid, $costcenter);

        if ($newbalance >= 0) {
            // We add the right cache even if it is 0.

            $cartstore = cartstore::instance($userid);
            $cartstore->set_credit($newbalance, $currency, $costcenter);
        }

        return [$newbalance, $currency, $costcenter];
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

        // Before adding this, we need to make sure that the we use the right costcenter.
        if (!empty($checkoutdata['costcenter'])) {
            // When we use a costcenter, the credit might come from the empty costcenter.
            // This is the credit we need to use first.
            $balances = self::get_balance_for_all_costcenters($userid);

            foreach ($balances as $balance) {
                if (empty($balance['costcenter'])) {
                    $emptycostcenterbalance = $balance['balance'];
                    continue;
                }
                if ($balance['costcenter'] == ($checkoutdata['costcenter'] ?? '')) {
                    $matchingcostcenterbalance = $balance['balance'];
                    continue;
                }
            }
        }

        if (empty($emptycostcenterbalance)) {
            $emptycostcenterbalance = 0;
        }

        $now = time();

        // If we have a balance for the empty costcenter, we use this first.
        $sumtodeduct = $checkoutdata['deductible'];

        $defaultcostcenter = get_config('local_shopping_cart', 'defaultcostcenterforcredits');

        if (
            $emptycostcenterbalance > 0
            && !empty($checkoutdata['costcenter'])
            && (empty($defaultcostcenter) || $defaultcostcenter == $checkoutdata['costcenter'])
        ) {
            // First check if we can deduct from the empty costcenter.
            $sumtodeduct = $emptycostcenterbalance - $sumtodeduct;

            $data = new stdClass();

            $data->userid = $userid;
            $data->costcenter = '';
            $data->currency = $checkoutdata['currency'];
            $data->usermodified = $USER->id;
            $data->timemodified = $now;
            $data->timecreated = $now;

            if ($sumtodeduct < 0) {
                // We want to deduct more than we have from the empty costcenter. Therefore we set it to 0.
                $data->credits = -$emptycostcenterbalance;
                $data->balance = 0;
                // We need to move the sumtoduct in the positive range again.
                $sumtodeduct *= -1;
            } else {
                // We have enough in the empty costcenter.
                $data->credits = -$checkoutdata['deductible'];
                $data->balance = $emptycostcenterbalance - $checkoutdata['deductible'];
            }

            $DB->insert_record('local_shopping_cart_credits', $data);
            $cartstore = cartstore::instance($userid);
            $cartstore->set_credit($data->balance, $data->currency);
        }

        if ($sumtodeduct > 0) {
            $data = new stdClass();

            $data->userid = $userid;
            $data->credits = -$sumtodeduct;
            $data->balance = !empty($matchingcostcenterbalance)
                ? ($matchingcostcenterbalance - $sumtodeduct) : $checkoutdata['remainingcredit'];
            $data->costcenter = $checkoutdata['costcenter'] ?? '';
            $data->currency = $checkoutdata['currency'];
            $data->usermodified = $USER->id;
            $data->timemodified = $now;
            $data->timecreated = $now;

            $DB->insert_record('local_shopping_cart_credits', $data);
            $cartstore = cartstore::instance($userid);
            $cartstore->set_credit($data->balance, $data->currency, $data->costcenter);
        }
    }

    /**
     * This function just gets the current balance and sets it to 0.
     *
     * @param int $userid
     * @param int $method
     * @param string $costcenter
     *
     * @return bool
     */
    public static function credit_paid_back(
        int $userid,
        int $method = LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH,
        string $costcenter = ''
    ) {
        global $USER;

        [$balance, $currency] = self::get_balance($userid, $costcenter);

        $data = [];

        $data['deductible'] = round($balance, 2);
        $data['remainingcredit'] = 0;
        $data['currency'] = $currency;

        if (!empty($costcenter)) {
            $data['costcenter'] = $costcenter;
        }

        self::use_credit($userid, $data);

        // Also record this in the ledger table.
        $ledgerrecord = new stdClass();
        $now = time();
        $ledgerrecord->userid = $userid;
        $ledgerrecord->itemid = 0;
        $ledgerrecord->price = (float) (-1.0) * $data['deductible'];
        $ledgerrecord->credits = (float) (-1.0) * $data['deductible'];
        $ledgerrecord->currency = $currency;
        $ledgerrecord->costcenter = $costcenter;
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
        $data['price'] = $shoppingcart->initialtotal;
        $costcenter = '';
        if (isset($shoppingcart->items)) {
            foreach ($shoppingcart->items as $item) {
                if (!empty($item['userid'])) {
                    $userid = $item['userid'];
                    $currency = $item['currency'];
                    $costcenter = empty($costcenter) ? ($item['costcenter'] ?? '') : $costcenter;
                    break;
                }
            }
        }

        $data['costcenter'] = $costcenter;

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

    /**
     * Correct credits.
     * @param stdClass $data the form data
     */
    public static function creditsmanager_correct_credits(stdClass $data) {
        global $USER;

        $currency = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';
        $costcenter = $data->creditsmanagercreditscostcenter ?? $data->costcenter ?? "";
        // Add credits.
        try {
            self::add_credit(
                $data->userid,
                $data->creditsmanagercredits,
                $currency,
                $costcenter
            );

            // Log it to ledger.
            // Also record this in the ledger table.
            $ledgerrecord = new stdClass();
            $now = time();
            $ledgerrecord->userid = $data->userid;
            $ledgerrecord->itemid = 0;
            $ledgerrecord->price = 0;
            $ledgerrecord->credits = (float) $data->creditsmanagercredits;
            $ledgerrecord->currency = $currency;
            $ledgerrecord->componentname = 'local_shopping_cart';
            $ledgerrecord->payment = LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION;
            $ledgerrecord->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
            $ledgerrecord->usermodified = $USER->id;
            $ledgerrecord->timemodified = $now;
            $ledgerrecord->timecreated = $now;
            $ledgerrecord->annotation = $data->creditsmanagerreason;
            $ledgerrecord->costcenter = $costcenter;
            shopping_cart::add_record_to_ledger_table($ledgerrecord);
        } catch (moodle_exception $e) {
            return false;
        }
        return true;
    }

}
