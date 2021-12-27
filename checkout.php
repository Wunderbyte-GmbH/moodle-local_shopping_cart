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
 * Pages main view page.
 *
 * @package         local_shopping_cart
 * @author          Thomas Winkler
 * @copyright       2021 Wunderbyte GmbH
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use cache_helper;
use local_shopping_cart;
global $USER;
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

// Get the id of the page to be displayed.
$id = optional_param('id', 0, PARAM_INT);

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/checkout.php");
$PAGE->set_title("Ihr Warenkorb");
$PAGE->set_heading("<i class='fa fa-3x fa-shopping-cart' aria-hidden='true'></i>Ihr Warenkorb ");
$PAGE->requires->js_call_amd('local_shopping_cart/cart','init');

// Set the page layout.


require_login();

$PAGE->set_pagelayout('standard');

// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;
$cache = \cache::make('local_shopping_cart', 'cacheshopping');

$shopping1['id'] = "1";
$shopping1['modul'] = "booking";
$shopping1['name'] = "Basketball";
$shopping1['price'] = "515.00";
$shopping1['expirationdate'] = time() + 30 * 60;
local_shopping_cart\shopping_cart::add_item_to_cart($shopping1);
for ($i = 0; $i <= 6; $i++) {
    local_shopping_cart\shopping_cart::add_random_item();
}
$shopping2['id'] = "3";
$shopping2['modul'] = "booking";
$shopping2['name'] = "Volleyball";
$shopping2['price'] = "25.00";
$shopping2['expirationdate'] = time() + 15 * 60;
local_shopping_cart\shopping_cart::add_item_to_cart($shopping2);
local_shopping_cart\shopping_cart::delete_item_from_cart(3);
$shopping3['id'] = "2";
$shopping3['modul'] = "booking";
$shopping3['name'] = "Basketball";
$shopping3['price'] = "75.00";
$shopping3['expirationdate'] = time() + 7 * 60;
local_shopping_cart\shopping_cart::add_item_to_cart($shopping3);
$shopping4['id'] = "11";
$shopping4['modul'] = "booking";
$shopping4['name'] = "Volleyball";
$shopping4['price'] = "25.00";
$shopping4['expirationdate'] = time() + 7 * 15;
$shoppings['item'][$shopping4['id']] = $shopping4;
local_shopping_cart\shopping_cart::add_item_to_cart($shopping4);
$cachedrawdata = $cache->get($userid . '_shopping_cart');
$data['item'] = array_values($cachedrawdata['item']);


echo $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
// Now output the footer.
echo $OUTPUT->footer();
