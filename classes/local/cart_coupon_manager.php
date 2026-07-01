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
 * Facade for all coupon state operations on the cart cache.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Class cart_coupon_manager
 *
 * Facade that owns all coupon-related reads and writes on the cart cache.
 * Callers obtain an instance by passing a cartstore, then interact only
 * with this class for anything coupon-related.
 *
 * @author Georg Maißer
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cart_coupon_manager {
    /** @var cartstore */
    private cartstore $cartstore;

    /**
     * Constructor.
     *
     * @param cartstore $cartstore
     */
    public function __construct(cartstore $cartstore) {
        $this->cartstore = $cartstore;
    }

    /**
     * Check if a coupon is currently applied to the cart.
     *
     * @return bool
     */
    public function coupon_applied(): bool {
        $data = $this->cartstore->get_cache();
        return !empty($data['coupon']);
    }

    /**
     * Return the coupon code currently applied to the cart, or empty string.
     *
     * @return string
     */
    public function get_applied_coupon(): string {
        $data = $this->cartstore->get_cache();
        return !empty($data['coupon']) ? $data['coupon'] : '';
    }

    /**
     * Remove the applied coupon and restore all item prices to their pre-coupon values.
     *
     * @return void
     */
    public function clear_coupon(): void {
        $data = $this->cartstore->get_cache();

        if (!$data) {
            return;
        }

        if (!empty($data['items'])) {
            $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;

            foreach ($data['items'] as $cacheitemkey => $item) {
                if (isset($item['discount'])) {
                    $discount = round($item['discount'], $discountprecision);
                    $item['price'] = $item['price'] + $discount;
                    unset($item['discount']);
                    unset($item['discountpercentage']);
                    unset($item['discountabsolute']);
                }
                if (isset($item['coupondiscount'])) {
                    $item['price'] = $item['price'] + (float) $item['coupondiscount'];
                    unset($item['coupondiscount'], $item['originalprice']);
                }
                $data['items'][$cacheitemkey] = $item;
            }
        }

        unset($data['coupon']);
        unset($data['couponpercent']);
        unset($data['couponabsolute']);
        unset($data['couponcurrency']);
        unset($data['coupondiscount']);
        unset($data['coupontype']);
        $this->cartstore->set_cache($data);
    }

    /**
     * Store the total coupon discount amount in the cart cache.
     *
     * @param float $amount
     * @return void
     */
    public function set_coupon_discount(float $amount): void {
        $data = $this->cartstore->get_cache();

        if (!$data) {
            return;
        }

        $data['coupondiscount'] = $amount;
        $this->cartstore->set_cache($data);
    }

    /**
     * Persist all coupon metadata (code, rates, currency) in the cart cache.
     *
     * @param string $coupon
     * @param float $percent
     * @param float $absolute
     * @param string $currency
     * @return void
     */
    public function set_coupon_data(
        string $coupon,
        float $percent,
        float $absolute,
        string $currency,
        string $coupontype = ''
    ): void {
        $data = $this->cartstore->get_cache();

        if (!$data) {
            return;
        }

        $data['coupon'] = $coupon;
        $data['couponpercent'] = $percent;
        $data['couponabsolute'] = $absolute;
        $data['couponcurrency'] = $currency;
        $data['coupontype'] = $coupontype;
        $this->cartstore->set_cache($data);
    }
}
