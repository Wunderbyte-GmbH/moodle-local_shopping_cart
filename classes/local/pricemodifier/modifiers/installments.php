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

use local_shopping_cart\local\pricemodifier\modifier_base;
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

                // If we just need to show the installment checkbox, we set it here.
                $data['installmentscheckboxid'] = $data['installmentscheckboxid'] ?? bin2hex(random_bytes(3));
                $data['installments'] = $data['installments'] ?? [];

                if (!empty($data['useinstallments'])) {
                    $searchdata = [
                        'itemid' => $itemdata['itemid'],
                        'componentname' => $itemdata['componentname'],
                        'area' => $itemdata['area'],
                    ];

                    $record = $DB->get_record('local_shopping_cart_iteminfo', $searchdata);
                    $jsonobject = json_decode($record->json);

                    // Check which payment it is.
                    // If this is the first payment, price is downpayment.
                    $data['items'][$key]['price'] = $jsonobject->downpayment;

                    $now = time();
                    $duedate = $now + ($jsonobject->duedatevariable * 86400);
                    $delta = $duedate - $now;

                    $interval = round($delta / ($jsonobject->numberofpayments + 1));
                    $payment = ($itemdata['price'] - $jsonobject->downpayment) / $jsonobject->numberofpayments;

                    // If there is nothing left to pay, we don't add payments.
                    if ($payment <= 0) {
                        continue;
                    }

                    $installmentpayments = [];

                    $counter = 1;
                    while ($counter <= $jsonobject->numberofpayments) {
                        $counter++;
                        $timestamp = $now + ($interval * $counter);
                        $installmentpayments['originalprice'] = $itemdata['price'];
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
}
