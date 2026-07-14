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
 * Builds the checkout page data for the still-shopping (cart) case.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process;

use local_shopping_cart\addresses;
use local_shopping_cart\local\cart_coupon_manager;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\utils\wb_payment;

/**
 * Builds the checkout page data for the still-shopping (cart) case.
 *
 * This is the exact data preparation checkout.php performs for a cart that is
 * about to be paid (cart store, coupon input state, selected addresses and the
 * checkout manager overview), factored out so it can also feed the embedded
 * checkout view. checkout.php keeps its own inline copy for now; both can be
 * pointed here once the embedded flow has been verified end to end against a
 * live payment provider.
 */
class checkout_page_data {
    /**
     * Assembles the checkout template data for the current cart of a user.
     *
     * Mirrors the "still shopping" branch of checkout.php (its receipt/success
     * branch is page-specific and stays there). No output and no page setup:
     * callers own the layout, JS requirements and rendering.
     *
     * @param int $userid
     * @return array Template data for local_shopping_cart/checkout.
     */
    public static function build_cart_checkout(int $userid): array {
        $cartstore = cartstore::instance($userid);
        $data = $cartstore->get_localized_data();

        // Reset the cached code before expanding the checkout data.
        shopping_cart::check_for_ongoing_payment($userid);
        $cartstore->get_expanded_checkout_data($data);

        // Coupon input state. Only offer the field when coupons are enabled and a
        // PRO licence is active, and never apply/remove one here (this runs on
        // plain reloads too). Only flag an already-applied coupon.
        $data['couponenabled'] = get_config('local_shopping_cart', 'couponenabled')
            && wb_payment::pro_version_is_activated();
        if ($data['couponenabled']) {
            $couponmanager = new cart_coupon_manager($cartstore);
            if ($couponmanager->coupon_applied()) {
                $data['couponenabled'] = false;
                $data['couponapplied'] = true;
                $data['couponmessage'] = get_string(
                    'couponappliedsuccessfully',
                    'local_shopping_cart',
                    $couponmanager->get_applied_coupon()
                );
            }
        }

        // Selected addresses.
        $requiredaddresskeys = addresses::get_required_address_keys();
        $requriedaddresses = addresses::get_required_address_data();
        $countries = get_string_manager()->get_list_of_countries();
        $hasallrequiredaddresses = !empty($requiredaddresskeys);
        $selectedaddresses = [];
        foreach ($requiredaddresskeys as $addresstype) {
            $addressid = $data["address_" . $addresstype] ?? "";
            if ($addressid && trim((string) $addressid) !== '' && is_numeric($addressid)) {
                $address = address_operations::get_specific_user_address($addressid);
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

        // Checkout manager overview (stepper, VAT, credits, addresses).
        $checkoutmanager = new checkout_manager($data);
        $data = array_merge($data, $checkoutmanager->render_overview());
        $data['area'] = 'main';

        return $data;
    }
}
