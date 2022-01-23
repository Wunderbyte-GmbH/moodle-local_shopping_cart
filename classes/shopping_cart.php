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

use local_shopping_cart\task\delete_item_task;

use function PHPUnit\Framework\isEmpty;

defined('MOODLE_INTERNAL') || die();

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
     * @param array $itemdata
     * @return bool
     */
    public static function add_item_to_cart(&$itemdata): bool {
        global $USER;
        $userid = $USER->id;
        $maxitems = get_config('local_shopping_cart', 'maxitems');
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);

        $cacheitemkey = $itemdata['componentname'] . '-' . $itemdata['itemid'];

        if (isset($cachedrawdata['items'][$cacheitemkey])) {
            // Todo: Admin setting could allow for more than one item. Right now, only one.
            // Therefore: if the item is already in the cart, we just return false.
            return false;
        }

        // Get expirationtimestamp current time + time in settings (from min to s).
        $expirationtimedelta = get_config('local_shopping_cart', 'expirationtime');
        $expirationtimestamp = time() + $expirationtimedelta * 60;

        // Then we set item in Cache.
        $cachedrawdata['items'][$cacheitemkey] = $itemdata;
        $cachedrawdata['expirationdate'] = $expirationtimestamp;
        $cache->set($cachekey, $cachedrawdata);

        // Add or reschedule all delete_item_tasks for all the items in the cart.
        self::add_or_reschedule_addhoc_tasks($expirationtimestamp);

        return true;
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
     *
     * This is to return all parent entities from the database
     * @param int $itemid
     * @param string $component
     * @return boolean
     */
    public static function delete_item_from_cart($itemid, $component): bool {
        global $USER;
        $userid = $USER->id;
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $cachedrawdata = $cache->get($cachekey);
        if ($cachedrawdata) {
            $cacheitemkey = $component . '-' . $itemid;
            if (isset($cachedrawdata['items'][$cacheitemkey])) {
                unset($cachedrawdata['items'][$cacheitemkey]);
                $cache->set($cachekey, $cachedrawdata);
                return true;
            }
        }
        return false;
    }

    /**
     *
     * This is to delete all items from cart.
     *
     * @return bool
     */
    public static function delete_all_items_from_cart(): bool {
        global $USER;
        $userid = $USER->id;
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
     * Asks the payable from the related component.
     *
     * @param string $component Name of the component that the paymentarea and itemid belong to
     * @param int $itemid An internal identifier that is used by the component
     * @return local\entities\cartitem
     */
    public static function get_cartitem(string $component, int $itemid): local\entities\cartitem {
        $providerclass = static::get_service_provider_classname($component);

        return component_class_callback($providerclass, 'get_cartitem', [$itemid]);
    }

    /**
     * local_shopping_cart_get_cache_data.
     *
     * @return array
     */
    public static function local_shopping_cart_get_cache_data(): array {
        global $USER;
        $userid = $USER->id;
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachedrawdata = $cache->get($userid . '_shopping_cart');
        if ($cachedrawdata['expirationdate'] < time()) {
            self::delete_all_items_from_cart();
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
     * @return void
     */
    private static function add_or_reschedule_addhoc_tasks(int $expirationtimestamp) {
        global $USER;
        $userid = $USER->id;

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
}
