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


use local_shopping_cart\form\dynamicvatnrchecker;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\create_invoice;
use local_shopping_cart\local\pricemodifier\modifiers\checkout;
use local_shopping_cart\addresses;
use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

require_login();

global $USER, $PAGE, $OUTPUT, $CFG, $ME;

// Get the id of the page to be displayed.
$success = optional_param('success', null, PARAM_INT);
$jsononly = optional_param('jsononly', null, PARAM_INT);

// As we might get a malformed URL, we have to jump through a few loops.
if (!$identifier = optional_param('identifier', null, PARAM_INT)) {
    if ($CFG->version >= 2023042400) {
        // Moodle 4.2 needs second param.
        $url = html_entity_decode($ME, ENT_QUOTES);
    } else {
        // Moodle 4.1 and older.
        $url = html_entity_decode($ME, ENT_COMPAT);
    }
    $urlcomponents = parse_url($url);
    if (isset($urlcomponents['query'])) {
        parse_str($urlcomponents['query'], $params);
    }
    $identifier = $params['identifier'] ?? null;
}

if (empty($jsononly)) {
    // Setup the page.
    $PAGE->set_context(\context_system::instance());
    $PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/checkout.php");
    $PAGE->set_title(get_string('yourcart', 'local_shopping_cart'));
    $PAGE->set_heading(get_string('yourcart', 'local_shopping_cart'));
    // Set the page layout.
    $PAGE->set_pagelayout('base');
}

$userid = $USER->id;

// If we have a successful checkout, we show the bought items via transaction id.
if (isset($identifier)) {
    $data = [];
    $historylist = new shoppingcart_history_list($userid, $identifier, true);

    // Prevent users to see the invoices of others.
    if (
        $historylist->return_userid() != $userid
    ) {
        require_capability('local/shopping_cart:cashier', context_system::instance());
    }

    $success = shopping_cart_history::has_successful_checkout($identifier);
}

if (isset($success) && isset($historylist)) {
    if (!empty($success)) {
        $historylist->insert_list($data);

        $data['success'] = 1;
        $data['finished'] = 1;

        // After successful checkout, remove all items from cart.
        $PAGE->requires->js_call_amd(
            'local_shopping_cart/cart',
            'deleteAllItems',
            []
        );

        // If we just want to show the success json, we return it.
        if (!empty($jsononly)) {
            echo json_encode(['status' => 0]);
            die;
        }

        if (
            get_config('local_shopping_cart', 'invoicingplatform') === 'saveinvoicenumber'
            && !empty(get_config('local_shopping_cart', 'startinvoicenumber'))
        ) {
            try {
                create_invoice::create_invoice_files_from_identifier($identifier, $userid);
            } catch (Exception $e) {
                if ($CFG->debug == DEBUG_DEVELOPER) {
                    throw $e;
                }
            }
        }
    } else {
        $data['failed'] = 1;
        $data['finished'] = 1;
    }
} else {
    $cartstore = cartstore::instance($userid);
    $data = $cartstore->get_localized_data();

    $data["mail"] = $USER->email;
    $data["name"] = $USER->firstname . $USER->lastname;
    $data["userid"] = $USER->id;

    // Makes sure no open purchase stays active.
    shopping_cart::check_for_ongoing_payment($userid);

    // This creates just our list of boght items.
    $historylist = new shoppingcart_history_list($userid);
    $historylist->insert_list($data);

    // Here we are before checkout.
    $expirationtime = shopping_cart::get_expirationtime();

    // Add or reschedule all delete_item_tasks for all the items in the cart.
    shopping_cart::add_or_reschedule_addhoc_tasks($expirationtime, $userid);

    // The modifier "checkout" prepares our data for the checkout page.
    // During this process,the new identifier is created, if necessary.
    checkout::prepare_checkout($data);

    // We add the vatnrcheckerform here, if necessary.
    if (
        get_config('local_shopping_cart', 'showvatnrchecker')
        && !empty(get_config('local_shopping_cart', 'owncountrycode')
        && !empty(get_config('local_shopping_cart', 'ownvatnrnumber')))
    ) {
        $vatnrchecker = new dynamicvatnrchecker();
        $vatnrchecker->set_data_for_dynamic_submission();
        $data['showvatnrchecker'] = $vatnrchecker->render();
    }
    $data['usecreditvalue'] = $data['usecredit'] == 1 ? 'checked' : '';
}

// Address handling.
$requiredaddresskeys = addresses::get_required_address_keys();
$requriedaddresses = addresses::get_required_address_data();
$countries = get_string_manager()->get_list_of_countries();
$hasallrequiredaddresses = !empty($requiredaddresskeys);
$selectedaddresses = [];
foreach ($requiredaddresskeys as $addresstype) {
    if (isset($data["address_" . $addresstype])) {
        $addressid = $data["address_" . $addresstype];
    } else {
        $addressid = "";
    }
    if ($addressid && !empty(trim($addressid)) && is_numeric($addressid)) {
        $address = addresses::get_address_for_user($userid, $addressid);
        if ($address !== false) {
            $address->label = ucfirst($requriedaddresses[$addresstype]['addresslabel']);
            $address->country = $countries[$address->state];
            $selectedaddresses[] = get_object_vars($address);
        } else {
            // There was an error loading the address from db.
            $hasallrequiredaddresses = false;
        }
    } else {
        $hasallrequiredaddresses = false;
    }
}
if ($hasallrequiredaddresses) {
    $data['selected_addresses'] = $selectedaddresses;
    $data['show_selected_addresses'] = true;
}
$data['address_selection_required'] = !empty($requiredaddresskeys) && !$hasallrequiredaddresses;
if (empty($jsononly)) {
    // Convert numbers to strings with 2 fixed decimals right before rendering.
    shopping_cart::convert_prices_to_number_format($data);

    // Output the header.
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_shopping_cart/checkout', $data);
    echo $OUTPUT->footer();
}
