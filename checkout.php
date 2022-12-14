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
 * Checkout page.
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

global $USER, $PAGE, $OUTPUT, $CFG;

// Get the id of the page to be displayed.
$success = optional_param('success', null, PARAM_INT);

// As we might get a malformed URL, we have to jump through a few loops.
if (!$identifier = optional_param('identifier', null, PARAM_INT)) {
    $url = html_entity_decode($ME);
    $urlcomponents = parse_url($url);
    parse_str($urlcomponents['query'], $params);
    $identifier = $params['identifier'];
}

// Setup the page.
$PAGE->set_context(\context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/checkout.php");
$PAGE->set_title(get_string('yourcart', 'local_shopping_cart'));
$PAGE->set_heading(get_string('yourcart', 'local_shopping_cart'));

// Set the page layout.
$PAGE->set_pagelayout('base');

// Output the header.
echo $OUTPUT->header();
$userid = $USER->id;
$data = shopping_cart::local_shopping_cart_get_cache_data($userid);
$data["mail"] = $USER->email;
$data["name"] = $USER->firstname . $USER->lastname;
$data["userid"] = $USER->id;
if (isset($success)) {
    if ($success) {
        $data['success'] = 1;
        $data['finished'] = 1;

        // After successful checkout, remove all items from cart.
        $PAGE->requires->js_call_amd(
            'local_shopping_cart/cart',
            'deleteAllItems', []
        );

        // If we have a successful checkout, we show the bought items via transaction id.
        if (isset($identifier)) {
            $historylist = new shoppingcart_history_list($userid, $identifier);
            $historylist->insert_list($data);
        }

    } else {
        $data['failed'] = 1;
    }
} else {

    // Here we are before checkout.

    $historylist = new shoppingcart_history_list($userid);
    $historylist->insert_list($data);

}

$history = new shopping_cart_history();
$scdata = $history->prepare_data_from_cache($userid);

$history->store_in_schistory_cache($scdata);

$sp = new service_provider();

$data['identifier'] = $scdata['identifier'];
$data['wwwroot'] = $CFG->wwwroot;

if (empty($data['currency'])) {
    $data['currency'] = $scdata['currency'] ?? '';
}

$data['successurl'] = $sp->get_success_url('shopping_cart', (int)$scdata['identifier'])->out(false);

$data['usecreditvalue'] = $data['usecredit'] == 1 ? 'checked' : '';

echo $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
// Now output the footer.
echo $OUTPUT->footer();
