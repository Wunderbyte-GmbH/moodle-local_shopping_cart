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
 * Shopping cart class to manage the shopping cart.
 *
 * @package local_shopping_cart
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use cache_helper;
use coding_exception;
use context_system;
use dml_exception;
use lang_string;
use local_shopping_cart\event\checkout_completed;
use local_shopping_cart\event\item_added;
use local_shopping_cart\event\item_bought;
use local_shopping_cart\event\item_canceled;
use local_shopping_cart\event\item_deleted;
use local_shopping_cart\event\payment_rebooked;
use local_shopping_cart\task\delete_item_task;
use moodle_exception;
use Exception;
use local_shopping_cart\event\item_notbought;
use local_shopping_cart\interfaces\interface_transaction_complete;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\payment\service_provider;
use moodle_url;
use stdClass;

/**
 * Class shopping_cart
 *
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart {

    /**
     * entities constructor.
     */
    public function __construct() {
    }

    /**
     *
     * Add Item to cart.
     * - First we check if we are below maxitems from the shopping_cart isde.
     * - Then we check if the item is already in the cart and can be add it again the shopping_cart side.
     * - Now we check if the component has the product still available
     * - For any fail, we return success 0.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function allow_add_item_to_cart(
        string $component,
        string $area,
        int $itemid,
        int $userid): array {

        $userid = self::set_user($userid);

        // Check the cache for items in cart.
        $maxitems = get_config('local_shopping_cart', 'maxitems');

        $cartstore = cartstore::instance($userid);
        $numberofitems = count($cartstore->get_items());

        // Check if maxitems is exceeded.
        if (isset($maxitems) && ($numberofitems >= $maxitems)) {
            return [
                'success' => LOCAL_SHOPPING_CART_CARTPARAM_CARTISFULL,
                'itemname' => '',
            ];
        }

        // Todo: Admin setting could allow for more than one item. Right now, only one.
        if ($cartstore->already_in_cart($component, $area, $itemid)) {
            return [
                'success' => LOCAL_SHOPPING_CART_CARTPARAM_ALREADYINCART,
                'itemname' => '',
            ];
        }

        if ($area == "option") {
            // If the setting 'samecostcenter' ist turned on...
            // ... then we do not allow to add items with different cost centers.
            $providerclass = static::get_service_provider_classname($component);
            $cartitem = component_class_callback($providerclass, 'allow_add_item_to_cart', [$area, $itemid, $userid]);

            if (get_config('local_shopping_cart', 'samecostcenter')) {
                $currentcostcenter = $cartitem['costcenter'] ?? '';

                if (!$cartstore->same_costcenter($currentcostcenter)) {
                    return [
                        'success' => LOCAL_SHOPPING_CART_CARTPARAM_COSTCENTER,
                        'itemname' => $cartitem['itemname'] ?? '',
                    ];
                }
            }
            if (empty($cartitem)) {
                return [
                    'success' => LOCAL_SHOPPING_CART_CARTPARAM_ERROR,
                    'itemname' => '',
                ];
            } else if (isset($cartitem['allow']) && $cartitem['allow'] == false
                && isset($cartitem['info']) && $cartitem['info'] == "fullybooked") {
                return [
                    'success' => LOCAL_SHOPPING_CART_CARTPARAM_FULLYBOOKED,
                    'itemname' => $cartitem['itemname'] ?? '',
                ];
            } else if (isset($cartitem['allow']) && $cartitem['allow'] == false
                && isset($cartitem['info']) && $cartitem['info'] == "alreadybooked") {
                return [
                    'success' => LOCAL_SHOPPING_CART_CARTPARAM_ALREADYBOOKED,
                    'itemname' => $cartitem['itemname'] ?? '',
                ];
            } else if (isset($cartitem['allow']) && $cartitem['allow']) {
                return [
                    'success' => LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS,
                    'itemname' => $cartitem['itemname'] ?? '',
                ];
            } else if (isset($cartitem['allow']) && $cartitem['allow'] == false) {
                return [
                    'success' => LOCAL_SHOPPING_CART_CARTPARAM_ERROR,
                    'itemname' => $cartitem['itemname'] ?? '',
                ];
            }
            // Default behavior.
            return [
                'success' => LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS,
                'itemname' => $cartitem['itemname'] ?? '',
            ];
        }

        // Default.
        return [
            'success' => LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS,
            'itemname' => $cartitem['itemname'] ?? '',
        ];
    }

    /**
     *
     * Add Item to cart.
     * - First we check if we are below maxitems from the shopping_cart isde.
     * - Then we check if the item is already in the cart and can be add it again the shopping_cart side.
     * - Now we check if the component has the product still available
     * - For any fail, we return success 0.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function add_item_to_cart(string $component, string $area, int $itemid, int $userid): array {

        global $DB, $USER;

        $buyforuser = false;

        $userid = self::set_user($userid);

        $response = self::allow_add_item_to_cart($component, $area, $itemid, $userid);
        $cartparam = $response['success'];

        $cartstore = cartstore::instance($userid);

        if ($cartparam == LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS) {
            // If we have nothing in our cart and we are not about...
            // ... to add the booking fee...
            // ... we add the booking fee.
            list($areatocheck) = explode('-', $area);
            if ((!$cartstore->has_items()
                || $cartstore->get_total_price_of_items() === 0)
                && !in_array($areatocheck, ['bookingfee', 'rebookingcredit', 'rebookitem', 'installments'])) {
                // If we buy for user, we need to use -1 as userid.
                // Also we add $userid as second param so we can check if fee was already paid.
                shopping_cart_bookingfee::add_fee_to_cart($buyforuser ? -1 : $userid, $buyforuser ? $userid : 0);
            }
        }

        switch ($cartparam) {
            case LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS:
                // This gets the data from the component and also triggers reservation.
                // If reservation is not successful, we have to react here.
                $cartitemarray = self::load_cartitem($component, $area, $itemid, $userid);
                if (isset($cartitemarray['cartitem'])) {
                    // Get the itemdata as array.
                    $cartitem = $cartitemarray['cartitem'];

                    // At this point, we might have added the booking fee to the cart.
                    // This is because we always add the fee first.
                    // But if the price of the item we buy is 0, we don't want to demand a booking fee neither.
                    // Therefore, we need to delete it again from the cart.
                    if (($cartitem->price() == 0)
                        && count($cartstore->get_items()) < 2) {
                        $cartstore->delete_bookingfee();
                    }

                    // When we add the cartitem to the cache, we receive an array.
                    $itemdata = $cartstore->add_item($cartitem);

                    // If it applies, we add the rebookingcredit.
                    shopping_cart_rebookingcredit::add_rebookingcredit($area, $buyforuser ? -1 : $userid);

                    $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS;
                    $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;

                    // Add or reschedule all delete_item_tasks for all the items in the cart.
                    self::add_or_reschedule_addhoc_tasks(
                        $itemdata['expirationtime'],
                        $userid);

                    $context = context_system::instance();
                    // Trigger item deleted event.
                    $event = item_added::create([
                        'context' => $context,
                        'userid' => $USER->id,
                        'relateduserid' => $userid,
                        'other' => [
                            'itemid' => $itemid,
                            'component' => $component,
                        ],
                    ]);

                    $event->trigger();
                } else {
                    $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_ERROR, $userid);
                }
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_COSTCENTER:
                $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_COSTCENTER, $userid);
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_ALREADYINCART:
                // This case means that we have the item already in the cart.
                // Normally, this should not happen, because of JS, but it might occure when a user is...
                // Logged in on two different devices.
                $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_ALREADYINCART, $userid);
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_CARTISFULL:
                $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_CARTISFULL, $userid);
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_FULLYBOOKED:
                $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_FULLYBOOKED, $userid);
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_ALREADYBOOKED:
                $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_ALREADYBOOKED, $userid);
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_ERROR:
            default:
                $itemdata = self::get_dummy_item(LOCAL_SHOPPING_CART_CARTPARAM_ERROR, $userid);
                break;
        }
        return $itemdata;
    }

    /**
     * Returns a dummy array with the correct success param.
     * @param int $success
     * @param int $userid
     * @return int[]
     * @throws dml_exception
     */
    private static function get_dummy_item(int $success, int $userid) {

        global $USER;

        $itemdata = [];
        $itemdata['success'] = $success;
        $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
        $itemdata['expirationtime'] = self::get_expirationtime();
        $itemdata['price'] = 0;
        return $itemdata;
    }

    /**
     * Function to set the userid correctly.
     * @param int $userid
     * @return mixed
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function set_user(int $userid) {

        global $USER;

        // If there is no user specified, we determine it automatically.
        if ($userid < 0 || $userid == self::return_buy_for_userid()) {
            $context = context_system::instance();
            if (has_capability('local/shopping_cart:cashier', $context)) {
                $userid = self::return_buy_for_userid();
            }
        } else {
            // As we are not on cashier anymore, we delete buy for user.
            self::buy_for_user(0);
        }
        if ($userid < 1) {
            $userid = $USER->id;
        }
        return $userid;
    }

    /**
     * Get expiration date time plus delta from config.
     *
     * @return int
     */
    public static function get_expirationtime(): int {
        return time() + get_config('local_shopping_cart', 'expirationtime') * 60;
    }

    /**
     * This is to unload all the items from the cart.
     * In the first instance, this is about cached items.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @param bool $unload
     * @return bool
     */
    public static function delete_item_from_cart(
            string $component,
            string $area,
            int $itemid,
            int $userid,
            bool $unload = true): bool {

        global $USER;

        $userid = $userid == 0 ? $USER->id : $userid;

        $cartstore = cartstore::instance($userid);
        $cartstore->delete_item($component, $area, $itemid);

        if ($unload) {
            // This treats the related component side.

            // This function can return an array of items to unload as well.
            $response = self::unload_cartitem($component, $area, $itemid, $userid);
            foreach ($response['itemstounload'] as $cartitem) {
                self::delete_item_from_cart($component, $cartitem->area, $cartitem->itemid, $userid);
            }
        }

        if (isset($response) && isset($response['success']) && $response['success'] == 1) {

            $context = context_system::instance();
            // Trigger item deleted event.
            $event = item_deleted::create([
                'context' => $context,
                'userid' => $USER->id,
                'relateduserid' => $userid,
                'other' => [
                    'itemid' => $itemid,
                    'component' => $component,
                ],
            ]);

            $event->trigger();
        }

        $items = $cartstore->get_items();
        // If there are only fees and/or rebookingcredits left, we delete them.
        if (!empty($items)) {

            // At first, check we can delete.
            $letsdelete = true;
            foreach ($items as $remainingitem) {
                if ($remainingitem['area'] === 'bookingfee' ||
                    $remainingitem['area'] === 'rebookingcredit') {
                    continue;
                } else {
                    // If we still have bookable items, we cannot delete fees and credits from cart.
                    $letsdelete = false;

                    // Also check, if we need to adjust rebookingcredit.
                    shopping_cart_rebookingcredit::add_rebookingcredit($area, $userid);
                }
            }

            if ($letsdelete) {
                foreach ($items as $item) {
                    if (($item['area'] == 'bookingfee' ||
                        $item['area'] == 'rebookingcredit')
                        && $item['componentname'] == 'local_shopping_cart') {
                        self::delete_all_items_from_cart($userid);
                    }
                }
            }
        }

        return true;
    }

    /**
     *
     * This is to delete all items from cart.
     *
     * @param int $userid
     * @return bool
     */
    public static function delete_all_items_from_cart(int $userid): bool {

        $cartstore = cartstore::instance($userid);
        $cartstore->delete_all_items();
        return true;
    }

    /**
     * Get the name of the service provider class
     *
     * @param string $component The component
     * @return string
     * @throws \coding_exception
     */
    public static function get_service_provider_classname(string $component) {
        $providerclass = "$component\\shopping_cart\\service_provider";

        if (class_exists($providerclass)) {
            $rc = new \ReflectionClass($providerclass);
            if ($rc->implementsInterface(local\callback\service_provider::class)) {
                return $providerclass;
            }
        }
        throw new \coding_exception("$component does not have an eligible implementation of payment service_provider.");
    }

    /**
     * Asks the cartitem from the related component.
     *
     * @param string $component Name of the component that the cartitems belong to
     * @param string $area Name of area that the cartitems belong to
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return array
     */
    public static function load_cartitem(string $component, string $area, int $itemid, int $userid): array {

        $providerclass = static::get_service_provider_classname($component);
        return component_class_callback($providerclass, 'load_cartitem', [$area, $itemid, $userid]);
    }

    /**
     * Unloads the cartitem from the related component.
     *
     * @param string $component Name of the component that the cartitems belong to
     * @param string $area Name of the area that the cartitems belong to
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return array
     */
    public static function unload_cartitem(string $component, string $area, int $itemid, int $userid): array {

        $providerclass = static::get_service_provider_classname($component);
        return component_class_callback($providerclass, 'unload_cartitem', [$area, $itemid, $userid]);
    }

    /**
     * Confirms Payment and successful checkout for item.
     * This method also deals with a successful checkout for rebooking item
     * In this case, we don't book, but we cancel the original item.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function successful_checkout(string $component, string $area, int $itemid, int $userid): bool {

        global $USER;

        $context = context_system::instance();
        // Trigger item deleted event.
        $event = item_bought::create([
            'context' => $context,
            'userid' => $USER->id,
            'relateduserid' => $userid,
            'other' => [
                'itemid' => $itemid,
                'component' => $component,
            ],
        ]);

        $event->trigger();

        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'successful_checkout',
            [$area, $itemid, LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER, $userid]);
    }

    /**
     * Cancels Purchase.
     *
     * @param string $component Name of the component that the cartitems belong to
     * @param string $area Name of the area that the cartitems belong to
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function cancel_purchase_for_component(string $component, string $area, int $itemid, int $userid): bool {

        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'cancel_purchase', [$area, $itemid, $userid]);
    }

    /**
     * Function local_shopping_cart_get_cache_data
     * This function returns all the item and calculates live the price for them.
     * This function also supports the credit system of this moodle.
     * If usecredit is true, the credit of the user is substracted from price...
     * ... and supplementary information about the subtraction is returned.
     *
     * @param int|null $userid
     * @param bool $usecredit
     * @return array
     */
    public static function local_shopping_cart_get_cache_data(int $userid, $usecredit = null): array {
        global $USER, $CFG;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $usecredit = shopping_cart_credits::use_credit_fallback($usecredit, $userid);

        $cartstore = cartstore::instance($userid);

        return $cartstore->get_data();
    }

    /**
     * Returns 0|1 fore the saved usecredit state, null if no such state exists.
     *
     * @param int $userid
     * @return ?int
     */
    public static function get_saved_usecredit_state(int $userid): ?int {

        $cartstore = cartstore::instance($userid);
        return $cartstore->get_usecredit_state();
    }

    /**
     * Sets the usecredit value in Cache for the user.
     *
     * @param int $userid
     * @param int $usecredit
     * @return void
     */
    public static function save_used_credit_state(int $userid, int $usecredit) {

        $cartstore = cartstore::instance($userid);
        return $cartstore->save_usecredit_state($usecredit);
    }

    /**
     * To add or reschedule addhoc tasks to delete all the items once the shopping cart is expired.
     * As the expiration date is always calculated by the cart, not the item, this always updates the whole cart.
     *
     * @param int $expirationtime
     * @param int $userid
     * @return void
     */
    public static function add_or_reschedule_addhoc_tasks(int $expirationtime, int $userid) {

        $cartstore = cartstore::instance($userid);
        $items = $cartstore->get_items();
        $cartstore->set_expiration($expirationtime);

        foreach ($items as $taskdata) {
            // We don't touch booking fee.
            // The fee will be deleted together with the other items.
            if ($taskdata['componentname'] === 'local_shpping_cart') {
                continue;
            }
            $deleteitemtask = new delete_item_task();
            $deleteitemtask->set_userid($userid);
            $deleteitemtask->set_next_run_time($expirationtime);
            $deleteitemtask->set_custom_data($taskdata);
            \core\task\manager::reschedule_or_queue_adhoc_task($deleteitemtask);
        }
    }

    /**
     * Add the selected user to cache in chachiermode
     *
     * @param int $userid
     * @return int
     */
    public static function buy_for_user(int $userid): int {
        $cache = \cache::make('local_shopping_cart', 'cashier');

        if ($userid == 0) {
            $cache->delete('buyforuser');
        } else {
            $cache->set('buyforuser', $userid);
        }
        return $userid;
    }

    /**
     * Return userid from cache. global userid if not to be found.
     *
     * @return int
     */
    public static function return_buy_for_userid() {
        global $USER;

        $cache = \cache::make('local_shopping_cart', 'cashier');
        $userid = $cache->get('buyforuser');

        if (!$userid) {
            $userid = $USER->id;
        }
        return $userid;
    }

    /**
     * This function confirms that a user has paid for the items which are currently in her shopping cart...
     * .. or the items passed by shopping cart history. The second option is the case when we use the payment module of moodle.
     *
     * 1. Check if price is 0. If so, user can pay for herself, or cashier can pay for others.
     * 2. Create an identifier if there is none.
     * 3. If we were paying by card, we need to use credits.
     *
     * @param int $userid
     * @param int $paymenttype
     * @param array $datafromhistory
     * @param string $annotation - empty on default
     * @return array
     */
    public static function confirm_payment(int $userid, int $paymenttype, array $datafromhistory = null,
        string $annotation = '') {
        global $USER;

        $identifier = 0;

        // We use this flag to keep track if we have already used the credits or not.
        $creditsalreadyused = false;

        // We deal with two separate cases.
        // If we receive the data via the callback from a payment provider...
        // ... we will have an identifier and $datafromhistory will actually hold the records retrieved...
        // ... from shopping_cart_history table via this identifier.
        // If not, we need to create the identifier first, because we checkout via cashier or pay with credits.

        $context = context_system::instance();

        // When the function is called from webservice, we don't have a $datafromhistory array.
        if (!$data = $datafromhistory) {

            $cartstore = cartstore::instance($userid);
            $data = $cartstore->get_data();

            // If the price is not null, user has to have cashier rights to proceed here.
            if (($data['price'] != 0)
                && !has_capability('local/shopping_cart:cashier', $context)) {

                return [
                    'status' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart'),
                    'credit' => (float)$data['remainingcredit'] ?? 0.0,
                    'identifier' => $identifier,
                ];
            }

            // Retrieve items from cache.
            $cartstore = cartstore::instance($userid);
            $data = $cartstore->get_data();

            // Now the user either has enough credit to pay for herself, or she is a cashier.
            $identifier = shopping_cart_history::create_unique_cart_identifier($userid);

        } else {

            // TODO: Migrate everything to the cartstore & pricemodifiers.

            // Even if we get the data from history, we still need to look in cache.
            // With this, we will know how much the user actually paid and how much comes from her credits.
            shopping_cart_credits::prepare_checkout($data, $userid);

            // Now we need to store the new credit balance.
            if (!empty($data['deductible']) &&
                ($data['credit'] != $data['remainingcredit'])) {
                shopping_cart_credits::use_credit($userid, $data);
                $creditsalreadyused = true;
            }
        }

        // Check if we have items for this user.
        if (empty($data['items'])) {
            return [
                    'status' => 0,
                    'error' => get_string('noitemsincart', 'local_shopping_cart'),
                    'credit' => 0.0,
                    'identifier' => $identifier,
            ];
        }

        $success = true;
        $error = [];

        // When we come from rebooking, we need to correct the price of the rebooking item.
        // The total price can't be below 0.
        shopping_cart_rebookingcredit::correct_item_price_for_rebooking($data);

        // When we use credits, we have to log this in the ledger so cash report will have the correct sums!
        if (isset($data["usecredit"]) && $data["usecredit"] && isset($data["credit"]) && $data["credit"] > 0) {

            // If we have no identifier, we look for it in items.
            if (empty($identifier)) {
                foreach ($data["items"] as $item) {
                    if (!empty($item['identifier'])) {
                        $identifier = $item['identifier'];
                        break;
                    }
                }
            }

            $ledgerrecord = new stdClass;
            $ledgerrecord->userid = $userid;
            $ledgerrecord->itemid = 0;
            $ledgerrecord->price = (float) (-1.0) * $data["deductible"];
            $ledgerrecord->credits = (float) (-1.0) * $data["deductible"];
            $ledgerrecord->currency = $data["currency"];
            $ledgerrecord->componentname = 'local_shopping_cart';
            $ledgerrecord->identifier = $identifier;
            $ledgerrecord->payment = $paymenttype;
            $ledgerrecord->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
            $ledgerrecord->usermodified = $USER->id;
            $ledgerrecord->timemodified = time();
            $ledgerrecord->timecreated = time();
            $ledgerrecord->itemname = get_string('creditsused', 'local_shopping_cart');
            $ledgerrecord->annotation = get_string('creditsusedannotation', 'local_shopping_cart');
            self::add_record_to_ledger_table($ledgerrecord);
        }

        // Run through all items still in the cart and confirm payment.
        foreach ($data['items'] as $item) {

            // We might retrieve the items from history or via cache. From history, they come as stdClass.
            $item = (array) $item;

            // If the item identifier is specified (this is only the case, when we get data from history)...
            // ... we use the identifier. Else, it stays the same.
            $identifier = $item['identifier'] ?? $identifier;

            // In the shoppingcart history, we don't store the name.
            if (!isset($item['itemname'])) {
                $item['itemname'] = $item['itemid'];
            }

            // Here, we have two different ways to call the component callback.
            // And for rebooking, there is quite some change going, as we actually replace the item.
            if (($item['componentname'] === 'local_shopping_cart')
                && ($item['area'] === 'rebookitem')) {

                shopping_cart_rebookingcredit::checkout_rebooking_item(
                    $item['componentname'],
                    $item['area'],
                    $item['itemid'],
                    $userid,
                );
            } else if (!self::successful_checkout($item['componentname'], $item['area'], $item['itemid'], $userid)) {
                $success = false;
                $error[] = get_string('itemcouldntbebought', 'local_shopping_cart', $item['itemname']);

                $context = context_system::instance();
                // Trigger item deleted event.
                $event = item_notbought::create([
                    'context' => $context,
                    'userid' => $USER->id,
                    'relateduserid' => $userid,
                    'other' => [
                        'itemid' => $item['itemid'],
                        'component' => $item['componentname'],
                    ],
                ]);

            }

            if ($success == true) {
                // Delete Item from cache.
                // Here, we don't need to unload the component, so the last parameter is false.
                self::delete_item_from_cart($item['componentname'], $item['area'], $item['itemid'], $userid, false);

                // We create this entry only for cash payment, that is when there is no datafromhistory yet.
                if (!$datafromhistory) {

                    $paymentmethod = $paymenttype;

                    // Make sure we can pass on a valid value.
                    $item['discount'] = $item['discount'] ?? 0;
                    $item['identifier'] = $identifier;
                    $item['annotation'] = $annotation ?? '';
                    $item['payment'] = $paymentmethod;
                    $item['usermodified'] = $USER->id;

                    if (($item['componentname'] === 'local_shopping_cart')
                        && ($item['area'] === 'rebookitem')) {

                            $historyitem = shopping_cart_history::return_item_from_history($item['itemid']);

                            $item['schistoryid'] = $item['itemid'];
                            $item['itemid'] = $historyitem->itemid;
                    }

                    $id = shopping_cart_history::create_entry_in_history(
                            $userid,
                            $item['itemid'],
                            $item['itemname'],
                            $item['price'],
                            $item['discount'],
                            $item['currency'],
                            $item['componentname'],
                            $item['area'],
                            $item['identifier'],
                            $item['payment'],
                            LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
                            $item['canceluntil'] ?? null,
                            $item['serviceperiodstart'] ?? 0,
                            $item['serviceperiodend'] ?? 0,
                            $item['tax'] ?? null,
                            $item['taxpercentage'] ?? null,
                            $item['taxcategory'] ?? null,
                            $item['costcenter'] ?? null,
                            $item['annotation'],
                            $item['usermodified'],
                            $item['schistoryid'] ?? null,
                            $item['installments'] ?? 0,
                            $item['json'] ?? ''
                    );

                    $item['id'] = $id;
                    // If we just paid for an installment, we need a very special treatment.
                    if ($item['componentname'] === 'local_shopping_cart'
                        && strpos($item['area'], 'installment') !== false) {

                        $item['id'] = $id;
                    }
                }

                shopping_cart_history::set_success_in_db([(object)$item]);
            }
        }

        // In our ledger, the credits table, we add an entry and make sure we actually deduce any credit we might have.
        // Only do this, if credits have not been used yet.
        if (!$creditsalreadyused && isset($data['usecredit'])
                && $data['usecredit'] === true) {
            shopping_cart_credits::use_credit($userid, $data);
        } else {
            $data['remainingcredit'] = 0;
        }

        if ($success) {

            if ($paymenttype == LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL) {
                // Trigger manual rebook event, so we can react on it within other plugins.
                $event = payment_rebooked::create([
                    'context' => context_system::instance(),
                    'userid' => $USER->id, // The cashier.
                    'relateduserid' => $userid, // The user for whom the rebooking was done.
                    'other' => [
                        'userid' => $userid, // The user for whom the rebooking was done.
                        'identifier' => $identifier,
                        'annotation' => $annotation, // The annotation. Might also contain an OrderID.
                        'usermodified' => $USER->id, // The cashier.
                    ],
                ]);
                $event->trigger();
            }

            return [
                    'status' => 1,
                    'error' => '',
                    'credit' => (float)$data['remainingcredit'],
                    'identifier' => $identifier,

            ];
        } else {
            return [
                    'status' => 0,
                    'error' => implode('<br>', $error),
                    'credit' => (float)$data['remainingcredit'],
                    'identifier' => $identifier,
            ];
        }
    }

    /**
     * Function to cancel purchase of item. The price of the item will be handled as a credit for the next purchases.
     *
     * @param int $itemid
     * @param string $area
     * @param int $userid
     * @param string $componentname
     * @param int|null $historyid
     * @param float $customcredit
     * @param float $cancelationfee
     * @param int $applytocomponent
     *
     * @return array
     */
    public static function cancel_purchase(
        int $itemid,
        string $area,
        int $userid,
        string $componentname,
        int $historyid = null,
        float $customcredit = 0.0,
        float $cancelationfee = 0.0,
        int $applytocomponent = 1): array {

        global $USER;

        // A user can only cancel for herself, unless she is cashier.
        if ($USER->id != $userid) {
            $context = context_system::instance();
            if (!has_capability('local/shopping_cart:cashier', $context)) {
                return [
                    'success' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart'),
                    'credit' => 0,
                ];
            }
        }

        // At this point, we need a fallback when the historyid is empty.
        // This happens typically when the cancel comes from outside shopping.
        // We then just take the newest matching purchase.
        if (empty($historyid)) {
            $record = shopping_cart_history::get_most_recent_historyitem(
                $componentname,
                $area,
                $itemid,
                $userid);
            $historyid = empty($record) ? 0 : $record->id;
        }

        // Check if cancelation is still within the allowed period set in shopping_cart_history.
        if (!self::allowed_to_cancel($historyid, $itemid, $area, $userid)) {
            return [
                    'success' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart'),
                    'credit' => 0,
            ];
        }

        // Sometimes, we don't want a callback to the compoonent.
        if ($applytocomponent == 1) {
            if (!self::cancel_purchase_for_component($componentname, $area, $itemid, $userid)) {
                return [
                        'success' => 0,
                        'error' => get_string('canceldidntwork', 'local_shopping_cart'),
                        'credit' => 0,
                ];
            }
        }

        // The credit field can only be transmitted by authorized user.
        if (!empty($customcredit)) {
            $context = context_system::instance();
            if (!has_capability('local/shopping_cart:cashier', $context)) {
                $customcredit = 0.0;
            }
        }

        list($success, $error, $credit, $currency, $record) = shopping_cart_history::cancel_purchase($itemid,
            $userid, $componentname, $area, $historyid);

        // Only the Cashier can override the credit. If she has done so, we use it.
        // Else, we use the normal credit.
        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)
            || $userid == $USER->id) { // If the cashier is cancelling her own booking, whe also gets normal credit.
            if (empty($customcredit)) {
                $customcredit = $credit;
            }
        }

        if ($success == 1) {
            // If the payment was successfully canceled, we can book the credits to the users balance.

            /* If the user canceled herself and a cancelation fee is set in config settings
            we deduce this fee from the credit. */
            if ($userid == $USER->id) {

                // The credit might be reduced by the consumption.
                $consumption = get_config('local_shopping_cart', 'calculateconsumation');
                if ($consumption == 1) {
                    $quota = self::get_quota_consumed($componentname, $area, $itemid, $userid, $historyid);
                    $customcredit = $quota['remainingvalue'];
                }

                if (($cancelationfee = get_config('local_shopping_cart', 'cancelationfee'))
                        && $cancelationfee > 0) {
                    $customcredit = $customcredit - $cancelationfee;

                }
            }

            // Apply rounding to all relevant values.

            // If setting to round discounts is turned on, we round to full int.
            $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;

            $customcredit = round($customcredit, $discountprecision);

            // Make sure customcredit is never negative due to cancelation fee.
            // For cashier as well as for self booking users.
            if ($customcredit < 0) {
                $cancelationfee = $cancelationfee + $customcredit; // We reduce cancelationfee by the negative customcredit.
                $customcredit = 0;
            }

            list($newcredit) = shopping_cart_credits::add_credit($userid, $customcredit, $currency);

            // We also need to insert a record into the ledger table.
            $record->credits = $customcredit;
            $record->fee = $cancelationfee;
            self::add_record_to_ledger_table($record);

            $context = context_system::instance();
                // Trigger item deleted event.
                $event = item_canceled::create([
                    'context' => $context,
                    'userid' => $USER->id,
                    'relateduserid' => $userid,
                    'other' => [
                        'itemid' => $itemid,
                        'component' => $componentname,
                    ],
                ]);

                $event->trigger();
        }

        return [
                'success' => $success,
                'error' => $error,
                'credit' => $newcredit,
        ];
    }

    /**
     * Sets credit to 0, because we get information about cash pay-back.
     *
     * @param int $userid
     * @param int $method
     * @return array
     */
    public static function credit_paid_back(int $userid,
        int $method = LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH): array {

        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            return [
                    'status' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart'),
            ];
        }

        if (!shopping_cart_credits::credit_paid_back($userid, $method)) {
            return [
                    'status' => 0,
                    'error' => 'couldntpayback',
            ];
        }

        return [
                'status' => 1,
                'error' => '',
        ];
    }

    /**
     * Check if we are allowed to cancel.
     * Can be when it's still within the defined cancelation periode, or the user has the right as cashier.
     *
     * @param int $historyid
     * @param int $itemid
     * @param string $area
     * @param int $userid
     * @return bool
     */
    public static function allowed_to_cancel(int $historyid, int $itemid, string $area, int $userid): bool {

        if (!$item = shopping_cart_history::return_item_from_history($historyid)) {
            return false;
        }

        // We can only cancel items that are successfully paid.
        if ($item->paymentstatus != LOCAL_SHOPPING_CART_PAYMENT_SUCCESS) {
            return false;
        }

        return self::allowed_to_cancel_for_item($item, $area);
    }

    /**
     * This function does not need the historyid but justs the component relevant settings.
     * @param stdClass $item
     * @param string $area
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function allowed_to_cancel_for_item(stdClass $item, string $area) {
        $context = context_system::instance();

        // Cashier can always cancel but if it's no cashier...
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            // ...then we have to check, if the item itself allows cancellation.
            $providerclass = static::get_service_provider_classname($item->componentname);
            try {
                $itemallowedtocancel = component_class_callback($providerclass, 'allowed_to_cancel', [$area, $item->itemid]);
            } catch (Exception $e) {
                $itemallowedtocancel = false;
            }

            if (!$itemallowedtocancel) {
                return false;
            }
        }

        $cancelationfee = get_config('local_shopping_cart', 'cancelationfee');

        // If the cancelationfee is < 0, or the time has expired, the user is not allowed to cancel.
        if (($cancelationfee < 0) ||
            (!empty($item->canceluntil)
                && ($item->canceluntil < time()))) {
            // Cancelation after time has expired is only allowed for cashiers.
            if (!has_capability('local/shopping_cart:cashier', $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Helper function to add entries to local_shopping_cart_ledger table.
     * Do not update entries in the ledges. Only insert records.
     *
     * @param stdClass $record the record to add to the ledger table
     */
    public static function add_record_to_ledger_table(stdClass $record) {
        global $DB, $USER;
        $id = null;
        switch ($record->paymentstatus) {
            case LOCAL_SHOPPING_CART_PAYMENT_SUCCESS:
                // We add a check to make sure we prevent duplicates!
                // If itemid is 0, we cannot do this check, as we always want to write cash transfer, cash transaction, etc.
                if (($record->itemid === 0) || (!$DB->get_record('local_shopping_cart_ledger', [
                    'userid' => $record->userid, // Not nullable.
                    'itemid' => $record->itemid, // Not nullable.
                    'itemname' => $record->itemname ?? null,
                    'price' => $record->price ?? null,
                    'discount' => $record->discount ?? null,
                    'credits' => $record->credits ?? null,
                    'fee' => $record->fee ?? null,
                    'currency' => $record->currency ?? null,
                    'componentname' => $record->componentname ?? null,
                    'identifier' => $record->identifier ?? null,
                    'payment' => $record->payment ?? null,
                    'paymentstatus' => $record->paymentstatus, // Not nullable.
                    'accountid' => $record->accountid ?? null,
                    'usermodified' => $record->usermodified, // Not nullable.
                    'area' => $record->area ?? null,
                    'annotation' => $record->annotation ?? null,
                    'schistoryid' => $record->schistoryid ?? null,
                ]))) {
                    // We only insert if entry does not exist yet.
                    $id = $DB->insert_record('local_shopping_cart_ledger', $record);
                    cache_helper::purge_by_event('setbackcachedcashreport');
                }
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_CANCELED:
                $now = time();
                $record->price = null;
                $record->discount = null;
                $record->usermodified = $USER->id;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $record->payment = LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS;
                $record->gateway = null;
                $record->orderid = null;
                $record->schistoryid = null;
                $id = $DB->insert_record('local_shopping_cart_ledger', $record);
                cache_helper::purge_by_event('setbackcachedcashreport');
                break;
            // Aborted or pending payments will never be added to ledger.
            // We use the <gateway>_openorders tables to track open orders.
            case LOCAL_SHOPPING_CART_PAYMENT_ABORTED:
            case LOCAL_SHOPPING_CART_PAYMENT_PENDING:
            default:
                break;
        }
        // Trigger the checkout_completed event. Can be used by observers.
        if (!empty($id)) {
            $context = context_system::instance();
            $event = checkout_completed::create([
                    'context' => $context,
                    'userid' => $record->usermodified,
                    'relateduserid' => $record->userid,
                    'other' => [
                            'identifier' => $record->identifier ?? null,
                    ],
            ]);
            $event->trigger();
        }
    }

    /**
     * Enriches the cart item with tax information if given
     *
     * @param array $items array of cart items
     * @param taxcategories|null $taxcategories
     * @return array
     */
    public static function update_item_price_data(array $items, ?taxcategories $taxcategories): array {
        $countrycode = null; // TODO get countrycode from user info.

        $context = context_system::instance();

        foreach ($items as $key => $item) {

            if ($taxcategories) {
                $taxpercent = $taxcategories->tax_for_category($item['taxcategory'], $countrycode);
                if ($taxpercent >= 0) {
                    $items[$key]['taxpercentage_visual'] = round($taxpercent * 100, 2);
                    $items[$key]['taxpercentage'] = round($taxpercent, 2);
                    $itemisnet = get_config('local_shopping_cart', 'itempriceisnet');
                    if ($itemisnet) {
                        $netprice = $items[$key]['price']; // Price is now considered a net price.
                        $grossprice = round($netprice * (1 + $taxpercent), 2);
                        $items[$key]['price_net'] = $netprice;
                        $items[$key]['price'] = $items[$key]['price_net']; // Set back formatted price.
                        // Add tax to price (= gross price).
                        $items[$key]['price_gross'] = $grossprice;
                        // And net tax info.
                        $items[$key]['tax'] = $grossprice - $netprice;
                    } else {
                        $netprice = round($items[$key]['price'] / (1 + $taxpercent), 2);
                        $grossprice = $items[$key]['price'];
                        $items[$key]['price_net'] = $netprice;
                        $items[$key]['price'] = $grossprice; // Set back formatted price.
                        // Add tax to price (= gross price).
                        $items[$key]['price_gross'] = $grossprice;
                        // And net tax info.
                        $items[$key]['tax'] = $grossprice - $netprice;
                    }
                }
            }
        }
        return $items;
    }

    /**
     * Calculates the total price of all items
     *
     * @param array $items list of shopping cart items
     * @param bool $calculatenetprice true to calculate net price, false to calculate gross price
     * @return float the total price (net or gross) of all items rounded to two decimal places
     */
    public static function calculate_total_price(array $items, bool $calculatenetprice = false): float {
        return round(array_reduce($items, function($sum, $item) use ($calculatenetprice) {
            if ($calculatenetprice) {
                // Calculate net price.
                if (key_exists('price_net', $item)) {
                    $sum += $item['price_net'];
                } else {
                    $sum += $item['price']; // This is the net price.
                }
            } else {
                // Calculate gross price.
                if (key_exists('price_gross', $item)) {
                    $sum += $item['price_gross'];
                } else {
                    $sum += $item['price']; // This is the gross price.
                }
            }
            return $sum;
        }, 0.0), 2);
    }

    /**
     * Get consumed quota of item via callback.
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @param int $historyid
     *
     * @return array
     */
    public static function get_quota_consumed(string $component, string $area, int $itemid, int $userid, int $historyid): array {

        $item = shopping_cart_history::return_item_from_history($historyid);

        self::add_quota_consumed_to_item($item, $userid);
        $quota = $item->quotaconsumed;

        // Now get the historyitem in order to check the initial price and calculate the rest.
        if ($quota >= 0 && $item) {
            $initialprice = (float)$item->price;
            $deducedvalue = $initialprice * $quota;
            $remainingvalue = $initialprice - $deducedvalue;
            $currency = $item->currency;
            $cancelationfee = get_config('local_shopping_cart', 'cancelationfee');
            $success = $cancelationfee < 0 ? 0 : 1; // Cancelation not allowed.
        }

        return [
            'success' => $success ?? 0,
            'quota' => $quota ?? 0,
            'remainingvalue' => $remainingvalue ?? 0,
            'deducedvalue' => $deducedvalue ?? 0,
            'initialprice' => $initialprice ?? 0,
            'currency' => $currency ?? '',
            'cancelationfee' => $cancelationfee ?? 0,
        ];
    }

    /**
     * Function to lazyload userlist for autocomplete.
     *
     * @param string $query
     * @return array
     */
    public static function load_users(string $query): array {
        global $DB;
        $params = [];
        $values = explode(' ', $query);
        $fullsql = $DB->sql_concat(
            '\' \'', 'u.id', '\' \'', 'u.firstname', '\' \'', 'u.lastname', '\' \'', 'u.email', '\' \''
        );
        $sql = "SELECT * FROM (
                    SELECT u.id, u.firstname, u.lastname, u.email, $fullsql AS fulltextstring
                    FROM {user} u
                    WHERE u.deleted = 0
                ) AS fulltexttable";
                // Check for u.deleted = 0 is important, so we do not load any deleted users!

        if (!empty($query)) {
            // We search for every word extra to get better results.
            $firstrun = true;
            $counter = 1;
            foreach ($values as $value) {

                $sql .= $firstrun ? ' WHERE ' : ' AND ';
                $sql .= " " . $DB->sql_like('fulltextstring', ':param' . $counter, false) . " ";
                // If it's numeric, we search for the full number - so we need to add blanks.
                $params['param' . $counter] = is_numeric($value) ? "% $value %" : "%$value%";
                $firstrun = false;
                $counter++;
            }
        }

        // We don't return more than 100 records, so we don't need to fetch more from db.
        $sql .= " limit 102";
        $rs = $DB->get_recordset_sql($sql, $params);
        $count = 0;
        $list = [];

        foreach ($rs as $record) {
            $user = (object)[
                'id' => $record->id,
                'firstname' => $record->firstname,
                'lastname' => $record->lastname,
                'email' => $record->email,
            ];

            $count++;
            $list[$record->id] = $user;
        }

        $rs->close();

        return [
            'warnings' => count($list) > 100 ? get_string('toomanyuserstoshow', 'core', '> 100') : '',
            'list' => count($list) > 100 ? [] : $list,
        ];
    }

    /**
     * Check for ongoing payment.
     *
     * @param int $userid
     * @return void
     */
    public static function check_for_ongoing_payment(int $userid) {

        global $DB;

        $now = time();

        $params['paymentstatus'] = LOCAL_SHOPPING_CART_PAYMENT_PENDING;
        $params['userid'] = $userid;

        $dbman = $DB->get_manager();

        // We need the accounts to run through all the gateways.
        $accounts = \core_payment\helper::get_payment_accounts_to_manage(context_system::instance());
        foreach ($accounts as $account) {

            // Note: We currently never use this.
            $canmanage = has_capability('moodle/payment:manageaccounts', $account->get_context());

            foreach ($account->get_gateways() as $gateway) {

                if (empty($gateway->get('enabled'))) {
                    continue;
                }

                $name = $gateway->get('gateway');

                // First we check if there is an openorders table. If not, we have no business here.
                $table = "paygw_" . $name . "_openorders";
                if (!$dbman->table_exists($table)) {
                    continue;
                }

                $sql = "SELECT DISTINCT sch.identifier, sch.userid, oo.timecreated, COALESCE(oo.tid, '') as tid
                        FROM {local_shopping_cart_history} sch
                        JOIN {" . $table . "} oo
                        ON oo.itemid = sch.identifier AND oo.userid=sch.userid
                        WHERE sch.paymentstatus IN (0,1)
                        AND sch.userid=:userid
                        AND oo.status = 0
                        GROUP BY sch.identifier, sch.userid, oo.timecreated, tid";

                // Filtern records.
                $now = time();
                $past = strtotime('-48 hours', $now);

                $records = $DB->get_records_sql($sql, $params);

                // If we don't have any entries, we just continue.
                if (count($records) == 0) {
                    continue;
                }

                $transactioncompletestring = 'paygw_' .$name . '\external\transaction_complete';
                if (class_exists($transactioncompletestring)) {

                    // Now, we run through all pending payments we found above.
                    foreach ($records as $record) {

                        if ($record->timecreated <= $past) {
                            continue;
                        }

                        try {
                            $transactioncomplete = new $transactioncompletestring();
                            if ($transactioncomplete instanceof interface_transaction_complete) {
                                $response = $transactioncomplete::execute(
                                    'local_shopping_cart',
                                    '',
                                    $record->identifier,
                                    $record->tid,
                                    '',
                                    '',
                                    true,
                                    '',
                                    $userid,
                                );
                            } else {
                                throw new moodle_exception(
                                    'ERROR: transaction_complete does not implement transaction_complete interface!');
                            }
                        } catch (\Throwable $e) {
                            echo "ERROR: " . $e;
                        }

                        // Whenever we find a pending payment and we could complete it, we redirect to the success url.
                        if (isset($response['success']) && $response['success']) {

                            // At this point, we need to do one more check.
                            // If, for some reason, the payment was successful...
                            // ...  but the shopping car history is not updated, we might run in a loop.

                            if ($paymentid = $DB->get_field('payments', 'id', [
                                'component' => 'local_shopping_cart',
                                'itemid' => $record->identifier,
                                'userid' => $record->userid,
                                'gateway' => $name,
                            ])) {
                                service_provider::deliver_order('', $record->identifier, $paymentid, $record->userid);
                            }

                            if (!empty($response['url'])) {
                                redirect($response['url']);
                            }
                        }
                    }
                }
            }
        }

        /*
            - run through installed gateways
            - check if _openorders table exists for any of them.
            - check if any identfier record is present and return the tid
            - contact the paymentprovider to check status of the current tid.
            - Depending on the returned status, => complete payment.
        */
    }

    /**
     * Returns the list of currencies that the payment subsystem supports and therefore we can work with.
     *
     * @return array[currencycode => currencyname]
     */
    public static function get_possible_currencies(): array {
        // Fix bug with Moodle versions older than 3.11.
        $currencies = [];
        if (class_exists('core_payment\helper')) {
            $codes = \core_payment\helper::get_supported_currencies();

            foreach ($codes as $c) {
                $currencies[$c] = new lang_string($c, 'core_currencies');
            }

            uasort($currencies, function($a, $b) {
                return strcmp($a, $b);
            });
        } else {
            $currencies['EUR'] = 'Euro';
            $currencies['USD'] = 'US Dollar';
        }

        return $currencies;
    }

    /**
     * Helper function to convert all prices in provided array
     * into strings with 2 fixed decimals.
     *
     * @param array $data reference to the data array.
     */
    public static function convert_prices_to_number_format(array &$data) {
        // Render all prices to 2 fixed decimals.
        if (!empty($data['price'])) {
            $data['price'] = number_format(round((float) $data['price'], 2), 2, '.', '');
        }
        if (!empty($data['initialtotal'])) {
            $data['initialtotal'] = number_format(round((float) $data['initialtotal'], 2), 2, '.', '');
        }
        if (!empty($data['initialtotal_net'])) {
            $data['initialtotal_net'] = number_format(round((float) $data['initialtotal_net'], 2), 2, '.', '');
        }
        if (!empty($data['discount'])) {
            $data['discount'] = number_format(round((float) $data['discount'], 2), 2, '.', '');
        }
        if (!empty($data['deductible'])) {
            $data['deductible'] = number_format(round((float) $data['deductible'], 2), 2, '.', '');
        }
        if (!empty($data['credit'])) {
            $data['credit'] = number_format(round((float) $data['credit'], 2), 2, '.', '');
        }
        if (!empty($data['remainingcredit'])) {
            $data['remainingcredit'] = number_format(round((float) $data['remainingcredit'], 2), 2, '.', '');
        }
        if (!empty($data['price_net'])) {
            $data['price_net'] = number_format(round((float) $data['price_net'], 2), 2, '.', '');
        }
        if (!empty($data['price_gross'])) {
            $data['price_gross'] = number_format(round((float) $data['price_gross'], 2), 2, '.', '');
        }
        // Also convert prices for each item.
        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                $item['price'] = number_format(round((float) $item['price'], 2), 2, '.', '');
                if (!empty($item['price_net'])) {
                    $item['price_net'] = number_format(round((float) $item['price_net'], 2), 2, '.', '');
                }
                if (!empty($item['price_gross'])) {
                    $item['price_gross'] = number_format(round((float) $item['price_gross'], 2), 2, '.', '');
                }
            }
            $data['items'] = array_values($data['items']);
        }
    }

    /**
     * Receive quota consumed via callback to component.
     *
     * @param stdClass $item
     * @param int $userid
     * @return [type]
     */
    public static function add_quota_consumed_to_item(stdClass &$item, int $userid) {

        if (empty($item->componentname) || empty($item->area)) {
            return;
        }

        // If we have set a fixed percentage in settings, we use this one!
        if (get_config('local_shopping_cart', 'calculateconsumation')
            && get_config('local_shopping_cart', 'calculateconsumationfixedpercentage') > 0) {

            // We also check if the setting to only apply fixed percentage within service period is turned on.
            if (get_config('local_shopping_cart', 'fixedpercentageafterserviceperiodstart')) {
                if (time() >= $item->serviceperiodstart) {
                    $item->quotaconsumed = (float) 0.01 * get_config('local_shopping_cart', 'calculateconsumationfixedpercentage');
                    return;
                } else {
                    $item->quotaconsumed = 0.0;
                    return;
                }

            } else {
                $item->quotaconsumed = (float) 0.01 * get_config('local_shopping_cart', 'calculateconsumationfixedpercentage');
                return;
            }
        }

        // We fetch the consumed quota as well.
        $providerclass = self::get_service_provider_classname($item->componentname);
        $item->quotaconsumed = component_class_callback($providerclass, 'quota_consumed',
                [
                        'area' => $item->area,
                        'itemid' => $item->itemid,
                        'userid' => $userid,
                ]);
    }

    /**
     * Helper function to create daily sums.
     *
     * @param string $date date in the form 'YYYY-MM-DD'
     * @param string $selectorformoutput the HTML of the date selector form
     * @return array array containing the data needed to render daily sums
     */
    public static function get_daily_sums_data(string $date, string $selectorformoutput = ''): array {
        global $DB, $USER;

        $commaseparator = current_language() == 'de' ? ',' : '.';

        // Get the current date and daily sums date.
        $now = time();
        $dailysumstimestamp = strtotime($date);
        switch (current_language()) {
            case 'de':
                $currentdate = date('d.m.Y', $now);
                $dailysumsdate = date('d.m.Y', $dailysumstimestamp);
                break;
            default:
                $currentdate = date('M d, Y', $now);
                $dailysumsdate = date('M d, Y', $dailysumstimestamp);
                break;
        }

        $dailysumsdata['date'] = $dailysumsdate; // Date.
        $dailysumsdata['printdate'] = $currentdate; // Actual date of today, might be needed in PDF.
        $dailysumsdata['currency'] = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';
        $dailysumsdata['title'] = get_string('titledailysums', 'local_shopping_cart');

        // SQL to get daily sums.
        $dailysumssql = "SELECT payment, sum(price) dailysum
            FROM {local_shopping_cart_ledger}
            WHERE timecreated BETWEEN :startofday AND :endofday
            AND paymentstatus = :paymentsuccess
            GROUP BY payment";

        // SQL params.
        $dailysumsparams = [
            'startofday' => strtotime($date . ' 00:00'),
            'endofday' => strtotime($date . ' 24:00'),
            'paymentsuccess' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        ];

        $dailysumsfromdb = $DB->get_records_sql($dailysumssql, $dailysumsparams);
        // We also calculate the total daily sum.
        $total = 0.0;
        foreach ($dailysumsfromdb as $dailysumrecord) {
            $add = true;
            $dailysumrecord->dailysumformatted = number_format((float)$dailysumrecord->dailysum, 2, $commaseparator, '');
            switch ($dailysumrecord->payment) {
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE:
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodonline', 'local_shopping_cart');
                    $dailysumsdata['online'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER:
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcashier', 'local_shopping_cart');
                    $dailysumsdata['cashier'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH:
                    // Will be a negative number, so we can still use "+=".
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcreditspaidbackcash', 'local_shopping_cart');
                    $dailysumsdata['creditspaidbackcash'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER:
                    // Will be a negative number, so we can still use "+=".
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcreditspaidbacktransfer', 'local_shopping_cart');
                    $dailysumsdata['creditspaidbacktransfer'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH:
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                    $dailysumsdata['cash'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CREDITCARD:
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                    $dailysumsdata['creditcard'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_DEBITCARD:
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                    $dailysumsdata['debitcard'] = $dailysumrecord->dailysumformatted;
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL:
                    $total += (float)$dailysumrecord->dailysum;
                    $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
                    $dailysumsdata['manual'] = $dailysumrecord->dailysumformatted;
                    break;
                default:
                    $add = false;
                    break;
            }
            if ($add) {
                $dailysumsdata['dailysums'][] = (array)$dailysumrecord;
            }
            $dailysumsdata['totalsum'] = number_format($total, 2, $commaseparator, '');
        }

        if (get_config('local_shopping_cart', 'showdailysumscurrentcashier')) {
            // Now get data for current cashier.
            // SQL to get daily sums.
            $dailysumssqlcurrent = "SELECT payment, sum(price) dailysum
                FROM {local_shopping_cart_ledger}
                WHERE timecreated BETWEEN :startofday AND :endofday
                AND paymentstatus = :paymentsuccess
                AND usermodified = :userid
                GROUP BY payment";

            // SQL params.
            $dailysumsparamscurrent = [
                'startofday' => strtotime($date . ' 00:00'),
                'endofday' => strtotime($date . ' 24:00'),
                'paymentsuccess' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
                'userid' => $USER->id,
            ];

            $dailysumsfromdbcurrentcashier = $DB->get_records_sql($dailysumssqlcurrent, $dailysumsparamscurrent);
            foreach ($dailysumsfromdbcurrentcashier as $dailysumrecord) {
                $add = true;
                $dailysumrecord->dailysumformatted = number_format((float)$dailysumrecord->dailysum, 2, $commaseparator, '');
                switch ($dailysumrecord->payment) {
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodonline', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:online'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcashier', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:cashier'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcreditspaidbackcash', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:creditspaidbackcash'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcreditspaidbacktransfer', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:creditspaidbacktransfer'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:cash'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CREDITCARD:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:creditcard'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_DEBITCARD:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:debitcard'] = $dailysumrecord->dailysumformatted;
                        break;
                    case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL:
                        $dailysumrecord->paymentmethod = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
                        $dailysumsdata['currentcashier:manual'] = $dailysumrecord->dailysumformatted;
                        break;
                    default:
                        $add = false;
                        break;
                }
                if ($add) {
                    $dailysumsdata['dailysumscurrentcashier'][] = (array)$dailysumrecord;
                }
            }

            if (!empty($dailysumsdata['dailysumscurrentcashier'])) {
                $dailysumsdata['dailysumscurrentcashier:exist'] = true;
            }

            $dailysumsdata['currentcashier:fullname'] = "$USER->firstname $USER->lastname";
        }

        if (!empty($dailysumsdata['dailysums'])) {
            $dailysumsdata['dailysums:exist'] = true;
        }

        if (!empty($selectorformoutput)) {
            $dailysumsdata['selectorform'] = $selectorformoutput;
        }

        // Add download URL.
        if (!empty($date)) {
            $dailysumsdata['dailysumspdfurl'] = new moodle_url('/local/shopping_cart/daily_sums_pdf.php', ['date' => $date]);
        }

        return $dailysumsdata;
    }

    /**
     * Is rebooking credit.
     *
     * @param string $component
     * @param string $area
     * @return bool
     */
    public static function is_rebookingcredit(string $component, string $area): bool {

        if ($component === 'local_shopping_cart'
            && $area === 'rebookingcredit') {

            return true;
        }

        return false;
    }
}
