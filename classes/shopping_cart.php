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

use context_system;
use local_shopping_cart\task\delete_item_task;

define('PAYMENT_PENDING', 0); // First entry in shopping cart history. This means that payment was initiated, but not successfully completed.
define('PAYMENT_ABORTED', 1); // Pending will be switched to aborted, once we can be sure that the payment process will not be continued.
define('PAYMENT_SUCCESS', 2); // Payment was successful.
define('PAYMENT_CANCELED', 3); // Canceled payments mean that they paid for items are canceled after successful checkout.

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
        if ($userid == 0) {
            $context = context_system::instance();
            if (has_capability('local/shopping_cart:cachier', $context)) {
                $userid = self::return_buy_for_userid();
            } else {
                $userid = $USER->id;
            }
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
            $itemdata['success'] = 1;
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
            $cache->set($cachekey, null);
        }
        return true;
    }



    /**
     *
     * A possibility to easily add random items for testing.
     * @return bool
     */
    public static function add_random_item() {
        global $USER;
        $userid = $USER->id;
        $itemdata['componentname'] = "componentname";
        $sports = array("Lacrosse", "Roller derby", "Basketball", "Tennis",
                        "Rugby", "Bowling", "Fencing", "Baseball", "Crew", "Cheerleading",
                        "Baseball", "Roller derby", "Baseball", "Baseball", "Boxing", "Endurance Running",
                        "Ultimate", "Curling", "Wrestling", "Surfing", "Horse Racing", "Auto Racing",
                        "Soccer", "Fencing", "Gynastics", "Lacrosse", "Skateboarding",
                        "Track", "Soccer", "Crew", "Skiing", "Poker", "Lacrosse", "Auto Racing",
                        "Endurance Running", "Curling", "Cricket", "Wiffleball",
                        "Wrestling", "Snowboarding", "Skateboarding", "Skateboarding",
                        "Poker", "Mixed Martial Arts", "Ice Hockey", "Badminton", "Surfing",
                        "Field Hockey", "Endurance Running", "Horse Racing", "Bowling", "Bobsleigh",
                        "Bobsleigh", "Basketball", "Cheerleading", "Mixed Martial Arts", "Field Hockey",
                        "Curling", "Skiing", "Soccer", "Curling", "Cricket", "Rugby", "Curling",
                        "Bobsleigh", "Cheerleading", "Baseball", "Competitive Swimming",
                        "Curling", "Curling", "Horse Racing", "Polo", "Tennis", "Football",
                        "Polo", "Golf", "Volleyball", "Lacrosse", "Golf", "Tennis", "Wrestling",
                        "Cricket", "Endurance Running", "Basketball", "Track", "Polo", "Field Hockey",
                        "Wiffleball", "Rowing", "Lacrosse", "Competitive Swimming", "Endurance Running",
                        "Snowboarding", "Horse Racing", "Baseball", "Skateboarding", "Pool",
                        "Mixed Martial Arts", "Snowboarding", "Surfing", "Polo", "Skateboarding",
                        "Poker", "Bowling", "Crew", "Ice Hockey", "Wrestling", "Cheerleading", "Polo",
                        "Rugby", "Crew", "Weightlifting", "Skiing", "Skateboarding", "Horse Racing",
                        "Bowling", "Weightlifting", "Rugby", "Roller derby", "Badminton");
        $rand = array_rand($sports, 1);
        $itemdata['itemid'] = time() - $rand + 7 * rand(5, 115);
        $itemdata['itemname'] = $sports[$rand];
        $itemdata['price'] = rand(5, 115);
        $itemdata['expirationdate'] = time() + rand(1, 1) * 60;
        $itemdata['description'] = "asdadasdsad";
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachedrawdata = $cache->get($userid . '_shopping_cart');
        $cachedrawdata['items'][ $itemdata['itemid'] ] = $itemdata;
        $cache->set($userid . '_shopping_cart', $cachedrawdata);
        $event = event\item_added::create_from_ids($userid, $itemdata);
        $event->trigger();
        return $itemdata;
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

        return component_class_callback($providerclass, 'successful_checkout', [$itemid, 'cash', $userid]);
    }

    /**
     * Function local_shopping_cart_get_cache_data
     * @param int $userid
     * @return array
     */
    public static function local_shopping_cart_get_cache_data($userid): array {

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachedrawdata = $cache->get($userid . '_shopping_cart');
        if ($cachedrawdata) {
            if ($cachedrawdata['expirationdate'] < time()) {
                self::delete_all_items_from_cart($userid);
            }
        }
        $data = [];

        $data['count'] = 0;
        $data['expirationdate'] = time();
        $data['maxitems'] = get_config('local_shopping_cart', 'maxitems');
        $data['items'] = [];
        $data['price'] = 0;

        if ($cachedrawdata && isset($cachedrawdata['items'])) {
            $count = count($cachedrawdata['items']);
            $data['count'] = $count;

            if ($count > 0) {
                $data['items'] = array_values($cachedrawdata['items']);
                $data['price'] = array_sum(array_column($data['items'], 'price'));
                $data['expirationdate'] = $cachedrawdata['expirationdate'];
            }
        }
        return $data;
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
     * @param array $datafromhistory
     * @return void
     */
    public static function confirm_payment($userid, $datafromhistory = null) {
        global $USER;

        // If the data comes from history, we don't need to check rights.
        if (!$datafromhistory) {
            // Make sure the user has the rights to access this function.
            $context = context_system::instance();
            if (!has_capability('local/shopping_cart:cachier', $context)) {
                return [
                    'status' => 0,
                    'error' => get_string('nopermission', 'local_shopping_cart')
                ];
            }

            $identifier = time();
        }

        if (!$data = $datafromhistory) {
            // Retrieve items from cache.
            $data = self::local_shopping_cart_get_cache_data($userid);
        }

        // Check if we have items for this user.
        if (!isset($data['items'])
            || count($data['items']) < 1) {
            return [
                'status' => 0,
                'error' => get_string('noitemsincart', 'local_shopping_cart')
            ];
        }

        $success = true;
        $error = [];

        // Run through all items still in the cart and confirm payment.
        foreach ($data['items'] as $item) {

            // We might retrieve the items from history or via cache. Form history, the come as stdClass.

            $item = (array)$item;

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
                    shopping_cart_history::create_entry_in_history(
                        $userid,
                        $item['itemid'],
                        $item['itemname'],
                        $item['price'],
                        $item['currency'],
                        $item['componentname'],
                        $identifier,
                        'cash',
                        PAYMENT_SUCCESS
                        );
                }

            }
        }

        if ($success) {

            return [
                'status' => 1,
                'error' => ''
            ];
        } else {
            return [
                'status' => 0,
                'error' => implode('<br>', $error)
            ];
        }
    }
}
