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
                        'local_shopping_cart_credit_paid_back',
                        'local_shopping_cart_cancel_purchase',
                        'local_shopping_cart_get_price',
                        'local_shopping_cart_credit_paid_back',
                        'local_shopping_cart_confirm_cash_payment'
                ),
                'restrictedusers' => 1,
                'shortname' => 'local_shopping_cart_external',
                'enabled' => 1
        )
);

$functions = array(
        'local_shopping_cart_add_item' => array(
                'classname' => 'local_shopping_cart\external\add_item_to_cart',
                'classpath' => '',
                'description' => 'Add an Item to the shopping cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_delete_item' => array(
                'classname' => 'local_shopping_cart\external\delete_item_from_cart',
                'classpath' => '',
                'description' => 'Delete Item from cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_delete_all_items_from_cart' => array(
                'classname' => 'local_shopping_cart\external\delete_all_items_from_cart',
                'classpath' => '',
                'description' => 'Delete All Items from cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_get_shopping_cart_items' => array(
                'classname' => 'local_shopping_cart\external\get_shopping_cart_items',
                'classpath' => '',
                'description' => 'Get shopping cart items',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_confirm_cash_payment' => array(
                'classname' => 'local_shopping_cart\external\confirm_cash_payment',
                'classpath' => '',
                'description' => 'Confirm cash payment by cashier',
                'type' => 'write',
                'capabilities' => 'local/shopping_cart:cashier',
                'ajax' => 1
        ),
        'local_shopping_cart_cancel_purchase' => array(
                'classname' => 'local_shopping_cart\external\cancel_purchase',
                'classpath' => '',
                'description' => 'Cancel purchase',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_get_price' => array(
                'classname' => 'local_shopping_cart\external\get_price',
                'classpath' => '',
                'description' => 'Get price',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_credit_paid_back' => array(
                'classname' => 'local_shopping_cart\external\credit_paid_back',
                'classpath' => '',
                'description' => 'Register paid back credit',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1
        ),
        'local_shopping_cart_get_history_items' => array(
                'classname' => 'local_shopping_cart\external\get_history_items',
                'classpath' => '',
                'description' => 'Get History items',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1
        ),
);

