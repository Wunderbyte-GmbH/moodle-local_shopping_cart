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

namespace local_shopping_cart\local;

use cache_helper;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Class coupon
 *
 * @author Georg Maißer
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupon {
    /** @var int */
    protected $userid = 0;

    /**
     * Cartstore constructor.
     * @param int $userid
     * @return void
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }
    /**
     * Apply coupon code to the shopping cart of the given user.
     *
     * @param string $couponcode
     *
     * @return array
     *
     */
    public function apply_coupon_code(string $couponcode): array {

        if (
            !get_config('local_shopping_cart', 'couponenabled')
        ) {
            return [false, ''];
        }

        // If the coupon code is empty, it might already be applied.
        if (empty($couponcode)) {
            $cartstore = cartstore::instance((int)$this->userid);
            if ($cartstore->coupon_applied()) {
                $coupon = $cartstore->get_applied_coupon();
                return [true, get_string('couponappliedsuccessfully', 'local_shopping_cart', $coupon)];
            } else {
                return [false, ''];
            }
        }

        $message = '';

        // Find the coupon in the database and make it can be applied.
        try {
            $coupon = $this->get_coupon_by_code($couponcode);
        } catch (moodle_exception $e) {
            // Invalid coupon code, nothing to do.
            return [false, get_string('couponcouldnotbeapplied', 'local_shopping_cart', $couponcode)];
        }

        $message = $this->validate_coupon($coupon, $this->userid);

        if ($message !== '') {
            // Coupon is not valid, nothing to do.
            return [false, get_string('couponcouldnotbeapplied', 'local_shopping_cart', $couponcode)];
        }

        $cartstore = cartstore::instance((int)$this->userid);
        $items = $cartstore->get_items();

        foreach ($items as $item) {
            // Discount absolute can only be applied if the item price is higher than the discount.
            if (
                empty($coupon->discountpercentage)
                && $coupon->discountabsolute > 0
                && $item->price < $coupon->discountabsolute
            ) {
                $remainingdiscount = $coupon->discountabsolute - $item->price;
                $coupon->discountabsolute = $item->price;
            } else if (!empty($remainingdiscount)) {
                if ($item->price >= $remainingdiscount) {
                    $coupon->discountabsolute = $remainingdiscount;
                    $remainingdiscount = 0;
                } else {
                    $coupon->discountabsolute = $item->price;
                    $remainingdiscount -= $item->price;
                }
            }

            $result = $cartstore->add_discount_to_item(
                $item['componentname'],
                $item['area'],
                $item['itemid'],
                $coupon->discountpercentage,
                $coupon->discountabsolute,
                $item['downpayment'] ?? -1,
                $couponcode
            );
        }

        if (!empty($result['success'])) {
            $message = get_string('couponappliedsuccessfully', 'local_shopping_cart', $couponcode);
        } else {
            $message = get_string('couponcouldnotbeapplied', 'local_shopping_cart', $couponcode);
        }

        return [true, $message];
    }

    /**
     * Get coupon by its code.
     *
     * @param string $couponcode
     *
     * @return stdClass
     *
     */
    private function get_coupon_by_code(string $couponcode): stdClass {
        global $DB;

        $coupon = $DB->get_record('local_shopping_cart_coupons', ['coupon' => $couponcode], '*', IGNORE_MISSING);

        if (!$coupon) {
            throw new moodle_exception('invalidcouponcode', 'local_shopping_cart');
        }

        return $coupon;
    }

    /**
     * The validation of the coupon.
     *
     * @param stdClass $coupon
     * @param int $userid
     *
     * @return string
     *
     */
    private function validate_coupon(stdClass $coupon, int $userid): string {
        $message = '';

        // Check if the coupon is valid for the user.
        // This is not yet implemented.

        // Check if the coupon is expired.
        $now = time();
        if ($coupon->starttime > 0 && $now < $coupon->starttime) {
            $message = get_string('couponnotvalidyet', 'local_shopping_cart');
            return $message;
        }
        if ($coupon->endtime > 0 && $now > $coupon->endtime) {
            $message = get_string('couponexpired', 'local_shopping_cart');
            return $message;
        }

        return $message;
    }

    /**
     * Add or edit coupon.
     *
     * @param int $id
     * @param string $coupon
     * @param float $discountpercentage
     * @param float $discountabsolute
     * @param string $currency
     * @param int $maxnumber
     * @param int $active
     * @param int $starttime
     * @param int $endtime
     * @param int $usermodified
     *
     * @return void
     *
     */
    public static function add_edit_coupon(
        int $id,
        string $coupon,
        float $discountpercentage,
        float $discountabsolute,
        string $currency,
        int $maxnumber,
        int $active,
        int $starttime,
        int $endtime,
        int $usermodified
    ): void {
        global $DB;

        $record = new stdClass();
        $record->id = $id;
        $record->coupon = $coupon;
        $record->discountpercentage = $discountpercentage;
        $record->discountabsolute = $discountabsolute;
        $record->currency = $currency;
        $record->maxnumber = $maxnumber;
        $record->active = $active;
        $record->starttime = $starttime;
        $record->endtime = $endtime;
        $record->usermodified = $usermodified;
        $record->timemodified = time();

        if ($id) {
            // Update existing record.
            $DB->update_record('local_shopping_cart_coupons', $record);
        } else {
            // New record.
            $record->timecreated = time();
            $DB->insert_record('local_shopping_cart_coupons', $record);
        }

        cache_helper::purge_by_event('setbackcachedcouponstable');
    }
}
