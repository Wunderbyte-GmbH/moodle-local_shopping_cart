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
 * Wunderbyte table external API
 *
 * @package local_shopping_cart
 * @category external
 * @copyright 2021 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\lib;
use local_shopping_cart\shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once('shopping_cart.php');

/**
 * Class local_shopping_cart_external
 */
class local_shopping_cart_external extends external_api {

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     * @param string $component
     * @param int $itemid
     * @return void
     */
    public static function add_item_to_cart($component, $itemid) {
        $params = external_api::validate_parameters(self::add_item_to_cart_parameters(), [
            'component' => $component,
            'itemid' => $itemid,
        ]);

        // TODO: We might not be able to add item because there can only be one.
        // We should prepare for this case.
        $cartitem = shopping_cart::get_cartitem($params['component'], $params['itemid']);

        // We need the cartitem as an array.
        $item = $cartitem->getitem();

        $shoppingcart = new shopping_cart();

        // TODO: React on full cart.
        // If the cart is full, this returns false.
        if ($shoppingcart->add_item_to_cart($item)) {
            $item['expirationdate'] = $shoppingcart->get_expirationdate();
            $item['success'] = 1;
        } else {
            $item['expirationdate'] = 0;
            $item['success'] = 0;
        }

        // Right now, we always return the item, no matter if it's added to cart or not.
        return $item;
    }

    /**
     * Describes the paramters for add_item_to_cart.
     * @return external_function_parameters
     */
    public static function add_item_to_cart_parameters() {
        return new external_function_parameters(array(
                        'component'  => new external_value(PARAM_RAW, 'component', VALUE_DEFAULT, ''),
                        'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, ''),

                )
        );
    }

    /**
     * Describes the return values for add_item_to_cart.
     * @return external_single_structure
     */
    public static function add_item_to_cart_returns() {
        return new external_single_structure(array(
                    'itemid' => new external_value(PARAM_INT, 'Item id'),
                    'itemname' => new external_value(PARAM_RAW, 'Item name'),
                    'price' => new external_value(PARAM_RAW, 'Item price'),
                    'currency' => new external_value(PARAM_RAW, 'Currency'),
                    'componentname' => new external_value(PARAM_RAW, 'Component name'),
                    'expirationdate' => new external_value(PARAM_INT, 'Expiration timestamp'),
                    'description' => new external_value(PARAM_RAW, 'Item description'),
                    'success' => new external_value(PARAM_INT, 'Successfully added'),
                )
        );
    }
    /**
     * Webservice for shopping_cart class for delete_item_from_cart.
     * @param int $itemid
     * @param string $component
     * @return void
     */
    public static function delete_item_from_cart($itemid, $component) {

        $params = external_api::validate_parameters(self::delete_item_from_cart_parameters(), [
            'itemid' => $itemid,
            'component' => $component
        ]);

        if (shopping_cart::delete_item_from_cart($params['itemid'], $params['component'])) {
            return ['success' => 1];
        }
        return ['success' => 0];
    }

    /**
     * Describes the paramters for delete_item_from_cart.
     * @return external_function_parameters
     */
    public static function delete_item_from_cart_parameters() {
        return new external_function_parameters(array(
                        'itemid'  => new external_value(PARAM_INT, 'id', VALUE_DEFAULT, '0'),
                        'component'  => new external_value(PARAM_RAW, 'component name like mod_booking', VALUE_DEFAULT, ''),
                )
        );
    }

    /**
     * Describes the return values for delete_item_from_cart.
     * @return external_multiple_structure
     */
    public static function delete_item_from_cart_returns() {
        return new external_single_structure(array(
            'success'  => new external_value(PARAM_INT, 'id'),
        ));
    }

    /**
     * Webservice for shopping_cart class to  delete all items.
     *
     *
     */
    public static function delete_all_items_from_cart() {
        shopping_cart::delete_all_items_from_cart();
    }

    /**
     * Describes the paramters for delete_item_from_cart.
     * @return external_function_parameters
     */
    public static function delete_all_items_from_cart_parameters() {
        return new external_function_parameters(array());
    }
    /**
     * Describes the return values for delete_item_from_cart.
     * @return external_multiple_structure
     */
    public static function delete_all_items_from_cart_returns() {
    }

    /**
     * Webservice for shopping_cart class to  delete all items.
     */
    public static function get_shopping_cart_items() {

        return shopping_cart::local_shopping_cart_get_cache_data();
    }

    /**
     * Describes the paramters for delete_item_from_cart.
     * @return external_function_parameters
     */
    public static function get_shopping_cart_items_parameters() {
        return new external_function_parameters(array());
    }
    /**
     * Describes the return values for delete_item_from_cart.
     * @return external_single_structure
     */
    public static function get_shopping_cart_items_returns() {
        return new external_single_structure(
            array(
                'count' => new external_value(PARAM_INT, 'Number of items'),
                'price' => new external_value(PARAM_RAW, 'Total price'),
                'expirationdate' => new external_value(PARAM_INT, 'Expiration timestamp of cart'),
                'maxitems' => new external_value(PARAM_INT, 'Currency'),
                'items' => new external_multiple_structure (
                        new external_single_structure(
                            array(
                            'itemid' => new external_value(PARAM_RAW, 'Item id'),
                            'itemname' => new external_value(PARAM_RAW, 'Item name'),
                            'price' => new external_value(PARAM_RAW, 'Price of item'),
                            'currency' => new external_value(PARAM_RAW, 'Currency'),
                            'componentname' => new external_value(PARAM_RAW, 'Component name'),
                            'description' => new external_value(PARAM_RAW, 'Item description'),
                            )
                        )
                )
            )
        );
    }
}
