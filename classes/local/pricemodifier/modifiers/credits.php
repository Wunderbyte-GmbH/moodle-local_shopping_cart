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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\pricemodifier\modifiers;

use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use local_shopping_cart\shopping_cart_rebookingcredit;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class credits extends modifier_base {

    /** @var int */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_CREDITS;

    public static function apply(array &$data): array {
        global $USER;

        $userid = $data['userid'];
        $usecredit = shopping_cart_credits::use_credit_fallback(null, $userid);
        if (!isset($data['items'])) {
            list($data['credit'], $data['currency']) = shopping_cart_credits::get_balance($data['userid']);
            $data['items'] = [];
            $data['remainingcredit'] = $data['credit'];
            $items = array_map(function($item) use ($USER, $userid) {
                $item['userid'] = $userid != $USER->id ? -1 : 0;
                return $item;
            }, $data['items']);
            // $data['items'] = shopping_cart::update_item_price_data(array_values($items));
            // $data['price'] = shopping_cart::calculate_total_price($data["items"]);
        } else {
            $count = isset($data['items']) ? count($data['items']) : 0;
            $data['count'] = $count;

            $data['currency'] = $data['currency'] ?? null;
            $data['credit'] = $data['credit'] ?? null;
            $data['remainingcredit'] = $data['credit'];
        }
        $data['price'] = shopping_cart::calculate_total_price($data["items"]);

        list($balance, $currency) = shopping_cart_credits::get_balance($userid);
        $data['initialtotal'] = $data['price'];
        // If there is no price key, we need to calculate it from items.
        if (!isset($data['price']) && isset($data['items'])) {
            $price = 0;
            foreach ($data['items'] as $item) {
                $price += $item->price;
            }
            $data['price'] = $price;
        }

        $pricebelowzero = shopping_cart_rebookingcredit::correct_total_price_for_rebooking($data);
        $usecredit = $pricebelowzero ? 0 : $usecredit;
        $balance = $pricebelowzero ? 0 : $balance;

        $data['currency'] = $currency ?: $data['currency'];

        // Now we account for discounts.
        if (isset($data['discount'])) {

            // If setting to round discounts is turned on, we round to full int.
            $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;
            $data['discount'] = round($data['discount'], $discountprecision);

            $data['initialtotal'] = $data['initialtotal'] + $data['discount'];

            $context = context_system::instance();
            // Right now, only the cashier has the right to use discounts.
            if (!has_capability('local/shopping_cart:cashier', $context)) {

                $data['price'] = $data['price'] + $data['discount'];
            }
        }

        // Only if the user has any credit at all, we apply the function.
        if ($balance > 0) {

            // We always calculate the deductible.
            if ($data['price'] <= $balance) {
                $deductible = $data['price'];
            } else {
                $deductible = $balance;
            }

            // We reduce the price if we use the credit.
            if ($usecredit) {

                $remainingtotal = $data['price'] - $deductible;
                $remainingcredit = $balance - $deductible;

                $data['usecredit'] = true;

            } else {
                $remainingcredit = $balance;
                $remainingtotal = $data['price'];
            }

            $data['credit'] = round($balance, 2);
            $data['deductible'] = round($deductible, 2);
            $data['price'] = round($remainingtotal, 2);
            $data['remainingcredit'] = round($remainingcredit, 2);
            $data['checkboxid'] = bin2hex(random_bytes(3));
        }
        return $data;
    }

}
