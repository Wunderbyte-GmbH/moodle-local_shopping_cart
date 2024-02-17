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
 * Entities Class to display list of entity records.
 *
 * @package local_shopping_cart
 * @author Thomas Winkler
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use cache;
use dml_exception;
use coding_exception;
use context_system;
use moodle_exception;
use ddl_exception;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Class shopping_cart
 *
 * @author Georg Maißer
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_rebookingcredit {

    /**
     * entities constructor.
     */
    public function __construct() {
    }

    /**
     * Performs checks and adds the rebookingcredit if it should be applied.
     *
     * @param array $cachedrawdata
     * @param string $area
     * @param int $userid
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws ddl_exception
     */
    public static function add_rebookingcredit(array &$cachedrawdata, string $area, int $userid) {

        // If we are on cashier and have a user to buy for.
        if ($userid == -1) {
            $userid = shopping_cart::return_buy_for_userid();
        }

        $cache = cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';

        $canceledrecords = self::get_records_canceled_with_future_canceluntil($userid);

        if (!empty($canceledrecords)
            && $area != 'bookingfee'
            && $area != 'rebookingcredit') {

            $normalitemsonly = array_filter($cachedrawdata["items"],
                fn($a) => ($a["area"] != 'bookingfee' && $a["area"] != 'rebookingcredit'));
            $itemcount = count($normalitemsonly);

            // We can only add as many rebookingcredits as we have canceled records.
            if ($itemcount <= count($canceledrecords)) {
                $rebookingcreditrecords = array_filter($cachedrawdata["items"],
                    fn($a) => ($a["area"] == 'rebookingcredit'));
                if (!empty($rebookingcreditrecords)) {
                    $rebookingcredititem = reset($rebookingcreditrecords);
                    shopping_cart::delete_item_from_cart('local_shopping_cart', 'rebookingcredit',
                        $rebookingcredititem['itemid'], $userid);
                }

                // Add the rebookingcredit to the shopping cart.
                self::add_rebookingcredit_item_to_cart($userid, $itemcount);
                $cachedrawdata = $cache->get($cachekey);
            }
        }
    }

    /**
     * If the user has recently cancelled an option we'll refund the rebookingcredit.
     *
     * @param int $userid
     * @return array
     */
    private static function get_records_canceled_with_future_canceluntil(int $userid): array {
        global $DB;
        $now = time();

        // If the user has recently cancelled an option we'll refund the rebookingcredit.
        // This will always only work with the MOST RECENTLY canceled option.
        $canceledrecords = $DB->get_records_select('local_shopping_cart_history',
            "userid = :userid AND paymentstatus = 3 AND area = 'option' AND canceluntil > :canceluntil", [
            'userid' => $userid,
            'canceluntil' => $now,
        ], '');

        return $canceledrecords;
    }

    /**
     * Helper function to check if a user has already used the rebookingcredit.
     *
     * @param int $userid
     * @return bool true if user has already received a rebookingcredit, else false
     */
    private static function rebookingcredit_already_used($userid) {
        global $DB;
        return $DB->record_exists_select('local_shopping_cart_ledger',
            "area = 'rebookingcredit' AND userid = :userid AND timecreated BETWEEN :lowesttimecreated AND :now", [
            'lowesttimecreated' => self::get_lowest_timecreated_of_canceledrecords($userid),
            'userid' => $userid,
            'now' => time(),
        ]);
    }

    /**
     * Get the lowest timecreated timestamp of the canceled records.
     *
     * @param int $userid
     * @return void
     */
    private static function get_lowest_timecreated_of_canceledrecords(int $userid) {
        global $DB;
        // If the user has recently cancelled an option we'll refund the rebookingcredit.
        // This will always only work with the MOST RECENTLY canceled option.
        $lowesttimecreated = $DB->get_field_sql("SELECT MIN(timecreated) AS lowesttimecreated
            FROM {local_shopping_cart_history}
            WHERE userid = :userid AND paymentstatus = 3 AND area = 'option' AND canceluntil > :canceluntil",
            [
                'userid' => $userid,
                'canceluntil' => time(),
            ]);

        return $lowesttimecreated;
    }

    /**
     *
     * Add rebookingcredit to cart.
     *
     *
     * @param int $userid the id of the user who books (-1 if cashier books for another user)
     * @param int $itemcount number of items
     *
     * @return bool
     */
    public static function add_rebookingcredit_item_to_cart(int $userid, int $itemcount = 1): bool {

        // If rebookingcredit is turned off in settings, we never add it at all.
        if (!get_config('local_shopping_cart', 'allowrebookingcredit')
            || self::rebookingcredit_already_used($userid) // If it was already used, we do not add.
            // For cashier rebookingcredit never gets added.
            || has_capability('local/shopping_cart:cashier', context_system::instance())) {
            return false;
        }

        shopping_cart::add_item_to_cart('local_shopping_cart', 'rebookingcredit', $itemcount, $userid);

        return true;
    }
}
