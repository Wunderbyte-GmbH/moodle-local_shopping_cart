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

use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\payment\service_provider;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

require_login();

global $USER;
// Get the id of the page to be displayed.
$success = optional_param('success', null, PARAM_INT);
$identifier = optional_param('identifier', null, PARAM_INT);

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/checkout.php");
$PAGE->set_title("Ihr Warenkorb");
$PAGE->set_heading("<i class='fa fa-3x fa-shopping-cart' aria-hidden='true'></i>Ihr Warenkorb ");

// Set the page layout.

$PAGE->set_pagelayout('standard');


// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;
$data = shopping_cart::local_shopping_cart_get_cache_data($userid);
$data["mail"] = $USER->email;
$data["name"] = $USER->firstname . $USER->lastname;
if (isset($success)) {
    if ($success) {
        $data['success'] = 1;

        // If we have a successful checkout, we show the bought items via transaction id.
        if (isset($identifier)) {
            $historylist = new shoppingcart_history_list($userid, $identifier);
            $data['histoyitems'] = $historylist->return_list();
        }

    } else {
        $data['failed'] = 1;
    }
}

$schistory = new shopping_cart_history();
$scdata = $schistory->prepare_data_from_cache($userid);

$schistory->store_in_schistory_cache($scdata);

$sp = new service_provider();

$data['identifier'] = $scdata['identifier'];
$data['currency'] = $scdata['currency'] ?? '';
$data['successurl'] = $sp->get_success_url('shopping_cart', (int)$scdata['identifier']);

echo $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
// Now output the footer.
echo $OUTPUT->footer();
