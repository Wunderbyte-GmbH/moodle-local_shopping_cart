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
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart;
use context_system;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class standard extends modifier_base {

    /**
     * The id is nedessary for the hierarchie of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_STANDARD;

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $data
     * @return array
     * @throws coding_exception
     */
    public static function apply(array &$data): array {

        $items = $data['items'];
        $data['count'] = count($items);
        $data['price'] = shopping_cart::calculate_total_price($items);
        $data['initialtotal'] = $data['price'];
        $data['items'] = $data['items'] ?? [];
        $data['installments'] = $data['installments'] ?? [];

        $context = context_system::instance();

        // A few modifications.
        $rebooking = 0;
        foreach ($items as $key => $item) {
            // As a cashier, I always want to be able to delete the booking fee.
            if ($items[$key]['nodelete'] === 1 &&
                has_capability('local/shopping_cart:cashier', $context)) {
                    unset($data['items'][$key]['nodelete']);
            }

            if ($item['componentname'] === 'local_shopping_cart'
                && $item['area'] === 'rebookitem') {
                    $rebooking++;
            }
        }

        if ($rebooking > 0 && $rebooking === count($items)) {
            $data['onlyrebooking'] = true;
        }
        return $data;
    }
}
