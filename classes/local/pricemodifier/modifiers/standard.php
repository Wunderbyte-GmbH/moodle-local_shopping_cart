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

use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class standard extends modifier_base {

    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_STANDARD;

    public static function apply(array &$data): array {

        $cartstore = cartstore::instance($data['userid']);
        $items = $cartstore->get_items();
        $data['count'] = count($items);
        $data['price'] = shopping_cart::calculate_total_price($items);
        $data['initialtotal'] = $data['price'];
        return $data;
    }
}
