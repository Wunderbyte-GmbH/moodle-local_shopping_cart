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
 * Payment subsystem callback implementation for local_shopping_cart.
 *
 * @package    local_shopping_cart
 * @category   payment
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\payment;

use context_system;
use local_shopping_cart\event\payment_confirmed;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\reservations;
use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use local_shopping_cart\shopping_cart_history;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

// We need the constants defined in lib.
require_once(__DIR__ . '/../../lib.php');

/**
 * Payment subsystem callback implementation for local_shopping_cart.
 *
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class service_provider implements \core_payment\local\callback\service_provider {

    /**
     * Callback function that returns the costs and the accountid
     * for the course that $userid of the buying user.
     *
     * @param string $paymentarea Payment area
     * @param int $cartidentifier
     * @return \core_payment\local\entities\payable
     */
    public static function get_payable(string $paymentarea, int $cartidentifier): \core_payment\local\entities\payable {
        global $DB;

        // This function is called multiple times during the checkout process.
        // As it's the only relyable place where we know that a checkout was started with a given identifier...
        // ... we do the following:
        // 1. Check if there is a valid cache.
        // 2. If not, rebuild it from reservations table.
        // 3. Check if the identifier is in the reservations table.
        // 4. If not, add it and also add items to history table.
        // 5. If we have cache and a reservations table entry, make sure they are the same, else throw an error.


        $shoppingcart = shopping_cart_history::fetch_data_from_schistory_cache($cartidentifier);

        $currency = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';


        $price = round($shoppingcart['price'], 2);

        // We can only buy items with the same payment account. Therefore, we can just take the first and test it.
        $record = reset($shoppingcart['items']);
        $seachdata = [
            'itemid' => $record['itemid'],
            'componentname' => $record['componentname'],
            'area' => $record['area'],
        ];
        if (!$record = $DB->get_record('local_shopping_cart_iteminfo', $seachdata)) {
            $jsonobject = new stdClass();
        } else {
            $jsonobject = json_decode($record->json);
        }

        $accountid = $jsonobject->paymentaccountid ?? get_config('local_shopping_cart', 'accountid') ?: 1;

        return new \core_payment\local\entities\payable($price, $currency, $accountid);
    }

    /**
     * Callback function that returns the URL of the page the user should be redirected to in the case of a successful payment.
     *
     * @param string $paymentarea Payment area
     * @param int $identifier The transaction id which was just successfully terminated.
     * @return moodle_url
     */
    public static function get_success_url(string $paymentarea, int $identifier): moodle_url {
        global $DB;

        return new moodle_url('/local/shopping_cart/checkout.php', ['success' => 1, 'identifier' => $identifier]);
    }

    /**
     * Callback function that delivers what the user paid for to them.
     *
     * @param string $paymentarea
     * @param int $identifier The id of the transaction
     * @param int $paymentid payment id as inserted into the 'payments' table, if needed for reference
     * @param int $userid The userid the order is going to deliver to
     * @return bool Whether successful or not
     */
    public static function deliver_order(string $paymentarea, int $identifier, int $paymentid, int $userid): bool {
        global $DB, $USER;

         // First, look in shopping cart history to identify the payment and what users have bought.
         // Now run through all the optionids (itemids) and confirm payment.

        $data = reservations::get_json_from_db_via_identifier($identifier);

        foreach ($data['items'] as $key => $item) {
            // $cacheitemkey = $component . '-' . $area . '-' . $itemid;
        }

        // Here we write all the items to history.
        // shopping_cart_history::write_to_db((object)$data);

        // And now we retrieve them again.
        $records = shopping_cart_history::return_data_via_identifier($data['identifier']);

        if (count($records) == 0) {
            return false;
        } else {
            foreach ($records as $record) {
                $key = "$record->componentname-$record->area-$record->itemid";
                if (isset($data['items'][$key])) {
                    $data['items'][$key]['id'] = $record->id;
                } else {




                    throw new moodle_exception('shoppingcarthaschanged', 'local_shopping_cart');
                }
            }
        }

        shopping_cart::confirm_payment($userid, LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE, $data);

        return true;
    }
}
