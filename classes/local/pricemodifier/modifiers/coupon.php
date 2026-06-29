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
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\pricemodifier\modifiers;

use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart;

/**
 * Coupon price modifier.
 */
abstract class coupon extends modifier_base {
    /**
     * The id is necessary for the hierarchy of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_COUPON;

    /**
     * Applies coupon discounts on the cached data.
     *
     * @param array $data
     * @return array
     */
    public static function apply(array &$data): array {
        $items = $data['items'] ?? [];

        // Reset coupon discounts if coupon is not active or disabled.
        if (empty($data['coupon']) || !get_config('local_shopping_cart', 'couponenabled')) {
            self::reset_coupon_discounts($items);
            $data['items'] = $items;
            $data['coupondiscount'] = 0;
            return $data;
        }

        $percent = (float) ($data['couponpercent'] ?? 0);
        $absolute = (float) ($data['couponabsolute'] ?? 0);

        if ($percent <= 0 && $absolute <= 0) {
            self::reset_coupon_discounts($items);
            $data['items'] = $items;
            $data['coupondiscount'] = 0;
            return $data;
        }

        $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;
        $remainingabsolute = $absolute;
        $coupondiscountsum = 0.0;

        foreach ($items as $key => $item) {
            $baseprice = $item['price'] + ($item['coupondiscount'] ?? 0);
            $item['price'] = $baseprice;
            unset($item['coupondiscount']);

            $itemdiscount = 0.0;
            if ($percent > 0) {
                $itemdiscount = round($baseprice * ($percent / 100), $discountprecision);
            } else if ($remainingabsolute > 0) {
                $itemdiscount = min($baseprice, $remainingabsolute);
                $itemdiscount = round($itemdiscount, $discountprecision);
                $remainingabsolute = max(0, $remainingabsolute - $itemdiscount);
            }

            if ($itemdiscount > 0) {
                $item['coupondiscount'] = $itemdiscount;
                $item['originalprice'] = $baseprice;
                $item['price'] = $baseprice - $itemdiscount;
                $coupondiscountsum += $itemdiscount;
            }

            $items[$key] = $item;
        }

        $data['items'] = $items;
        $data['coupondiscount'] = $coupondiscountsum;
        $data['price'] = shopping_cart::calculate_total_price($items);

        return $data;
    }

    /**
     * Reset coupon discounts on items.
     *
     * @param array $items
     * @return void
     */
    private static function reset_coupon_discounts(array &$items): void {
        foreach ($items as $key => $item) {
            if (!empty($item['coupondiscount'])) {
                $item['price'] = $item['price'] + (float) $item['coupondiscount'];
                unset($item['coupondiscount'], $item['originalprice']);
                $items[$key] = $item;
            }
        }
    }
}
