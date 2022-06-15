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

defined('MOODLE_INTERNAL') || die;

use local_shopping_cart\shopping_cart;

// Define constants.

// First entry in shopping cart history. This means that payment was initiated, but not successfully completed.
define('PAYMENT_PENDING', 0);
// Pending will be switched to aborted, once we can be sure that the payment process will not be continued.
define('PAYMENT_ABORTED', 1);
// Payment was successful.
define('PAYMENT_SUCCESS', 2);
// Canceled payments mean that items - which have already been paid for - are canceled after successful checkout.
define('PAYMENT_CANCELED', 3);

// Payment methods.
define('PAYMENT_METHOD_ONLINE', 0); // Payment via payment gateway (usually with card).
define('PAYMENT_METHOD_CASHIER', 1); // Payment at cachier's office (usually cash, but card would also be possible).
define('PAYMENT_METHOD_CREDITS', 2); // Payment via credits.

/**
 * Adds module specific settings to the settings block
 *
 * @param navigation_node $modnode The node to add module settings to
 * @return void
 */
function local_shopping_cart_extend_navigation(navigation_node $navigation) {
    $context = context_system::instance();
    if (has_capability('local/shopping_cart:cachier', $context)) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)) {
            $nodehome = $navigation;
        }
        $pluginname = get_string('pluginname', 'local_shopping_cart');
        $link = new moodle_url('/local/shopping_cart/cashier.php', array());
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
    $cache = shopping_cart::local_shopping_cart_get_cache_data($USER->id);
    $output .= $renderer->render_from_template('local_shopping_cart/shopping_cart_popover', $cache);
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
