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
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use context_system;
use local_shopping_cart\task\delete_item_task;
use moodle_exception;
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
    public static function add_item_to_cart(string $component, string $area, int $itemid, int $userid): array {

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
        $cacheitemkey = $component . '-' . $area . '-' . $itemid;

        // Check if maxitems is exceeded.
        if (isset($maxitems) && isset($cachedrawdata['items']) && (count($cachedrawdata['items']) >= $maxitems)) {
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
            $cartitemarray = self::load_cartitem($component, $area, $itemid, $userid);
            if (isset($cartitemarray['cartitem'])) {
                // Get the itemdata as array.
                $itemdata = $cartitemarray['cartitem']->as_array();

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
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
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
     * @return int
     */
    public static function get_expirationdate(): int {
        return time() + get_config('local_shopping_cart', 'expirationtime') * 60;
    }

    /**
     * This is to return all parent entities from the database
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

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        if ($cachedrawdata) {
            $cacheitemkey = $component . '-' . $area . '-' . $itemid;
            if (isset($cachedrawdata['items'][$cacheitemkey])) {
                unset($cachedrawdata['items'][$cacheitemkey]);
                $cache->set($cachekey, $cachedrawdata);
            }
        }

        if ($unload) {
            // This treats the related component side.
            self::unload_cartitem($component, $area, $itemid, $userid);
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

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        if ($cachedrawdata) {

            unset($cachedrawdata['items']);
            unset($cachedrawdata['expirationdate']);

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
     * @return bool
     */
    public static function unload_cartitem(string $component, string $area, int $itemid, int $userid): bool {
        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'unload_cartitem', [$area, $itemid, $userid]);
    }

    /**
     * Confirms Payment and successful checkout for item.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @return local\entities\cartitem
     */
    public static function successful_checkout(string $component, string $area, int $itemid, int $userid): bool {
        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'successful_checkout', [$area, $itemid, PAYMENT_METHOD_CASHIER, $userid]);
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
     * @param int $userid
     * @param bool $usecredit
     * @return array
     */
    public static function local_shopping_cart_get_cache_data(int $userid, bool $usecredit = null): array {
        global $USER, $CFG;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $usecredit = shopping_cart_credits::use_credit_fallback($usecredit, $userid);
        $taxesenabled = get_config('local_shopping_cart', 'enabletax') == 1;
        if ($taxesenabled) {
            $taxcategories = taxcategories::from_raw_string(
                    get_config('local_shopping_cart', 'defaulttaxcategory'),
                    get_config('local_shopping_cart', 'taxcategories')
            );
        } else {
            $taxcategories = null;
        }

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';
        $cachedrawdata = $cache->get($cachekey);

        // If we have cachedrawdata, we need to check the expiration date.
        if ($cachedrawdata) {
            if (isset($cachedrawdata['expirationdate']) && !is_null($cachedrawdata['expirationdate'])
                    && $cachedrawdata['expirationdate'] < time()) {
                self::delete_all_items_from_cart($userid);
                $cachedrawdata = $cache->get($cachekey);
            }
        }

        // We create a new item to pass on in any case.
        $data = [];
        $data['userid'] = $userid;
        $data['count'] = 0;

        $data['maxitems'] = get_config('local_shopping_cart', 'maxitems');
        $data['items'] = [];
        $data['price'] = 0.00;
        $data['taxesenabled'] = $taxesenabled;
        $data['initialtotal'] = 0.00;
        $data['deductible'] = 0.00;
        $data['checkboxid'] = bin2hex(random_bytes(3));
        $data['usecredit'] = $usecredit;
        $data['expirationdate'] = time();
        $data['checkouturl'] = $CFG->wwwroot . "/local/shopping_cart/checkout.php";

        if (!$cachedrawdata) {
            list($data['credit'], $data['currency']) = shopping_cart_credits::get_balance($userid);
            $data['items'] = [];
            $data['remainingcredit'] = $data['credit'];

        } else if ($cachedrawdata) {
            $count = isset($cachedrawdata['items']) ? count($cachedrawdata['items']) : 0;
            $data['count'] = $count;

            $data['currency'] = $cachedrawdata['currency'] ?? null;
            $data['credit'] = $cachedrawdata['credit'] ?? null;
            $data['remainingcredit'] = $data['credit'];

            if ($count > 0) {
                // We need the userid in every item.
                $items = array_map(function($item) use ($USER, $userid) {
                    $item['userid'] = $userid != $USER->id ? -1 : 0;
                    return $item;
                }, $cachedrawdata['items']);

                $data['items'] = self::update_item_price_data(array_values($items), $taxcategories);

                $data['price'] = self::calculate_total_price($data["items"]);
                if ($taxesenabled) {
                    $data['price_net'] = self::calculate_total_price($data["items"], true);
                }
                $data['discount'] = array_sum(array_column($data['items'], 'discount'));
                $data['expirationdate'] = $cachedrawdata['expirationdate'];
            }
        }

        // There might be cases where we don't have the currency or credit yet. We take it from the last item in our cart.
        if (empty($data['currency']) && (count($data['items']) > 0)) {
            $data['currency'] = end($data['items'])['currency'];
        } else if (empty($data['currency'])) {
            $data['currency'] = '';
        }
        $data['credit'] = $data['credit'] ?? 0.00;

        if ($cachedrawdata && count($data['items']) > 0) {
            // If there is credit for this user, we give her options.
            shopping_cart_credits::prepare_checkout($data, $userid, $usecredit);
        } else if (count($data['items']) == 0) {
            // If not, we save the cache right away.
            $cache->set($cachekey, $data);
        }

        return $data;
    }

    /**
     * Returns 0|1 fore the saved usecredit state, null if no such state exists.
     *
     * @param int $userid
     * @return null|int
     */
    public static function get_saved_usecredit_state(int $userid) {
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
    public static function save_used_credit_state(int $userid, bool $usecredit) {
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
     * @param int $paymenttype
     * @param array $datafromhistory
     * @return array
     */
    public static function confirm_payment(int $userid, int $paymenttype, array $datafromhistory = null) {
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

            // Now we need to store the new credit balance.
            if ($data['deductible'] > 0) {
                shopping_cart_credits::use_credit($userid, $data);
            }
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

            $item = (array) $item;

            // If the item identifier is specified (this is only the case, when we get data from history)...
            // ... we use the identifier. Else, it stays the same.
            $identifier = $item['identifier'] ?? $identifier;

            // In the shoppingcart history, we don't store the name.
            if (!isset($item['itemname'])) {
                $item['itemname'] = $item['itemid'];
            }

            if (!self::successful_checkout($item['componentname'], $item['area'], $item['itemid'], $userid)) {
                $success = false;
                $error[] = get_string('itemcouldntbebought', 'local_shopping_cart', $item['itemname']);
            } else {
                // Delete Item from cache.
                // Here, we don't need to unload the component, so the last parameter is false.
                self::delete_item_from_cart($item['componentname'], $item['area'], $item['itemid'], $userid, false);

                // We create this entry only for cash payment, that is when there is no datafromhistory yet.
                if (!$datafromhistory) {

                    // If this is paid for with credits, we want to have this on record.
                    // Also, price is then 0, but we want to know the real price.
                    $paymentmethod = $data['price'] == 0 ? PAYMENT_METHOD_CREDITS : PAYMENT_METHOD_CASHIER;

                    if ($paymentmethod === PAYMENT_METHOD_CASHIER) {
                        // We now need to specify the actual payment method (cash, debit or credit card).
                        switch ($paymenttype) {
                            case PAYMENT_METHOD_CASHIER_CASH:
                            case PAYMENT_METHOD_CASHIER_CREDITCARD:
                            case PAYMENT_METHOD_CASHIER_DEBITCARD:
                                $paymentmethod = $paymenttype;
                                break;
                        }
                    }

                    // Make sure we can pass on a valid value.
                    $item['discount'] = $item['discount'] ?? 0;

                    shopping_cart_history::create_entry_in_history(
                            $userid,
                            $item['itemid'],
                            $item['itemname'],
                            $item['price'],
                            $item['discount'],
                            $item['currency'],
                            $item['componentname'],
                            $item['area'],
                            $identifier,
                            $paymentmethod,
                            PAYMENT_SUCCESS,
                            $item['canceluntil'] ?? null,
                            $item['serviceperiodstart'] ?? null,
                            $item['serviceperiodend'] ?? null,
                            $item['tax'] ?? null,
                            $item['taxpercentage'] ?? null,
                            $item['taxcategory'] ?? null,
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
     *
     * @param int $itemid
     * @param int $userid
     * @param string $componentname
     * @param string $area
     * @param int|null $historyid
     * @param float $customcredit
     * @param float $cancelationfee
     * @return array
     */
    public static function cancel_purchase(int $itemid, string $area, int $userid, string $componentname,
            int $historyid = null, float $customcredit = 0.0, float $cancelationfee = 0.0): array {

        global $USER;

        // A user can only cancel for herself, unless she is cashier.
        if ($USER->id != $userid) {
            $context = context_system::instance();
            if (!has_capability('local/shopping_cart:cashier', $context)) {
                return [
                    'success' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart'),
                    'credit' => 0
                ];
            }
        }

        // Check if cancelation is still within the allowed periode set in shopping_cart_history.
        if (!self::allowed_to_cancel($historyid, $itemid, $area, $userid)) {
            return [
                    'success' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart'),
                    'credit' => 0
            ];
        }

        if (!self::cancel_purchase_for_component($componentname, $area, $itemid, $userid)) {
            return [
                    'success' => 0,
                    'error' => get_string('canceldidntwork', 'local_shopping_cart'),
                    'credit' => 0
            ];
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
        if (!has_capability('local/shopping_cart:cashier', $context)) {
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
     * @param int $userid
     * @return array
     */
    public static function credit_paid_back(int $userid): array {

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
    public static function allowed_to_cancel(int $historyid, int $itemid, string $area, int $userid):bool {

        global $DB;

        if (!$item = shopping_cart_history::return_item_from_history($historyid, $itemid, $area, $userid)) {
            return false;
        }

        // We can only cancel items that are successfully paid.
        if ($item->paymentstatus != PAYMENT_SUCCESS) {
            return false;
        }

        $cancelationfee = get_config('local_shopping_cart', 'cancelationfee');

        // If the cancelationfee is < 0, or the time has expired, the user is not allowed to cancel.
        if (($cancelationfee < 0) || ($item->canceluntil < time())) {
            // Cancelation after time has expired is only allowed for cashiers.
            $context = context_system::instance();
            if (!has_capability('local/shopping_cart:cashier', $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     *
     * Add discount to item.
     * - First we check if the item is here.
     * - Now we add the discount to the cart.
     * - For any fail, we return success 0.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @param float $percent
     * @param float $absolute
     * @return array
     */
    public static function add_discount_to_item(
        string $component,
        string $area,
        int $itemid,
        int $userid,
        float $percent,
        float $absolute): array {

        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        $cacheitemkey = $component . '-' . $area . '-' . $itemid;

        // Item has to be there.
        if (!isset($cachedrawdata['items'][$cacheitemkey])) {
            throw new moodle_exception('itemnotfound', 'local_shopping_cart');
        }

        $item = $cachedrawdata['items'][$cacheitemkey];

        // The undiscounted price of the item is price + discount.
        $initialdiscount = $item['discount'] ?? 0;

        // If setting to round discounts is turned on, we round to full int.
        $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;
        $initialdiscount = round($initialdiscount, $discountprecision);

        $initialprice = $item['price'] + $initialdiscount;

        if (!empty($percent)) {

            // Validation of percent value.
            if ($percent < 0 || $percent > 100) {
                throw new moodle_exception('absolutevalueinvalid', 'local_shopping_cart');
            }
            $cachedrawdata['items'][$cacheitemkey]['discount'] = $initialprice / 100 * $percent;

            // If setting to round discounts is turned on, we round to full int.
            $cachedrawdata['items'][$cacheitemkey]['discount'] = round($cachedrawdata['items'][$cacheitemkey]['discount'],
                    $discountprecision);

            $cachedrawdata['items'][$cacheitemkey]['price'] =
                    $initialprice - $cachedrawdata['items'][$cacheitemkey]['discount'];
        } else if (!empty($absolute)) {
            // Validation of absolute value.
            if ($absolute < 0 || $absolute > $initialprice) {
                throw new moodle_exception('absolutevalueinvalid', 'local_shopping_cart');
            }
            $cachedrawdata['items'][$cacheitemkey]['discount'] = $absolute;
            // If setting to round discounts is turned on, we round to full int.
            $cachedrawdata['items'][$cacheitemkey]['discount'] = round($cachedrawdata['items'][$cacheitemkey]['discount'],
                    $discountprecision);
            $cachedrawdata['items'][$cacheitemkey]['price'] =
                    $initialprice - $cachedrawdata['items'][$cacheitemkey]['discount'];
        } else {
            // If both are empty, we unset discount.
            $cachedrawdata['items'][$cacheitemkey]['price'] = $initialprice;
            unset($cachedrawdata['items'][$cacheitemkey]['discount']);
        }

        // We write the modified data back to cache.
        $cache->set($cachekey, $cachedrawdata);

        return ['success' => 1];
    }

    /**
     * Helper function to add entries to local_shopping_cart_ledger table.
     *
     * @param stdClass $record the record to add to the ledger table
     * @return bool true if successful, else false
     */
    public static function add_record_to_ledger_table(stdClass $record) {
        global $DB;
        $success = true;
        switch ($record->paymentstatus) {
            case PAYMENT_SUCCESS:
                if (!$DB->insert_record('local_shopping_cart_ledger', $record)) {
                    $success = false;
                }
                break;
            case PAYMENT_CANCELED:
                $record->price = null;
                $record->discount = null;
                if (!$DB->insert_record('local_shopping_cart_ledger', $record)) {
                    $success = false;
                }
                break;
            case PAYMENT_ABORTED:
            case PAYMENT_PENDING:
            default:
                $success = false;
                break;
        }
        return $success;
    }

    /**
     * Enriches the cart item with tax information if given
     *
     * @param $items array of cart items
     * @param taxcategories|null $taxcategories
     * @return array
     */
    public static function update_item_price_data(array $items, ?taxcategories $taxcategories): array {
        global $USER;
        $countrycode = null; // TODO get countrycode from user info.

        foreach ($items as $key => $item) {

            if ($taxcategories) {
                $taxpercent = $taxcategories->tax_for_category($item['taxcategory'], $countrycode);
                if ($taxpercent >= 0) {
                    $items[$key]['taxpercentage_visual'] = round($taxpercent * 100, 2);
                    $items[$key]['taxpercentage'] = round($taxpercent, 2);
                    $netprice = $items[$key]['price']; // Price is now considered a net price.
                    $grossprice = round($netprice * (1 + $taxpercent), 2);
                    $items[$key]['price_net'] = $netprice;
                    // Add tax to price (= gross price).
                    $items[$key]['price_gross'] = $grossprice;
                    // And net tax info.
                    $items[$key]['tax'] = $grossprice - $netprice;
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

        // First we calculate the quota from the item.
        $providerclass = static::get_service_provider_classname($component);
        $quota = component_class_callback($providerclass, 'quota_consumed', [$area, $itemid, $userid]);

        $item = shopping_cart_history::return_item_from_history($historyid, $itemid, $area, $userid);

        // Now get the historyitem in order to check the initial price and calculate the rest.
        if ($quota >= 0 && $item) {
            $initialprice = (float)$item->price;
            $remainingvalue = $initialprice - ($initialprice * $quota);
            $currency = $item->currency;
            $cancelationfee = get_config('local_shopping_cart', 'cancelationfee');
            $success = $cancelationfee < 0 ? 0 : 1; // Cancelation not allowed.
        }

        return [
            'success' => $success ?? 0,
            'quota' => $quota ?? 0,
            'remainingvalue' => $remainingvalue ?? 0,
            'initialprice' => $initialprice ?? 0,
            'currency' => $currency ?? '',
            'cancelationfee' => $cancelationfee ?? 0,
        ];
    }
}
