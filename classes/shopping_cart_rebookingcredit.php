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
 * Entities Class to display list of entity records.
 *
 * @package local_shopping_cart
 * @author Thomas Winkler
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use cache;
use dml_exception;
use coding_exception;
use context_system;
use moodle_exception;
use ddl_exception;
use local_shopping_cart\event\item_canceled;
use local_shopping_cart\local\cartstore;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Class shopping_cart
 *
 * @author Georg Maißer
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_rebookingcredit {

    /**
     * entities constructor.
     */
    public function __construct() {
    }

    /**
     * Performs checks and adds the rebookingcredit if it should be applied.
     * @param string $area
     * @param int $userid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws ddl_exception
     */
    public static function add_rebookingcredit(string $area, int $userid) {

        // If we are on cashier and have a user to buy for.
        if ($userid == -1) {
            $userid = shopping_cart::return_buy_for_userid();
        }

        $cartstore = cartstore::instance($userid);
        $items = $cartstore->get_items();

        $canceledrecords = self::get_records_canceled_with_future_canceluntil($userid);

        if (!empty($canceledrecords)
            && $area != 'bookingfee'
            && $area != 'rebookingcredit') {

            $normalitemsonly = array_filter($items,
                fn($a) => ($a["area"] != 'bookingfee' && $a["area"] != 'rebookingcredit'));
            $itemcount = count($normalitemsonly);

            // We can only add as many rebookingcredits as we have canceled records.
            if ($itemcount <= count($canceledrecords)) {
                $rebookingcreditrecords = array_filter($items,
                    fn($a) => ($a["area"] == 'rebookingcredit'));
                if (!empty($rebookingcreditrecords)) {
                    $rebookingcredititem = reset($rebookingcreditrecords);
                    shopping_cart::delete_item_from_cart('local_shopping_cart', 'rebookingcredit',
                        $rebookingcredititem['itemid'], $userid);
                }

                // Add the rebookingcredit to the shopping cart.
                self::add_rebookingcredit_item_to_cart($userid, $itemcount);
            }
        }
    }

    /**
     * If the user has recently cancelled an option we'll refund the rebookingcredit.
     *
     * @param int $userid
     * @return array
     */
    private static function get_records_canceled_with_future_canceluntil(int $userid): array {
        global $DB;
        $now = time();

        // If the user has recently cancelled an option we'll refund the rebookingcredit.
        // This will always only work with the MOST RECENTLY canceled option.
        $canceledrecords = $DB->get_records_select('local_shopping_cart_history',
            "userid = :userid AND paymentstatus = 3 AND area = 'option' AND canceluntil > :canceluntil", [
            'userid' => $userid,
            'canceluntil' => $now,
        ], '');

        return $canceledrecords;
    }

    /**
     * Helper function to check if a user has already used the rebookingcredit.
     *
     * @param int $userid
     * @return bool true if user has already received a rebookingcredit, else false
     */
    private static function rebookingcredit_already_used($userid) {
        global $DB;
        return $DB->record_exists_select('local_shopping_cart_ledger',
            "area = 'rebookingcredit' AND userid = :userid AND timecreated BETWEEN :lowesttimecreated AND :now", [
            'lowesttimecreated' => self::get_lowest_timecreated_of_canceledrecords($userid),
            'userid' => $userid,
            'now' => time(),
        ]);
    }

    /**
     * Get the lowest timecreated timestamp of the canceled records.
     *
     * @param int $userid
     * @return void
     */
    private static function get_lowest_timecreated_of_canceledrecords(int $userid) {
        global $DB;
        // If the user has recently cancelled an option we'll refund the rebookingcredit.
        // This will always only work with the MOST RECENTLY canceled option.
        $lowesttimecreated = $DB->get_field_sql("SELECT MIN(timecreated) AS lowesttimecreated
            FROM {local_shopping_cart_history}
            WHERE userid = :userid AND paymentstatus = 3 AND area = 'option' AND canceluntil > :canceluntil",
            [
                'userid' => $userid,
                'canceluntil' => time(),
            ]);

        return $lowesttimecreated;
    }

    /**
     *
     * Add rebookingcredit to cart.
     *
     *
     * @param int $userid the id of the user who books (-1 if cashier books for another user)
     * @param int $itemcount number of items
     *
     * @return bool
     */
    public static function add_rebookingcredit_item_to_cart(int $userid, int $itemcount = 1): bool {

        // If rebookingcredit is turned off in settings, we never add it at all.
        if (!get_config('local_shopping_cart', 'allowrebookingcredit')
            || self::rebookingcredit_already_used($userid) // If it was already used, we do not add.
        ) {
            return false;
        }

        shopping_cart::add_item_to_cart('local_shopping_cart', 'rebookingcredit', $itemcount, $userid);

        return true;
    }

    /**
     * We need to make sure the price of the rebooking is corrected.
     * @param array $data
     * @return void
     */
    public static function correct_item_price_for_rebooking(array &$data) {

        $totalprice = 0;
        $keyofrebooking = null;

        // First, we calculate the total price.

        foreach ($data["items"] as $key => $item) {

            $item = (array)$item;
            $totalprice += $item['price'];
        }

        // Now we need to correct the price of the single items.
        foreach ($data["items"] as $key => $item) {

            // We handle items as arrays.
            $item = (array)$item;
            $data['items'][$key] = $item;

            if (($item['area'] === 'rebookitem')
                && ($item['componentname'] === 'local_shopping_cart') ) {
                $keyofrebooking = $key;

                // If the totalprice is lower than 0, the rebooking must be corrected by the difference.
                // So: Rebooking of -50 and new item of 30 will correct rebooking to -30.
                if ($totalprice < 0) {

                    // There might be more than one rebooking.
                    if ($data['items'][$keyofrebooking]['price'] > $totalprice) {
                        $totalprice -= $data['items'][$keyofrebooking]['price'];
                        $data['items'][$keyofrebooking]['price'] = 0;
                    } else {
                        $data['items'][$keyofrebooking]['price'] += -$totalprice;
                        $totalprice = 0;
                    }
                }
            }
        }
    }

    /**
     * Correct the price for rebooking, to make sure it's not lower than 0.
     * @param array $data
     * @return bool
     */
    public static function correct_total_price_for_rebooking(array &$data) {
        if ($data['price'] <= 0) {
            $data['price'] = 0;
            $data['initialtotal'] = 0;
            $data['credit'] = null;
            $data['usecredit'] = 0;
            if (isset($data['price_net'])) {
                $data['price_net'] = 0;
            }
            return true;
        }
        return false;
    }

    /**
     * Check if has currently a rebooking item in cart.
     * @param int $userid
     * @return bool
     * @throws coding_exception
     */
    public static function is_rebooking(int $userid) {

        $cartstore = cartstore::instance($userid);
        return $cartstore->is_rebooking();
    }

    /**
     * Delete any possible present booking fee from the cart.
     * @param int $userid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws ddl_exception
     */
    public static function delete_booking_fee(int $userid) {

        $cartstore = cartstore::instance($userid);

        $items = $cartstore->get_items();

        foreach ($items as $item) {
            if (($item['area'] === 'bookingfee')
                && ($item['componentname'] === 'local_shopping_cart') ) {

                shopping_cart::delete_item_from_cart(
                    'local_shopping_cart',
                    'bookingfee',
                    $item['itemid'],
                    $userid,
                    false);
            }
        }
    }

    /**
     * Checkout rebooking item and cancel the connected original booking.
     * This is partly the same as the shopping_cart::cancel_purchase function.
     * It also calls the componentcallback and shopping_cart_history::cancel_purchase.
     * But it does not add credits etc. as this is all handled via rebooking.
     * @param mixed $component
     * @param mixed $area
     * @param mixed $itemid
     * @param mixed $userid
     * @return mixed
     * @throws dml_exception
     * @throws coding_exception
     * @throws ddl_exception
     */
    public static function checkout_rebooking_item($component, $area, $itemid, $userid) {

        global $USER;

        $context = context_system::instance();

        // If we are dealing with a rebooking item, we need to unsubscribe the user.
        if (($component === 'local_shopping_cart')
            && ($area === 'rebookitem')) {

            // We need to correct $component, $area & $itemid.
            // For the rebooking item, the itemid corresponds to id of the original entry in sch.
            $historyitem = shopping_cart_history::return_item_from_history($itemid);

            // Trigger item deleted event.
            $event = item_canceled::create([
                'context' => $context,
                'userid' => $USER->id,
                'relateduserid' => $userid,
                'other' => [
                    'itemid' => $historyitem->itemid,
                    'component' => $historyitem->componentname,
                ],
            ]);
            $event->trigger();

            $providerclass = shopping_cart::get_service_provider_classname($historyitem->componentname);
            component_class_callback($providerclass, 'cancel_purchase',
                [$historyitem->area, $historyitem->itemid, $userid]);

            list($success, $error, $credit, $currency, $record) = shopping_cart_history::cancel_purchase(
                $historyitem->itemid,
                $userid,
                $historyitem->componentname,
                $historyitem->area,
                $itemid);

            if ($success === 1) {
                return true;
            } else {
                return false;
            }
        }

    }
}
