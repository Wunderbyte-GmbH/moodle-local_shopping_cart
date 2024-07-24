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
 * Moodle hooks for local_shopping_cart
 * @package    local_shopping_cart
 * @copyright  2021 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;

// Define constants.

// First entry in shopping cart history. This means that payment was initiated, but not successfully completed.
define('LOCAL_SHOPPING_CART_PAYMENT_PENDING', 0);
// Pending will be switched to aborted, once we can be sure that the payment process will not be continued.
define('LOCAL_SHOPPING_CART_PAYMENT_ABORTED', 1);
// Payment was successful.
define('LOCAL_SHOPPING_CART_PAYMENT_SUCCESS', 2);
// Canceled payments mean that items - which have already been paid for - are canceled after successful checkout.
define('LOCAL_SHOPPING_CART_PAYMENT_CANCELED', 3);

// Payment methods.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE', 0);
    // Payment via payment gateway (which is usually connected with a credit card).
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER', 1); // Payment at cashier's office (unknown if cash, debit or credit card).
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS', 2); // Payment via credits.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH', 3); // Payment at cashier's office using cash.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_DEBITCARD', 4); // Payment at cashier's office using a debit card.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CREDITCARD', 5); // Payment at cashier's office using a credit card.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH', 6); // Credits removed and paid back to user by cash.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL', 7);
    // If someone paid, but there was an error, the cashier can re-book someone manually.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER', 8);
    // Credits removed and paid back to user by (bank) transfer.
define('LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION', 9); // Credits removed and paid back to user by (bank) transfer.

// Cart success params.
define('LOCAL_SHOPPING_CART_CARTPARAM_ERROR', -1); // General error.
define('LOCAL_SHOPPING_CART_CARTPARAM_ALREADYINCART', 0); // Already in cart.
define('LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS', 1); // Item added to cart successfully.
define('LOCAL_SHOPPING_CART_CARTPARAM_CARTISFULL', 2); // Item could not be added because cart is full.
define('LOCAL_SHOPPING_CART_CARTPARAM_COSTCENTER', 3); // Item could not be added because of different cost center.
define('LOCAL_SHOPPING_CART_CARTPARAM_FULLYBOOKED', 4); // Item could not be added because it's already fully booked.
define('LOCAL_SHOPPING_CART_CARTPARAM_ALREADYBOOKED', 5); // Item could not be added because it was already booked before.

// Price modifiers.
define('LOCAL_SHOPPING_CART_PRICEMOD_INSTALLMENTS', 10); // Apply Installments.
define('LOCAL_SHOPPING_CART_PRICEMOD_STANDARD', 30); // Apply Standard calculation.
define('LOCAL_SHOPPING_CART_PRICEMOD_TAXES', 50); // Apply Taxes on cart.
define('LOCAL_SHOPPING_CART_PRICEMOD_CREDITS', 100); // Apply Credits on cart.
define('LOCAL_SHOPPING_CART_PRICEMOD_TERMSANDCONDITIONS', 150); // Applies Terms and conditions, normally for checkout.
define('LOCAL_SHOPPING_CART_PRICEMOD_CHECKOUT', 200); // Checkout is a price modifier, but only applied manually.

/**
 * Adds module specific settings to the settings block
 *
 * @param navigation_node $navigation The node to add module settings to
 * @return void
 */
function local_shopping_cart_extend_navigation(navigation_node $navigation) {
    $context = context_system::instance();
    if (has_capability('local/shopping_cart:cashier', $context)) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)) {
            $nodehome = $navigation;
        }
        $pluginname = get_string('pluginname', 'local_shopping_cart');
        $link = new moodle_url('/local/shopping_cart/cashier.php', []);
        $icon = new pix_icon('i/shopping_cart', $pluginname, 'local_shopping_cart');
        $nodecreatecourse = $nodehome->add($pluginname, $link, navigation_node::NODETYPE_LEAF,
            $pluginname, 'shopping_cart_cashier', $icon);
        $nodecreatecourse->showinflatnavigation = true;
    }
}


/**
 * Renders the popup.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_shopping_cart_render_navbar_output(\renderer_base $renderer) {
    global $USER, $CFG;

    // Early bail out conditions.
    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $output = '';

    $cartstore = cartstore::instance($USER->id);
    $data = $cartstore->get_data();

    $dueinstallments = $cartstore->get_due_installments();

    if (!empty($dueinstallments)) {
        foreach ($dueinstallments as $dueinstallement) {

            if ($dueinstallement['installment'] > time()) {
                $message = get_string('installmentpaymentisdue', 'local_shopping_cart', $dueinstallement);
                $type = \core\notification::INFO;
            } else {
                $message = get_string('installmentpaymentwasdue', 'local_shopping_cart', $dueinstallement);
                $type = \core\notification::ERROR;
            }
            \core\notification::add($message, $type);
        }
    }

    // If we have the capability, we show a link to cashier's desk.
    if (has_capability('local/shopping_cart:cashier', context_system::instance())) {
        $data['showcashier'] = true;
        $data['cashierurl'] = new moodle_url('/local/shopping_cart/cashier.php');
    }

    // Convert numbers to strings with 2 fixed decimals right before rendering.
    shopping_cart::convert_prices_to_number_format($data);

    $output .= $renderer->render_from_template('local_shopping_cart/shopping_cart_popover', $data);
    return $output;
}

/**
 * Get icon mapping for font-awesome.
 *
 * @return  array
 */
function local_shopping_cart_get_fontawesome_icon_map() {
    return [
        'local_shopping_cart:i/shopping_cart' => 'fa-shopping-cart',
        'local_shopping_cart:t/selected' => 'fa-check',
        'local_shopping_cart:t/subscribed' => 'fa-envelope-o',
        'local_shopping_cart:t/unsubscribed' => 'fa-envelope-open-o',
        'local_shopping_cart:t/star' => 'fa-star',
    ];
}

/**
 *  Callback checking permissions and preparing the file for serving plugin files, see File API.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_shopping_cart_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Check the contextlevel is as expected - if your plugin is a block.
    // We need context course if wee like to acces template files.
    if (!in_array($context->contextlevel, [CONTEXT_SYSTEM])) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'local_shopping_cart_receiptimage') {
        return false;
    }
    // Make sure the user is logged in and has access to the module.

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        // Var $args is empty => the path is '/'.
        $filepath = '/';
    } else {
        // Var $args contains elements of the filepath.
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_shopping_cart', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // Send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    send_stored_file($file, 0, 0, true, $options);
}

/**
 * Helper function to get a list of all shoppingcart events to be shown in a select (dropdown).
 * @return array a list containing the full paths of all booking events as key
 *               and the event names as values
 */
function get_list_of_shoppingcart_events() {
    $eventinformation = [];
    $events = core_component::get_component_classes_in_namespace('local_shopping_cart', 'event');
    foreach (array_keys($events) as $event) {
        // We need to filter all classes that extend event base, or the base class itself.
        if (is_a($event, \core\event\base::class, true)) {
            $parts = explode('\\', $event);
            $eventwithnamespace = "\\{$event}";
            $eventinformation[$eventwithnamespace] = $eventwithnamespace::get_name() .
                " (" . array_pop($parts) . ")";
        }
    }
    return $eventinformation;
}
