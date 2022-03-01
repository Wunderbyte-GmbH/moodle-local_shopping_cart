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
 * Plugin strings are defined here.
 *
 * @package     local_shopping_cart
 * @category    string
 * @copyright   2021 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Shopping Cart';
$string['sendpaymentbutton'] = 'Checkout';

$string['addtocart'] = 'Add to cart';

// Settings.
$string['maxitems'] = 'Max. items in the shopping cart';
$string['maxitems:description'] = 'Set max. items for the user shopping cart';
$string['expirationtime'] = 'Set expiration time in minutes';
$string['expirationtime:description'] = 'How long should the item be in the cart';
$string['addon'] = 'Set addon time';
$string['addon:description'] = 'Addition to the expiration time after checkout process is initiated';
$string['additonalcashiersection'] = 'Add text for cashier section';
$string['additonalcashiersection:description'] = 'Add HTML shortcodes or items to buy for the cashier shopping tab';

// Capabilities.
$string['shopping_cart:canbuy'] = 'Can buy';
$string['shopping_cart:history'] = 'See History';
$string['shopping_cart:cachier'] = 'Is a cachier';

// File: lib.php.
$string['foo'] = 'foo';

// Cache.
$string['cachedef_cashier'] = 'Cashier cache';
$string['cachedef_cacheshopping'] = 'Shopping cache';
