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

use dml_exception;
use coding_exception;
use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\taxcategories;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class taxes extends modifier_base {

    /**
     * The id is nedessary for the hierarchie of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_TAXES;

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $data
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function apply(array &$data): array {

        $taxesenabled = get_config('local_shopping_cart', 'enabletax') == 1;
        if ($taxesenabled) {
            $taxcategories = taxcategories::from_raw_string(
                    get_config('local_shopping_cart', 'defaulttaxcategory'),
                    get_config('local_shopping_cart', 'taxcategories')
            );
            $data['items'] = shopping_cart::update_item_price_data(array_values($data['items']), $taxcategories);
            $data['price'] = shopping_cart::calculate_total_price($data["items"]);
            $data['price_net'] = shopping_cart::calculate_total_price($data["items"], true);
            $data['initialtotal'] = $data['price'];
            $data['initialtotal_net'] = $data['price_net'];
        }

        $data['taxesenabled'] = $taxesenabled;

        return $data;
    }
}
