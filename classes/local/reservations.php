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

/**
 * Class cartstore
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reservations {
    /**
     * Writes the given data object to the reserveration table.
     *
     * @param array $data
     *
     * @return [type]
     *
     */
    public static function save_reservation(array $data) {

        global $DB, $USER;

        $now = time();

        if (
            $record = $DB->get_record(
                'local_shopping_cart_reserv',
                [
                    'userid' => $data['userid'],
                ]
            )
        ) {
            $record->json = json_encode($data);
            $record->usermodified = $USER->id;
            $record->expirationtime = $data['expirationtime'];
            $DB->update_record('local_shopping_cart_reserv', $record);
        } else {
            $DB->insert_record(
                'local_shopping_cart_reserv',
                [
                    'userid' => $data['userid'],
                    'json' => json_encode($data),
                    'expirationtime' => $data['expirationtime'],
                    'usermodified' => $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]
            );
        }
    }

    /**
     * Method to delete
     *
     * @param int $userid
     *
     * @return [type]
     *
     */
    public static function delete_reservation(int $userid) {
        global $DB;

        return $DB->delete_records(
            'local_shopping_cart_reserv',
            [
                'userid' => $userid,
            ]
        );
    }
}
