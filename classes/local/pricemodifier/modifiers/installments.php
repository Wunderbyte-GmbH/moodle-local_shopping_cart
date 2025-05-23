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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\pricemodifier\modifiers;

use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\output\button;
use local_shopping_cart\shopping_cart_handler;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class installments extends modifier_base {

    /**
     * The id is nedessary for the hierarchie of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_INSTALLMENTS;

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $data
     * @return array
     */
    public static function apply(array &$data): array {

        global $DB;

        if (!get_config('local_shopping_cart', 'enableinstallments')) {
            return $data;
        }

        if (!isset($data['openinstallments'])) {
            $data['openinstallments'] = self::get_installments_from_db($data['userid']);

            $cartstore = cartstore::instance($data['userid']);
            $cartstore->set_open_installments($data['openinstallments']);
        }

        foreach ($data['items'] as $key => $itemdata) {

            // We treat an installment payment differently.

            if (!empty($itemdata['installmentpayment'])) {

                $installments = (object)[
                    'installments' => [
                        'payments' => $itemdata['installmentpayment'],
                        'price' => $itemdata['price'],
                    ],
                ];

                $data['items'][$key]['json'] = json_encode($installments);

            } else if (shopping_cart_handler::installment_exists(
                $itemdata['componentname'],
                $itemdata['area'],
                $itemdata['itemid'])) {

                // Next step, we check if there is enough time for this particular installement.
                $searchdata = [
                    'itemid' => $itemdata['itemid'],
                    'componentname' => $itemdata['componentname'],
                    'area' => $itemdata['area'],
                ];

                $record = $DB->get_record('local_shopping_cart_iteminfo', $searchdata);
                $jsonobject = json_decode($record->json);

                $timebetweenpayments = get_config('local_shopping_cart', 'timebetweenpayments') ?: 30;
                // If a value before coursestart is set, we need to check if it's not too late.
                if (!empty($jsonobject->duedaysbeforecoursestart)
                    && $timebetweenpayments > 0) {
                    if (!empty($itemdata['serviceperiodstart'])) {

                        // Calculate the date of last payment.
                        $dateoflastpayment =
                            strtotime(" - $jsonobject->duedaysbeforecoursestart days", $itemdata['serviceperiodstart']);

                        // Calculate the minimal time of payments.
                        $timeuntilpayment = $jsonobject->numberofpayments * $timebetweenpayments * 86400;
                        if ($dateoflastpayment - $timeuntilpayment < time()) {
                            // Installments are not possible anymore, as there is no time left.
                            continue;
                        } else {
                            $jsonobject->duedatevariable = round(($dateoflastpayment - time()) / 86400);
                        }
                    }
                }

                // If we just need to show the installment checkbox, we set it here.
                $data['installmentscheckboxid'] = $data['installmentscheckboxid'] ?? bin2hex(random_bytes(3));
                $data['installments'] = $data['installments'] ?? [];

                if (!empty($data['useinstallments'])) {

                    // Check which payment it is.
                    // If this is the first payment, price is downpayment.

                    // The downpayment may be higher if we find linked items.
                    $cartstore = cartstore::instance($data['userid']);
                    $linkeditems = $cartstore->get_linked_items($itemdata['itemid'], $itemdata['componentname'], $itemdata['area']);

                    $originalprice = $itemdata['price'];
                    $installmentslinkeditems = [];
                    $openamount = $originalprice;

                    $downpaymentdata = self::get_downpayment_for_user_and_option($data['userid'], $record->itemid);
                    if (!empty($downpaymentdata)) {
                        $jsonobject->downpayment = $downpaymentdata['newdownpayment'];
                    }
                    if (!empty($linkeditems)) {
                        // First, we calculate the ratio of the downpayment.
                        $downpaymentratio = $jsonobject->downpayment / $originalprice;

                        // Now we set the payment for the initial item.
                        $data['items'][$key]['price'] = $jsonobject->downpayment;

                        // We want to apply the same ratio to the subbooking items.
                        foreach ($linkeditems as $linkeditem) {

                            $linkedkey = array_search($linkeditem, $data['items']);

                            $originallinkedprice = $linkeditem['price'];
                            $linkeddownpayment = round($linkeditem['price'] * $downpaymentratio, 2);
                            $data['items'][$linkedkey]['price'] = $linkeddownpayment;

                            // No, we add the difference between the new price of the linked item to the original downpayment.
                            // phpcs:ignore
                            // $data['items'][$key]['price'] += ($originallinkedprice - $jsonobject->downpayment);

                            $installmentslinkeditems[] = [
                                'itemname' => $linkeditem['itemname'],
                                'originalprice' => $originallinkedprice,
                                'initialpayment' => $linkeddownpayment,
                                'currency' => $linkeditem['currency'],
                            ];

                            $openamount += ($originallinkedprice - $linkeddownpayment);
                        }
                        // Now we reduce the announced price of all the other items as well.
                        // But we need to ad the reduction to the initial item.
                        // We don't want to multiply our installment payments, but only increase the one we had before.

                    } else {
                        $data['items'][$key]['price'] = $jsonobject->downpayment;
                    }

                    $openamount -= $jsonobject->downpayment;

                    $now = time();
                    $delta = $jsonobject->duedatevariable * 86400;

                    $interval = round($delta / ($jsonobject->numberofpayments));
                    $payment = ($openamount) / $jsonobject->numberofpayments;

                    // If there is nothing left to pay, we don't add payments.
                    if ($payment <= 0) {
                        continue;
                    }

                    $installmentpayments = [];

                    $counter = 0;
                    while ($counter < $jsonobject->numberofpayments) {
                        $counter++;
                        $timestamp = $now + ($interval * $counter);
                        $installmentpayments['originalprice'] = $originalprice;
                        $installmentpayments['itemname'] = $itemdata["itemname"];
                        $installmentpayments['initialpayment'] = $jsonobject->downpayment;
                        $installmentpayments['currency'] = $itemdata['currency'];
                        $installmentpayments['payments'][] = [
                            'id' => ($counter - 1),
                            'date' => userdate($timestamp, get_string('strftimedate', 'langconfig')),
                            'price' => round($payment, 2),
                            'currency' => $itemdata['currency'],
                            'paid' => 0,
                            'timestamp' => $timestamp,
                        ];
                        $installmentpayments['installments'] = $jsonobject->numberofpayments;
                    }
                    $installmentpayments['installmentslinkeditems'] = $installmentslinkeditems;
                    $data['installments'][] = $installmentpayments;
                    $data['items'][$key]['installments'] = $jsonobject->numberofpayments;

                    $installments = (object)[
                        'installments' => $installmentpayments,
                    ];
                    $data['items'][$key]['json'] = json_encode($installments);
                }
            }
        }

        $data['installmentscheckboxid'] = $data['installmentscheckboxid'] ?? '';
        return  $data;
    }

    /**
     * Fetches the open installment payments from DB.
     * @param int $userid
     * @return array
     */
    private static function get_installments_from_db(int $userid) {

        global $DB, $OUTPUT;

         // This is the user view.
         $sql = "SELECT *
                FROM {local_shopping_cart_history}
                WHERE installments > 0
                AND paymentstatus = :paymentstatus";
        $params = [
            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        ];

        if (!empty($userid)) {
            $params['userid'] = $userid;
            $sql .= " AND userid=:userid ";
        }

        $records = $DB->get_records_sql($sql, $params);

        $items = [];

        foreach ($records as $record) {

            // First, we add the down payment.
            $item = new cartitem(
                $record->itemid,
                $record->itemname,
                $record->price,
                $record->currency,
                $record->componentname,
                $record->area,
                get_string('downpayment', 'local_shopping_cart'),
                '',
                $record->canceluntil,
                $record->serviceperiodstart,
                $record->serviceperiodend,
                $record->taxcategory,
                1,
                $record->costcenter
            );

            $jsonobject = json_decode($record->json);
            $payments = $jsonobject->installments->payments;

            $items[] = $item->as_array();

            foreach ($payments as $payment) {

                // If this is already paid, we don't show the button.
                if (!empty($payment->paid)) {
                    continue;
                }

                $item = new cartitem(
                    $record->id, // We use the historyid.
                    $record->itemname,
                    $payment->price,
                    $payment->currency,
                    'local_shopping_cart',
                    'installments-' . $payment->id,
                    get_string('installment', 'local_shopping_cart') . ', ' . $payment->date,
                    '',
                    null,
                    null,
                    null,
                    null,
                    1,
                    null,
                    $payment->timestamp
                );

                $item = $item->as_array();

                $items[] = $item;
            }
        }

        return $items ?? [];
    }

    /**
     * Get downpayment from cache for user and option.
     *
     * @param int $userid
     * @param int $optionid
     *
     * @return array
     *
     */
    public static function get_downpayment_for_user_and_option(int $userid, int $optionid) {
        global $USER;

        $cache = \cache::make('local_shopping_cart', 'cashier');
        $cachekey = $userid . "_" . $optionid;
        $data = $cache->get($cachekey);
        if (
            empty($data)
            || $data['expirationtime'] < time()
        ) {
            return [];
        }
        return $data;
    }

    /**
     * Set downpayment for user for option.
     *
     * @param int $userid
     * @param int $optionid
     * @param float $newdownpayment
     *
     * @return array
     *
     */
    public static function set_downpayment_for_user_and_option(int $userid, int $optionid, float $newdownpayment) {
        global $USER;

        $cache = \cache::make('local_shopping_cart', 'cashier');
        $cachekey = (string) $userid . "_" . (string) $optionid;

        $expirationtime = get_config('local_shopping_cart', 'expirationtime');
        $data = [
            'newdownpayment' => $newdownpayment,
            'expirationtime' => strtotime("+ $expirationtime minutes"),
        ];

        $cache->set($cachekey, $data);
        return $data;
    }
}
