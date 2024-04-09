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
use local_shopping_cart\shopping_cart_credits;

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
        // $usecredit = shopping_cart_credits::use_credit_fallback(null, $userid);

        if (!isset($data['items'])) {
            list($data['credit'], $data['currency']) = shopping_cart_credits::get_balance($data['userid']);
            $data['items'] = [];
            $data['remainingcredit'] = $data['credit'];
        } else {
            $count = isset($data['items']) ? count($data['items']) : 0;
            $data['count'] = $count;

            $data['currency'] = $data['currency'] ?? null;
            $data['credit'] = $data['credit'] ?? null;
            $data['remainingcredit'] = $data['credit'];
        }
        return $data;
    }

}
