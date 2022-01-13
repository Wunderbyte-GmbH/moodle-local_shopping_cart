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
        if (!isset($itemdata['expirationdate'])) {
            $itemdata['expirationdate'] = time() + 15 * 60;
        }
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachedrawdata = $cache->get($userid . '_shopping_cart');
        $cachedrawdata['item'][$itemdata['itemid']] = $itemdata;
        $cache->set($userid . '_shopping_cart', $cachedrawdata);
        /*$event = event\item_added::create(

        );
        $event->trigger();*/
        return true;
    }
    /**
     *
     * This is to return all parent entities from the database
     * @param int $itemid
     * @return bool
     */
    public static function delete_item_from_cart($itemid): bool {
        global $USER;
        $userid = $USER->id;
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachedrawdata = $cache->get($userid . '_shopping_cart');
        unset($cachedrawdata['item'][$itemid]);
        $cache->set($userid . '_shopping_cart', $cachedrawdata);
        return true;
    }

    /**
     *
     * This is to return all parent entities from the database
     * @param int $itemid
     * @return bool
     */
    public static function add_random_item() {
        global $USER;
        $userid = $USER->id;
        $itemdata['modul'] = "booking";
        $sports = array("Lacrosse", "Roller derby", "Basketball", "Tennis", "Rugby", "Bowling", "Fencing", "Baseball", "Crew", "Cheerleading",
         "Baseball", "Roller derby", "Baseball", "Baseball", "Boxing", "Endurance Running", "Ultimate", "Curling",
          "Wrestling", "Surfing", "Horse Racing", "Auto Racing", "Soccer", "Fencing", "Gynastics", "Lacrosse", "Skateboarding",
           "Track", "Soccer", "Crew", "Skiing", "Poker", "Lacrosse", "Auto Racing", "Endurance Running", "Curling", "Cricket", "Wiffleball",
            "Wrestling", "Snowboarding", "Skateboarding", "Skateboarding", "Poker", "Mixed Martial Arts", "Ice Hockey", "Badminton", "Surfing",
             "Field Hockey", "Endurance Running", "Horse Racing", "Bowling", "Bobsleigh", "Bobsleigh", "Basketball", "Cheerleading", "Mixed Martial Arts",
              "Field Hockey", "Curling", "Skiing", "Soccer", "Curling", "Cricket", "Rugby", "Curling", "Bobsleigh", "Cheerleading", "Baseball", "Competitive Swimming",
               "Curling", "Curling", "Horse Racing", "Polo", "Tennis", "Football", "Polo", "Golf", "Volleyball", "Lacrosse", "Golf", "Tennis", "Wrestling", "Cricket",
                "Endurance Running", "Basketball", "Track", "Polo", "Field Hockey", "Wiffleball", "Rowing", "Lacrosse", "Competitive Swimming", "Endurance Running", "Snowboarding",
                 "Horse Racing", "Baseball", "Skateboarding", "Pool", "Mixed Martial Arts", "Snowboarding", "Surfing", "Polo", "Skateboarding", "Poker", "Bowling", "Crew", "Ice Hockey",
                 "Wrestling", "Cheerleading", "Polo", "Rugby", "Crew", "Weightlifting", "Skiing", "Skateboarding", "Horse Racing", "Bowling", "Weightlifting", "Rugby",
                  "Roller derby", "Badminton");
        $rand = array_rand($sports, 1);
        $itemdata['itemid'] = time() - $rand + 7 * rand(5, 115);
        $itemdata['itemname'] = $sports[$rand];
        $itemdata['price'] = rand(5, 115);
        $itemdata['expirationdate'] = time() + rand(1, 1) * 60;

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachedrawdata = $cache->get($userid . '_shopping_cart');
        $cachedrawdata['item'][ $itemdata['itemid'] ] = $itemdata;
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
}
