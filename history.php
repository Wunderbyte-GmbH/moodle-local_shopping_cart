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

use local_shopping_cart\shopping_cart;
use core_user_external;
use local_shopping_cart\local\cartstore;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

require_login();

global $USER;
// Get the id of the page to be displayed.
$success = optional_param('success', null, PARAM_INT);

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/checkout.php");
$PAGE->set_title(get_string('yourcart', 'local_shopping_cart'));
$PAGE->set_heading(get_string('yourcart', 'local_shopping_cart'));

// Set the page layout.

$PAGE->set_pagelayout('standard');


// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;

$cartstore = cartstore::instance($userid);
$data = $cartstore->get_data();
$data["mail"] = $USER->email;
$data["name"] = $USER->firstname . $USER->lastname;
if (isset($success)) {
    if ($success) {
        $data['success'] = 1;
    } else {
        $data['failed'] = 1;
    }
}
$data['additonalcashiersection'] = get_config('local_shopping_cart', 'additonalcashiersection');

$test = get_users(true, '', true, [], '', '', '', '', $recordsperpage = 21);

// Convert numbers to strings with 2 fixed decimals right before rendering.
shopping_cart::convert_prices_to_number_format($data);

echo $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
// Now output the footer.
echo $OUTPUT->footer();
