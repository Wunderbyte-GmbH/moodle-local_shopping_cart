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
 * @copyright       2021 Wunderbyte GmbH
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use local_shopping_cart\addresses;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

require_login();

$addressesrequired = addresses::get_required_address_keys();
if (empty($addressesrequired)) {
    redirect($CFG->wwwroot . '/local/shopping_cart/checkout.php', '', 0);
    return;
}

global $USER, $PAGE, $OUTPUT, $CFG;

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/address.php");
$PAGE->set_title(get_string('addresses:pagetitle', 'local_shopping_cart'));
$PAGE->set_heading(get_string('addresses:heading', 'local_shopping_cart'));

// Set the page layout.
$PAGE->set_pagelayout('standard');

$data = addresses::get_template_render_data();
// Handle form submit, when user selected new address(es).
if (isset($_POST['submit'])) {
    require_sesskey();
    $selectedaddressdbids = [];
    // Are all required addresses present?
    $alladdressesset = true;
    foreach ($data['required_addresses_keys'] as $addreskey) {
        $addressdbid = $_POST['selectedaddress_' . $addreskey];
        if (isset($addressdbid) && !empty(trim($addressdbid)) && is_numeric($addressdbid)) {
            $selectedaddressdbids[$addreskey] = $addressdbid;
        } else {
            $alladdressesset = false;
        }
    }

    if (!$alladdressesset) {
        $data['show_error'] = get_string('addresses:selectionrequired', 'local_shopping_cart');
    } else {
        $userid = $USER->id;

        $cartstore = cartstore::instance($userid);
        $cartstore->local_shopping_cart_save_address_in_cache($selectedaddressdbids);

        redirect($CFG->wwwroot . '/local/shopping_cart/checkout.php', '', 0);
    }
}

// Output the header.
echo $OUTPUT->header();

echo '<form method="post"><input type="hidden" name="sesskey" value="' . sesskey() . '"><div id="addressestemplatespace">';

echo $OUTPUT->render_from_template('local_shopping_cart/address', $data);

echo '</div></form>';
// Now output the footer.
echo $OUTPUT->footer();
