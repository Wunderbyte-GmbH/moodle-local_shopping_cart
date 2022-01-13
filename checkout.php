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
use local_shopping_cart\shopping_cart;

global $USER;
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

// Get the id of the page to be displayed.
$id = optional_param('id', 0, PARAM_INT);

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/checkout.php");
$PAGE->set_title("Ihr Warenkorb");
$PAGE->set_heading("<i class='fa fa-3x fa-shopping-cart' aria-hidden='true'></i>Ihr Warenkorb ");

// Set the page layout.


require_login();

$PAGE->set_pagelayout('standard');


shopping_cart::add_random_item();
// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;
$cache = \cache::make('local_shopping_cart', 'cacheshopping');
$cachedrawdata = $cache->get($userid . '_shopping_cart');
$data['item'] = array_values($cachedrawdata['item']);


echo $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
// Now output the footer.
echo $OUTPUT->footer();
