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

use coding_exception;
use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use local_shopping_cart\shopping_cart_rebookingcredit;
use context_system;
use dml_exception;
use Random\RandomException;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class credits extends modifier_base {

    /**
     * The id is nedessary for the hierarchie of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_CREDITS;

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $data
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws RandomException
     */
    public static function apply(array &$data): array {
        global $USER;

        $userid = $data['userid'];
        $usecredit = shopping_cart_credits::use_credit_fallback(null, $userid);

        $balance = $data['credit'];

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
