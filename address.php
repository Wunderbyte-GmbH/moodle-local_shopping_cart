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
 * Address selection page.
 *
 * @package         local_shopping_cart
 * @author          Maurice Whlk
 * @copyright       2021 Wunderbyte GmbH
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\payment\service_provider;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

require_login();

$addressesrequired = get_config('local_shopping_cart', 'addresses_required');
if (empty($addressesrequired)) {
    redirect($CFG->wwwroot . '/local/shopping_cart/checkout.php');
    return;
}

global $USER, $PAGE, $OUTPUT, $CFG;

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/address.php");
$PAGE->set_title(get_string('addresses:pagetitle', 'local_shopping_cart'));
$PAGE->set_heading(get_string('addresses:heading', 'local_shopping_cart'));

// Set the page layout.
$PAGE->set_pagelayout('base');

// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;
$data = shopping_cart::local_shopping_cart_get_cache_data($userid);
$data["usermail"] = $USER->email;
$data["username"] = $USER->firstname . $USER->lastname;
$data["userid"] = $USER->id;

$data['saved_addresses'] = [];

// insert localized string for required address types
$requiredaddresseslocalized = [];
foreach (explode(',', $addressesrequired) as $addresstype) {
    $requiredaddresseslocalized[] = [
            "addresskey" => $addresstype,
            "requiredaddress" => get_string('addresses:' . $addresstype, 'local_shopping_cart')
    ];
}
$data['required_addresses'] = $requiredaddresseslocalized;

//var_dump($data);

echo $OUTPUT->render_from_template('local_shopping_cart/address', $data);
// Now output the footer.
echo $OUTPUT->footer();
