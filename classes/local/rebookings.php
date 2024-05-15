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

use stdClass;

/**
 * Class cartstore
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rebookings {

    /**
     * Checks all the conditions if we should allow rebooking.
     * @param stdClass $item
     * @param int $userid
     */
    public static function allow_rebooking(stdClass $item, int $userid = 0) {

        global $DB;

        // If rebooking is turned off.
        if (empty(get_config('local_shopping_cart', 'allowrebooking'))) {
            return false;
        }

        // First check, if it is the wrong kind of item.
        if ($item->componentname === 'local_shopping_cart'
            && in_array($item->area, ['bookingfee', 'rebookingcredit', 'rebookitem'])) {
            return false;
        }

        // If the item was already canceled.
        if ($item->canceled) {
            return false;
        }

        // If the item is outside the service period.
        if (!empty($item->serviceperiodend)
            && $item->serviceperiodend < time()) {
            return false;
        }

        // If the rebooking period is not empty, we check the rest.
        // This is very expensive, we do it last.
        if (!empty(get_config('local_shopping_cart', 'rebookingperiod')
            && !empty(get_config('local_shopping_cart', 'rebookingmaxnumber')))) {

            $maxnumberofrebookings = get_config('local_shopping_cart', 'rebookingmaxnumber');

            $rebookingperiod = get_config('local_shopping_cart', 'rebookingperiod');
            $limitdate = strtotime(" - $rebookingperiod days ");

            $sql = "SELECT COUNT(id)
                    FROM {local_shopping_cart_history}
                    WHERE componentname = :componentname
                    AND area = :area
                    AND userid = :userid
                    AND timecreated > :limitdate";

            $params = [
                'componentname' => 'local_shopping_cart',
                'area' => 'rebookitem',
                'userid' => $item->userid,
                'limitdate' => $limitdate,
            ];

            $numberrebookings = $DB->count_records_sql($sql, $params);

            if ($maxnumberofrebookings <= $numberrebookings) {
                return false;
            }

        }

        // Finally, we have a look if allowrebooking is turned off for this particular item.
        if ($record = $DB->get_record('local_shopping_cart_iteminfo', [
            'componentname' => $item->componentname,
            'area' => $item->area,
            'itemid' => $item->itemid])) {

            $jsonobject = json_decode($record->json);

            if ($jsonobject && empty($jsonobject->allowrebooking)) {
                return false;
            }
        }

        return true;
    }

}
