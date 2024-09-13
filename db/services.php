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

$services = [
        'Wunderbyte shopping_cart external' => [
                'functions' => [
                        'local_shopping_cart_add_item',
                        'local_shopping_cart_delete_item',
                        'local_shopping_cart_delete_all_items_from_cart',
                        'local_shopping_cart_get_shopping_cart_items',
                        'local_shopping_cart_credit_paid_back',
                        'local_shopping_cart_cancel_purchase',
                        'local_shopping_cart_get_price',
                        'local_shopping_cart_confirm_cash_payment',
                        'local_shopping_cart_quota_consumed',
                ],
                'restrictedusers' => 1,
                'shortname' => 'local_shopping_cart_external',
                'enabled' => 1,
        ],
];

$functions = [
        'local_shopping_cart_add_item' => [
                'classname' => 'local_shopping_cart\external\add_item_to_cart',
                'description' => 'Add an Item to the shopping cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_delete_item' => [
                'classname' => 'local_shopping_cart\external\delete_item_from_cart',
                'description' => 'Delete Item from cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_delete_all_items_from_cart' => [
                'classname' => 'local_shopping_cart\external\delete_all_items_from_cart',
                'description' => 'Delete All Items from cart',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_get_shopping_cart_items' => [
                'classname' => 'local_shopping_cart\external\get_shopping_cart_items',
                'description' => 'Get shopping cart items',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_confirm_cash_payment' => [
                'classname' => 'local_shopping_cart\external\confirm_cash_payment',
                'description' => 'Confirm cash payment by cashier',
                'type' => 'write',
                'capabilities' => 'local/shopping_cart:cashier',
                'ajax' => 1,
        ],
        'local_shopping_cart_cancel_purchase' => [
                'classname' => 'local_shopping_cart\external\cancel_purchase',
                'description' => 'Cancel purchase',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_get_price' => [
                'classname' => 'local_shopping_cart\external\get_price',
                'description' => 'Get price',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_credit_paid_back' => [
                'classname' => 'local_shopping_cart\external\credit_paid_back',
                'description' => 'Register paid back credit',
                'type' => 'write',
                'capabilities' => 'local/shopping_cart:cashier',
                'ajax' => 1,
        ],
        'local_shopping_cart_get_history_items' => [
                'classname' => 'local_shopping_cart\external\get_history_items',
                'description' => 'Get History items',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_reload_history' => [
                'classname' => 'local_shopping_cart\external\reload_history',
                'description' => 'Reload complete shopping cart history',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_quota_consumed' => [
                'classname' => 'local_shopping_cart\external\get_quota_consumed',
                'description' => 'Return the consumed quota from a given item',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_search_users' => [
                'classname' => 'local_shopping_cart\external\search_users',
                'description' => 'Search a list of all users',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_mark_item_for_rebooking' => [
                'classname' => 'local_shopping_cart\external\mark_for_rebooking',
                'description' => 'Marks history item for rebooking',
                'type' => 'write',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_get_history_item' => [
                'classname' => 'local_shopping_cart\external\get_history_item',
                'description' => 'Gets the latest history item',
                'type' => 'read',
                'capabilities' => '',
                'ajax' => 1,
        ],
        'local_shopping_cart_verify_purchase' => [
                'classname' => 'local_shopping_cart\external\verify_purchase',
                'description' => 'Verify a puchase',
                'type' => 'read',
                'capabilities' => '',
        ],
];
