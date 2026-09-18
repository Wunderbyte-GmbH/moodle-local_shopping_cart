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
use local_shopping_cart\utils\wb_payment;
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
    /** @var string Count one usage per checkout (ledger identifier), however many items were discounted. */
    public const COUNTMODE_CHECKOUT = 'checkout';

    /** @var string Count one usage per discounted item (ledger record). */
    public const COUNTMODE_ITEM = 'item';

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

        // Coupons are a PRO feature: refuse to apply/remove one without a valid license,
        // even if the site admin left the "couponenabled" setting switched on.
        if (!wb_payment::pro_version_is_activated()) {
            return [false, get_string('couponsprofeaturenotice', 'local_shopping_cart')];
        }

        $cartstore = cartstore::instance((int)$this->userid);
        $couponmanager = new cart_coupon_manager($cartstore);

        // If the coupon code is empty, remove any existing coupon.
        if (empty($couponcode)) {
            if ($couponmanager->coupon_applied()) {
                $coupon = $couponmanager->get_applied_coupon();
                $couponmanager->clear_coupon();
                return [true, get_string('couponremovedsuccessfully', 'local_shopping_cart', $coupon)];
            }
            return [false, ''];
        }

        // A coupon that is already applied is validated again, because a usage limit may have been
        // reached since it was applied. Otherwise keeping it in the cart would bypass the limits.
        if ($couponmanager->coupon_applied()) {
            $appliedcoupon = $couponmanager->get_applied_coupon();
            if ($appliedcoupon === $couponcode) {
                try {
                    $message = $this->validate_coupon($this->get_coupon_by_code($couponcode), $this->userid);
                } catch (moodle_exception $e) {
                    $message = get_string('invalidcouponcode', 'local_shopping_cart');
                }
                if ($message === '') {
                    return [true, get_string('couponappliedsuccessfully', 'local_shopping_cart', $couponcode)];
                }
                $couponmanager->clear_coupon();
                return [false, get_string('couponcouldnotbeapplied', 'local_shopping_cart', $couponcode) . ' ' . $message];
            }
            // Switch coupon: remove existing one before applying a new code.
            $couponmanager->clear_coupon();
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
            return [false, get_string('couponcouldnotbeapplied', 'local_shopping_cart', $couponcode) . ' ' . $message];
        }

        $couponmanager->set_coupon_data(
            $couponcode,
            (float) $coupon->discountpercentage,
            (float) $coupon->discountabsolute,
            (string) $coupon->currency,
            (string) ($coupon->coupontype ?? '')
        );

        $message = get_string('couponappliedsuccessfully', 'local_shopping_cart', $couponcode);
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
        global $DB;

        // Check if the coupon is active.
        if (empty($coupon->active)) {
            return get_string('couponnotactive', 'local_shopping_cart');
        }

        // Check if the coupon is expired.
        $now = time();
        if ($coupon->starttime > 0 && $now < $coupon->starttime) {
            return get_string('couponnotvalidyet', 'local_shopping_cart');
        }
        if ($coupon->endtime > 0 && $now > $coupon->endtime) {
            return get_string('couponexpired', 'local_shopping_cart');
        }

        $countmode = (string) ($coupon->countmode ?? self::COUNTMODE_CHECKOUT);

        // Check max number of uses (0 = unlimited).
        if (!empty($coupon->maxnumber)) {
            $timesused = self::count_usages((int) $coupon->id, $countmode);
            if ($timesused >= $coupon->maxnumber) {
                return get_string('couponmaxusesreached', 'local_shopping_cart');
            }
        }

        // Check max number of uses for this user (0 = unlimited).
        if (!empty($coupon->maxnumberperuser)) {
            $timesused = self::count_usages((int) $coupon->id, $countmode, $userid);
            if ($timesused >= $coupon->maxnumberperuser) {
                return get_string('couponmaxusesperuserreached', 'local_shopping_cart');
            }
        }

        return '';
    }

    /**
     * Count how often a coupon has been used, based on the ledger.
     *
     * Only successful payments that were not cancelled count. A ledger record references the coupon
     * only when the coupon actually reduced the price of that item, so in item mode every discounted
     * item is one usage, while in checkout mode all items paid under the same identifier are one
     * usage together.
     *
     * @param int $couponid
     * @param string $countmode one of the COUNTMODE_* constants
     * @param int|null $userid restrict the count to this user, null for all users
     * @return int
     */
    public static function count_usages(int $couponid, string $countmode, ?int $userid = null): int {
        global $DB;

        // A cancelled purchase gives its usage back. The cancellation is a separate ledger record
        // pointing to the same history entry. A checkout counts as long as one of its discounted
        // items is still bought.
        $params = [
            'couponid' => (string) $couponid,
            'status' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            'canceled' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
        ];
        $where = "l.coupon = :couponid
              AND l.paymentstatus = :status
              AND NOT EXISTS (
                    SELECT 1
                      FROM {local_shopping_cart_ledger} c
                     WHERE c.schistoryid = l.schistoryid
                       AND c.paymentstatus = :canceled
                  )";
        if ($userid !== null) {
            $where .= " AND l.userid = :userid";
            $params['userid'] = $userid;
        }

        $count = $countmode === self::COUNTMODE_ITEM ? 'COUNT(1)' : 'COUNT(DISTINCT l.identifier)';

        return (int) $DB->count_records_sql(
            "SELECT $count FROM {local_shopping_cart_ledger} l WHERE $where",
            $params
        );
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
     * @param string $coupontype
     * @param int $maxnumberperuser 0 is unlimited
     * @param string $countmode one of the COUNTMODE_* constants
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
        int $usermodified,
        string $coupontype,
        int $maxnumberperuser = 0,
        string $countmode = self::COUNTMODE_CHECKOUT
    ): void {
        global $DB;

        if (!in_array($countmode, [self::COUNTMODE_CHECKOUT, self::COUNTMODE_ITEM], true)) {
            throw new moodle_exception('invalidcountmode', 'local_shopping_cart');
        }

        $record = new stdClass();
        $record->id = $id;
        $record->coupon = $coupon;
        $record->discountpercentage = $discountpercentage;
        $record->discountabsolute = $discountabsolute;
        $record->currency = $currency;
        $record->maxnumber = $maxnumber;
        $record->maxnumberperuser = $maxnumberperuser;
        $record->countmode = $countmode;
        $record->active = $active;
        $record->coupontype = $coupontype;
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
