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
 * @package local_shopping_cart
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use context_system;
use local_shopping_cart\task\delete_item_task;

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
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function add_item_to_cart(string $component, int $itemid, int $userid): array {

        global $USER;

        // If there is no user specified, we determine it automatically.
        if ($userid < 0) {
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

        $success = true;

        // Check the cache for items in cart.
        $maxitems = get_config('local_shopping_cart', 'maxitems');
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        $cacheitemkey = $component . '-' . $itemid;

        // Check if maxitems is exceeded.
        if (isset($maxitems) && (count($cachedrawdata['items']) >= $maxitems)) {
            $success = false;
        }

        // Todo: Admin setting could allow for more than one item. Right now, only one.
        // Therefore: if the item is already in the cart, we just return false.
        if ($success && isset($cachedrawdata['items'][$cacheitemkey])) {
            $success = false;
            $itemdata = $cachedrawdata['items'][$cacheitemkey];
        }

        $expirationtimestamp = self::get_expirationdate();

        if ($success) {
            // This gets the data from the componennt and also triggers reserveration.
            // If reserveration is not successful, we have to react here.
            if ($cartitem = self::load_cartitem($component, $itemid, $userid)) {
                // Get the itemdata as array.
                $itemdata = $cartitem->getitem();

                // Then we set item in Cache.
                $cachedrawdata['items'][$cacheitemkey] = $itemdata;
                $cachedrawdata['expirationdate'] = $expirationtimestamp;
                $cache->set($cachekey, $cachedrawdata);

                $itemdata['expirationdate'] = $expirationtimestamp;
                $itemdata['success'] = 1;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;

                // Add or reschedule all delete_item_tasks for all the items in the cart.
                self::add_or_reschedule_addhoc_tasks($expirationtimestamp, $userid);
            } else {
                $success = false;
                $itemdata = [];
                $itemdata['success'] = 0;
                $itemdata['expirationdate'] = 0;
            }
        } else {
            // This case means that we have the item already in the cart.
            // Normally, this should not happen, because of JS, but it might occure when a user is...
            // Logged in on two different devices.
            $itemdata['success'] = 2;
            $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
            $itemdata['expirationdate'] = 0;
        }

        return $itemdata;
    }

    /**
     * Get expiration date time plus delta from config.
     *
     * @return integer
     */
    public static function get_expirationdate(): int {
        return time() + get_config('local_shopping_cart', 'expirationtime') * 60;
    }

    /**
     * This is to return all parent entities from the database
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     * @param bool $unload
     * @return boolean
     */
    public static function delete_item_from_cart($component, $itemid, $userid, $unload = true): bool {

        global $USER;

        $userid = $userid == 0 ? $USER->id : $userid;

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        if ($cachedrawdata) {
            $cacheitemkey = $component . '-' . $itemid;
            if (isset($cachedrawdata['items'][$cacheitemkey])) {
                unset($cachedrawdata['items'][$cacheitemkey]);
                $cache->set($cachekey, $cachedrawdata);
            }
        }

        if ($unload) {
            // This treats the related component side.
            self::unload_cartitem($component, $itemid, $userid);
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
    public static function delete_all_items_from_cart($userid): bool {

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        if ($cachedrawdata) {

            unset($cachedrawdata['items']);

            $cache->set($cachekey, $cachedrawdata);
        }
        return true;
    }

    /**
     * Get the name of the service provider class
     *
     * @param string $component The component
     * @return string
     * @throws \coding_exception
     */
    private static function get_service_provider_classname(string $component) {
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
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function load_cartitem(string $component, int $itemid, int $userid): local\entities\cartitem {
        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'load_cartitem', [$itemid, $userid]);
    }

    /**
     * Unloads the cartitem from the related component.
     *
     * @param string $component Name of the component that the cartitems belong to
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function unload_cartitem(string $component, int $itemid, int $userid): bool {
        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'unload_cartitem', [$itemid, $userid]);
    }

    /**
     * Confirms Payment and successful checkout for item.
     *
     * @param string $component Name of the component that the cartitems belong to
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function successful_checkout(string $component, int $itemid, int $userid): bool {
        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'successful_checkout', [$itemid, PAYMENT_METHOD_CASHIER, $userid]);
    }

    /**
     * Cancels Purchase.
     *
     * @param string $component Name of the component that the cartitems belong to
     * @param int $itemid An internal identifier that is used by the component
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function cancel_purchase_for_component(string $component, int $itemid, int $userid): bool {

        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'cancel_purchase', [$itemid, $userid]);
    }

    /**
     * Function local_shopping_cart_get_cache_data
     * This function returns all the item and calculates live the price for them.
     * This function also supports the credit system of this modle.
     * If usecredit is true, the credit of the user is substracted from price...
     * ... and supplementary informatin about the subsctraction is returend.
     *
     * @param int $userid
     * @param bool $usecredit
     * @return array
     */
    public static function local_shopping_cart_get_cache_data(int $userid, bool $usecredit = null): array {

        global $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';
        $cachedrawdata = $cache->get($cachekey);
        if ($cachedrawdata) {
            if (isset($cachedrawdata['expirationdate'])
                && $cachedrawdata['expirationdate'] < time()) {
                self::delete_all_items_from_cart($userid);
                unset($cachedrawdata['expirationdate']);
                $cachedrawdata = $cache->get($cachekey);
            }
        }
        $data = [];

        $data['count'] = 0;
        $data['expirationdate'] = time();
        $data['maxitems'] = get_config('local_shopping_cart', 'maxitems');
        $data['items'] = [];
        $data['price'] = 0;

        if ($userid && (!isset($cachedrawdata['credit']) || !isset($cachedrawdata['currency']))) {
            list($data['credit'], $data['currency']) = shopping_cart_credits::get_balance($userid);
            $cachedrawdata['credit'] = $data['credit'];
            $cachedrawdata['currency'] = $data['currency'];

        } else {
            $data['credit'] = $cachedrawdata['credit'];
            $data['currency'] = $cachedrawdata['currency'];
        }

        if ($cachedrawdata && isset($cachedrawdata['items'])) {
            $count = count($cachedrawdata['items']);
            $data['count'] = $count;

            if ($count > 0) {
                $data['items'] = array_values($cachedrawdata['items']);
                $data['price'] = array_sum(array_column($data['items'], 'price'));
                $data['expirationdate'] = $cachedrawdata['expirationdate'];
            }
            // There might be cases where we don't have the currency yet. We take it from the last item in cart.
            if (!$data['currency']) {
                $data['currency'] = end($data['items'])['currency'] ?? "";
            }
        }

        // If there is credit for this user, we give her options.
        shopping_cart_credits::prepare_checkout($data, $userid, $usecredit);

        return $data;
    }

    /**
     * Returns 0|1 fore the saved usecredit state, null if no such state exists.
     *
     * @param int $userid
     * @return null|int
     */
    public static function get_saved_usecredit_state($userid) {
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';
        $cachedrawdata = $cache->get($cachekey);

        if ($cachedrawdata && isset($cachedrawdata['usecredit'])) {
            return $cachedrawdata['usecredit'];
        } else {
            return null;
        }
    }

    /**
     * Sets the usecredit value in Cache for the user.
     *
     * @param int $userid
     * @param bool $usecredit
     * @return void
     */
    public static function save_used_credit_state($userid, $usecredit) {

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';
        $cachedrawdata = $cache->get($cachekey);

        $cachedrawdata['usecredit'] = $usecredit;

        $cache->set($cachekey, $cachedrawdata);
    }

    /**
     * To add or reschedule addhoc tasks to delete all the items once the shopping cart is expired.
     * As the expiration date is always calculated by the cart, not the item, this always updates the whole cart.
     *
     * @param int $expirationtimestamp
     * @param int $userid
     * @return void
     */
    private static function add_or_reschedule_addhoc_tasks(int $expirationtimestamp, int $userid) {

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);

        if (!$cachedrawdata
            || !isset($cachedrawdata['items'])
            || (count($cachedrawdata['items']) < 1)) {
                return;
        }
        // Now we schedule tasks to delete item from cart after some time.
        foreach ($cachedrawdata['items'] as $taskdata) {
            $deleteitemtask = new delete_item_task();
            $deleteitemtask->set_userid($userid);
            $deleteitemtask->set_next_run_time($expirationtimestamp);
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
    public static function buy_for_user($userid) {
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
     * @param int $userid
     * @param string $paymentmethod
     * @param array $datafromhistory
     * @return void
     */
    public static function confirm_payment($userid, $paymenttype, $datafromhistory = null) {
        global $USER;

        $identifier = 0;

        // When the function is called from webservice, we don't have a $datafromhistory array.
        if (!$data = $datafromhistory) {
            // Retrieve items from cache.
            $data = self::local_shopping_cart_get_cache_data($userid);

            // Now, this can happen in two cases. Either, a user wants to pay with his credits for his item.
            // This can only go through, when price is 0.

            $context = context_system::instance();

            if ($userid == $USER->id) {
                if ($data['price'] == 0) {
                    // The user wants to pay for herself with her credits and she has enough.
                    // We actually don't need to do anything here.
                    $data['price'] = 0;

                } else if (!has_capability('local/shopping_cart:cashier', $context)) {
                    // The cashier could call this to pay for herself, therefore only for non cashiers, we return here.
                    return [
                        'status' => 0,
                        'error' => get_string('notenoughcredit', 'local_shopping_cart'),
                        'credit' => $data['remainingcredit'],
                        'identifier' => $identifier
                    ];
                }
            } else {
                if (!has_capability('local/shopping_cart:cashier', $context)) {
                    return [
                        'status' => 0,
                        'error' => get_string('nopermission', 'local_shopping_cart'),
                        'credit' => '',
                        'identifier' => $identifier
                    ];
                }
            }

            // Now the user either has enough credit to pay for herself, or she is a cashier.
            $identifier = time();

        } else {

            // Even if we get the data from history, we still need to look in cache.
            // With this, we will know how much the user actually paid and how much comes from her credits.
            shopping_cart_credits::prepare_checkout($data, $userid);
        }

        // Check if we have items for this user.
        if (!isset($data['items'])
            || count($data['items']) < 1) {
            return [
                'status' => 0,
                'error' => get_string('noitemsincart', 'local_shopping_cart'),
                'credit' => '',
                'identifier' => $identifier
            ];
        }

        $success = true;
        $error = [];

        // Run through all items still in the cart and confirm payment.
        foreach ($data['items'] as $item) {

            // We might retrieve the items from history or via cache. From history, they come as stdClass.

            $item = (array)$item;

            // If the item identifier is specified (this is only the case, when we get data from history)...
            // ... we use the identifier. Else, it stays the same.
            $identifier = $item['identifier'] ?? $identifier;

            // In the shoppingcart history, we don't store the name.
            if (!isset($item['itemname'])) {
                $item['itemname'] = $item['itemid'];
            }

            if (!self::successful_checkout($item['componentname'], $item['itemid'], $userid)) {
                $success = false;
                $error[] = get_string('itemcouldntbebought', 'local_shopping_cart', $item['itemname']);
            } else {
                // Delete Item from cache.
                // Here, we don't need to unload the component, so the last parameter is false.
                self::delete_item_from_cart($item['componentname'], $item['itemid'], $userid, false);

                // We create this entry only for cash payment, that is when there is no datafromhistory yet.
                if (!$datafromhistory) {

                    // If this is paid for with credits, we want to have this on record.
                    // Also, price is then 0, but we want to know the real price.
                    $paymentmethod = $data['price'] == 0 ? PAYMENT_METHOD_CREDITS : PAYMENT_METHOD_CASHIER;

                    shopping_cart_history::create_entry_in_history(
                        $userid,
                        $item['itemid'],
                        $item['itemname'],
                        $item['price'],
                        $item['currency'],
                        $item['componentname'],
                        $identifier,
                        $paymentmethod,
                        PAYMENT_SUCCESS
                    );
                }

            }
        }

        // In our ledger, the credits table, we add an entry and make sure we actually deduce any credit we might have.
        if (isset($data['usecredit'])
            && $data['usecredit'] === true) {
                shopping_cart_credits::use_credit($userid, $data);
        } else {
            $data['remainingcredit'] = 0;
        }

        if ($success) {

            return [
                'status' => 1,
                'error' => '',
                'credit' => $data['remainingcredit'],
                'identifier' => $identifier

            ];
        } else {
            return [
                'status' => 0,
                'error' => implode('<br>', $error),
                'credit' => $data['remainingcredit'],
                'identifier' => $identifier
            ];
        }
    }


    /**
     * Function to cancel purchase of item. The price of the item will be handled as a credit for the next purchases.
     * @param integer $itemid
     * @param integer $userid
     * @param string $component
     * @param integer|null $historyid
     * @return array
     */
    public static function cancel_purchase(int $itemid, int $userid, string $componentname,
        int $historyid = null, float $customcredit = 0): array {

        // Cancelation is only allowed for cashiers.
        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
             return [
                 'success' => 0,
                 'error' => get_string('nopermission', 'local_shopping_cart'),
                 'credit' => 0
             ];
        }

        if (!self::cancel_purchase_for_component($componentname, $itemid, $userid)) {
            return [
                'success' => 0,
                'error' => get_string('canceldidntwork', 'local_shopping_cart'),
                'credit' => 0
            ];
        }

        list($success, $error, $credit, $currency) = shopping_cart_history::cancel_purchase($itemid,
            $userid, $componentname, $historyid);

        if (empty($customcredit)) {
            $customcredit = $credit;
        }

        if ($success == 1) {
            // If the payment was successfully canceled, we can book the credits to the users balance.

            list($newcredit) = shopping_cart_credits::add_credit($userid, $customcredit, $currency);
        }

        return [
            'success' => $success,
            'error' => $error,
            'credit' => $newcredit
        ];
    }

    /**
     * Sets credit to 0, because we get information about cash pay-back.
     *
     * @param integer $userid
     * @return array
     */
    public static function credit_paid_back(int $userid):array {

        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            return [
                'status' => 0,
                'error' => get_string('nopermission', 'local_shopping_cart'),
            ];
        }

        if (!shopping_cart_credits::credit_paid_back($userid)) {
            return [
                'status' => 0,
                'error' => 'couldntpayback'
            ];
        }

        return [
            'status' => 1,
            'error' => ''
        ];
    }
}
