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

namespace local_shopping_cart\shopping_cart;

use context_system;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\shopping_cart_history;
use moodle_exception;

/**
 * Shopping_cart subsystem callback implementation for local_shopping_cart, for testing, does not have any use for production.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_provider implements \local_shopping_cart\local\callback\service_provider {

    /**
     * Callback function that returns the costs and the accountid
     * for the course, just for testing.
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return array
     */
    public static function load_cartitem(string $area, int $itemid, int $userid = 0): array {

        global $DB, $USER;

        // We might need to add additional information to the area.
        // Therefore, we split it here.
        if (strpos($area, '-') !== false) {
            list($area, $addinfo) = explode('-', $area);
        } else {
            // Handle the case where "-" doesn't exist in $area.
            $addinfo = $area;
        }

        switch ($area) {
            case 'bookingfee':
                $price = get_config('local_shopping_cart', 'bookingfee');

                // Does the fee depend on costcenter?
                if (!empty(get_config('local_shopping_cart', 'bookingfeevariable'))) {
                    $cartstore = cartstore::instance($userid);
                    $items = $cartstore->get_all_items();
                    $item = reset($items);
                    $costcenter = $item['costcenter'];
                    $ccfees = get_config('local_shopping_cart', 'definefeesforcostcenters');
                    $pairs = explode(PHP_EOL, $ccfees);
                    $ccarray = [];
                    foreach ($pairs as $pair) {
                        list($key, $value) = explode(":", $pair);
                        $ccarray[$key] = $value;
                    }
                    if (in_array($costcenter, array_keys($ccarray))) {
                        $price = $ccarray[$costcenter];
                    }
                }

                $imageurl = new \moodle_url('/local/shopping_cart/pix/coins.png');
                $cartitem = new cartitem(
                    $itemid,
                    get_string('bookingfee', 'local_shopping_cart'),
                    $price,
                    get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
                    'local_shopping_cart',
                    'bookingfee',
                    '',  // No item description for booking fee.
                    $imageurl->out(), // Fee image.
                    time(),
                    0,
                    0,
                    'A',
                    1, // Booking fee cannot be deleted.
                );
                return ['cartitem' => $cartitem];
            case 'rebookingcredit':

                if (get_config('local_shopping_cart', 'cancelationfee') > 0) {
                    $price = -1 * (
                        (float)get_config('local_shopping_cart', 'bookingfee') +
                        (float)$itemid * (float)get_config('local_shopping_cart', 'cancelationfee')
                    );
                } else {
                    $price = -1 * (
                        (float)get_config('local_shopping_cart', 'bookingfee')
                    );
                }

                $imageurl = new \moodle_url('/local/shopping_cart/pix/rebook.png');
                $cartitem = new cartitem(
                    $itemid,
                    get_string('rebookingcredit', 'local_shopping_cart'),
                    $price,
                    get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
                    'local_shopping_cart',
                    'rebookingcredit',
                    '',  // No item description for rebookingcredit.
                    $imageurl->out(),
                    time(),
                    0,
                    0,
                    'A',
                    1, // Rebookingcredit cannot be deleted.
                );
                return ['cartitem' => $cartitem];
            case 'rebookitem':
                $record = $DB->get_record('local_shopping_cart_history', ['id' => $itemid]);

                if ($record->componentname === 'local_shopping_cart') {
                    return [];
                }

                $imageurl = new \moodle_url('/local/shopping_cart/pix/rebook.png');
                $cartitem = new cartitem(
                    $itemid,
                    get_string('rebooking', 'local_shopping_cart') . ': ' . $record->itemname,
                    - ((float)$record->price),
                    $record->currency ?? 'EUR',
                    'local_shopping_cart',
                    'rebookitem',
                    '',  // No item description for rebookitem.
                    $imageurl->out(),
                    $record->canceluntil, // We use the same cancel until for rebooking check.
                    0,
                    0,
                    'A',
                    0, // Rebook items can be deleted again.
                    $record->costcenter ?? null
                );
                return ['cartitem' => $cartitem];
            case 'installments':

                // First step: We have the itemid which refers to the shopping cart history id.
                // Now we have itemid etc. of the original item.
                // Now, we need to understand if this is the first item in the cart.
                // For installments, we first just have the id of the original item.

                // For installments, we get the original history record.
                $record = $DB->get_record('local_shopping_cart_history', ['id' => $itemid]);

                if ($record->componentname === 'local_shopping_cart') {
                    return [];
                }

                // Now, we check if a payment was already made.
                // Or how many installments there are still to pay.
                $jsonobject = json_decode($record->json);

                // Now we run through all the payments and check...
                // ... if they are open or already in the cart.

                $cartstore = cartstore::instance($userid);
                $itemincart = $cartstore->get_item(
                    $record->componentname,
                    $record->area,
                    $record->itemid);

                // Number of installments still to pay.
                $stilltopay = (int)$jsonobject->installments->installments;

                foreach ($jsonobject->installments->payments as $payment) {

                    // We might have the payment item already in the cart.
                    if (!empty($itemincart)) {

                        // Check if the price of the item is correct.
                        $remainder = $itemincart['price'] % $payment->price;
                        if (!empty($remainder)) {
                            throw new moodle_exception('wronginstallmentprice', 'local_shopping_cart');
                        }

                        // We only change the price if it is below the due amount.
                        if ($itemincart['price'] <= ($payment->price * ($stilltopay - 1))) {
                            $itemincart['price'] += $payment->price;
                        }

                        // Return the updated item.
                        return ['cartitem' => new cartitem(
                            $itemid,
                            $itemincart['itemname'],
                            $itemincart['price'],
                            $itemincart['currency'],
                            'local_shopping_cart',
                            'installments-' . $addinfo,
                            $itemincart['description'],
                            $itemincart['imageurl'],
                            $itemincart['canceluntil'],
                            $itemincart['serviceperiodstart'],
                            $itemincart['serviceperiodend'],
                            $itemincart['taxcategory'],
                            $itemincart['nodelete'],
                            $itemincart['costcenter'],
                        )];
                    }

                    // Now check if this particular installement is already paid in DB.

                    // If not, we just add this payment and abort the loop.
                    $imageurl = new \moodle_url('/local/shopping_cart/pix/coins.png');
                    $cartitem = new cartitem(
                        $itemid,
                        get_string('installment', 'local_shopping_cart'). ': ' . $record->itemname,
                        $payment->price,
                        $record->currency,
                        'local_shopping_cart',
                        'installments-' . $addinfo,
                        get_string('installment', 'local_shopping_cart'),
                        '',
                        $record->canceluntil,
                        $record->serviceperiodstart,
                        $record->serviceperiodend,
                        $record->taxcategory,
                        0,
                        $record->costcenter
                    );
                    return ['cartitem' => $cartitem];

                }
            case 'rebookingfee':
                $imageurl = new \moodle_url('/local/shopping_cart/pix/coins.png');
                $cartitem = new cartitem(
                    $itemid,
                    get_string('rebookingfee', 'local_shopping_cart'),
                    get_config('local_shopping_cart', 'rebookingfee'),
                    get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
                    'local_shopping_cart',
                    'rebookingfee',
                    '',  // No item description for booking fee.
                    $imageurl->out(), // Fee image.
                    time(),
                    0,
                    0,
                    'A',
                    1, // Booking fee cannot be deleted.
                );
                return ['cartitem' => $cartitem];
                break;
        }

        $now = time();
        $canceluntil = strtotime('+14 days', $now);
        $serviceperiodestart = $now;
        $serviceperiodeend = strtotime('+100 days', $now);
        $nodelete = 0;
        $costcenter = '';
        $installment = null;

        $imageurl = new \moodle_url('/local/shopping_cart/pix/edu.png');

        // For behat tests, we want clear separation of items and no random values.
        switch ($itemid) {
            case 1:
                $price = 10.00;
                $tax = 'A';
                break;
            case 2:
                $price = 20.30;
                $tax = 'B';
                break;
            case 3:
                $price = 13.8;
                $tax = 'C';
                break;
            case 5:
                $price = 42.42;
                $tax = 'B';
                $installment = 1;
                break;
            default:
                $price = 12.12;
                $tax = '';
                break;
        }

        $cartitem = new cartitem($itemid,
            'my test item ' . $itemid,
            $price,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            $area,
            'item description',
            $imageurl->out(),
            $canceluntil,
            $serviceperiodestart,
            $serviceperiodeend,
            $tax,
            $nodelete,
            $costcenter,
            $installment
            );

        // Spoecial case to crate installment.
        if ($itemid == 5) {

            $jsonobject = (object) [
                "allowinstallment" => "1",
                "downpayment" => 20,
                "numberofpayments" => "2",
                "duedaysbeforecoursestart" => 0,
                "duedatevariable" => 10,
            ];

            $iteminfo = new \stdClass;
            $iteminfo->itemid = $itemid;
            $iteminfo->area = $area;
            $iteminfo->componentname = 'local_shopping_cart';
            $iteminfo->json = json_encode($jsonobject);
            $iteminfo->usermodified = $USER->id;
            $iteminfo->allowinstallment = 1;
            // Force record into local_shopping_cart_iteminfo table.
            if ($iteminfo->id = $DB->get_field('local_shopping_cart_iteminfo', 'id', ['itemid' => $itemid], IGNORE_MISSING)) {
                $DB->update_record('local_shopping_cart_iteminfo', $iteminfo);
            } else {
                $DB->insert_record('local_shopping_cart_iteminfo', $iteminfo);
            }
        }

        return ['cartitem' => $cartitem];
    }

    /**
     * Callback function that unloads a cart item and thus frees
     * Used only in demo.php for test purchases.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     * @param int $userid
     * @return array
     */
    public static function unload_cartitem(string $area, int $itemid, int $userid = 0): array {
        return [
            'success' => 1,
            'itemstounload' => [],
        ];
    }

    /**
     * Callback function that handles inscripiton after fee was paid.
     * @param string $area
     * @param int $itemid
     * @param int $paymentid
     * @param int $userid
     * @return bool
     */
    public static function successful_checkout(string $area, int $itemid, int $paymentid, int $userid): bool {
        // TODO: Set booking_answer to 1.
        return true;
    }

    /**
     * Callback function that handles cancelation after purchase.
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return bool
     */
    public static function cancel_purchase(string $area, int $itemid, int $userid = 0): bool {
        return true;
    }

    /**
     * Callback function to give back a float value how much of the initially bought item is already consumed.
     * 1 stands for everything, 0.5 for 50%.
     * This is used in cancellation, to know how much of the initial price is returned.
     *
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return float
     */
    public static function quota_consumed(string $area, int $itemid, int $userid = 0): float {

        if ($area == 'bookingfee' || $area == 'rebookingcredit') {
            return 0;
        }

        // In this test situation, we return a value for each item to be able to test all cases.
        switch ($itemid) {
            case 1:
                return 0.67;
            case 2:
                return 0;
            case 3:
                return 1;
            default:
                return 0.0;
        }
    }

    /**
     * Callback function to check if an item can be cancelled.
     *
     * @param string $area
     * @param int $itemid An identifier that is known to the plugin
     *
     * @return bool true if cancelling is allowed, else false
     */
    public static function allowed_to_cancel(string $area, int $itemid): bool {
        $allowedtocancel = false; // By default, items in shopping cart cannot be cancelled.
        if (has_capability('local/shopping_cart:cashier', context_system::instance())) {
            $allowedtocancel = true; // By default, cashier can cancel anything.
        }
        if ($area == 'main') {
            $allowedtocancel = true; // Test items can be cancelled.
        }
        return $allowedtocancel;
    }

    /**
     * Callback to check if adding item to cart is allowed.
     *
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return array
     */
    public static function allow_add_item_to_cart(string $area, int $itemid, int $userid = 0): array {

        $data = self::load_cartitem($area, $itemid, $userid);
        /** @var cartitem $cartitem */
        $cartitem = $data['cartitem'];
        return $cartitem->as_array() ?? [];
    }
}
