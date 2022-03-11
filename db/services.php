<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Webservice to reload table.
 *
 * @package     local_shopping_cart
 * @category    upgrade
 * @copyright   2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$services = array(
        'Wunderbyte shopping_cart external' => array(
                'functions' => array (
                        'local_shopping_cart_add_item',
                        'local_shopping_cart_delete_item',
                        'local_shopping_cart_delete_all_items_from_cart',
                        'local_shopping_cart_get_shopping_cart_items',
                        'local_shopping_cart_checkout_for_customer',
                        'local_shopping_cart_confirm_cash_payment'
                ),
                'restrictedusers' => 1,
                'shortname' => 'local_shopping_cart_external',
                'enabled' => 1
        )
);

$functions = array(
        'local_shopping_cart_add_item' => array(
                'classname' => 'local_shopping_cart_external',
                'methodname' => 'add_item_to_cart',
                'classpath' => 'local/shopping_cart/classes/externallib.php',
                'description' => 'Add an Item to the shopping cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_delete_item' => array(
                'classname' => 'local_shopping_cart_external',
                'methodname' => 'delete_item_from_cart',
                'classpath' => 'local/shopping_cart/classes/externallib.php',
                'description' => 'Delete Item from cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_delete_all_items_from_cart' => array(
                'classname' => 'local_shopping_cart_external',
                'methodname' => 'delete_all_items_from_cart',
                'classpath' => 'local/shopping_cart/classes/externallib.php',
                'description' => 'Delete All Items from cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_get_shopping_cart_items' => array(
                'classname' => 'local_shopping_cart_external',
                'methodname' => 'get_shopping_cart_items',
                'classpath' => 'local/shopping_cart/classes/externallib.php',
                'description' => 'Get shopping cart items',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1
        ),
/*         'local_shopping_cart_checkout_for_customer' => array(
                'classname' => 'local_shopping_cart_external',
                'methodname' => 'get_shopping_cart_items',
                'classpath' => 'local/shopping_cart/classes/externallib.php',
                'description' => 'Get shopping cart items',
                'type' => 'read',
                'capabilities' => 'local/shopping_cart:cachier',
                'ajax' => 1
        ), */
        'local_shopping_cart_confirm_cash_payment' => array(
                'classname' => 'local_shopping_cart_external',
                'methodname' => 'confirm_cash_payment',
                'classpath' => 'local/shopping_cart/classes/externallib.php',
                'description' => 'Confirm cash payment by cashier',
                'type' => 'write',
                'capabilities' => 'local/shopping_cart:cachier',
                'ajax' => 1
        )

);

