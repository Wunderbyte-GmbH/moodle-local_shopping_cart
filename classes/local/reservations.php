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

use moodle_exception;

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
                    'identifier' => $data['identifier'] ?? null,
                ]
            )
        ) {
            if (empty($record->identifier)) {
                $record->json = json_encode($data);
                $record->usermodified = $USER->id;
                $record->expirationtime = $data['expirationtime'];
                $DB->update_record('local_shopping_cart_reserv', $record);
            }
            // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
            /* else if (
                $record->json != json_encode($data)
            ) {
                // We don't update.
                // throw new moodle_exception('tryingtoupdateunqiuecart', 'local_shopping_cart');
            } */
        } else {
            $DB->insert_record(
                'local_shopping_cart_reserv',
                [
                    'userid' => $data['userid'],
                    'json' => json_encode($data),
                    'expirationtime' => $data['expirationtime'],
                    'identifier' => $data['identifier'] ?? null,
                    'usermodified' => $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]
            );
        }
    }

    /**
     * Method to get the json from the db
     *
     * @param int $userid
     * @param ?int $identifier
     *
     * @return ?array
     *
     */
    public static function get_json_from_db(int $userid, $identifier = null) {
        global $DB;

        $record = $DB->get_record(
            'local_shopping_cart_reserv',
            [
                'userid' => $userid,
                'identifier' => $identifier,
            ]
        );

        return $record ? json_decode($record->json, true) : null;
    }

    /**
     * Method to get the json from the db
     *
     * @param ?int $identifier
     *
     * @return ?array
     *
     */
    public static function get_json_from_db_via_identifier($identifier = null) {
        global $DB;

        $record = $DB->get_record(
            'local_shopping_cart_reserv',
            [
                'identifier' => $identifier,
            ]
        );

        return $record ? json_decode($record->json, true) : null;
    }

    /**
     * Method to delete
     *
     * @param int $userid
     * @param ?int $identifier
     *
     * @return bool
     *
     */
    public static function delete_reservation(int $userid, $identifier = null) {
        global $DB;

        return $DB->delete_records(
            'local_shopping_cart_reserv',
            [
                'userid' => $userid,
                'identifier' => $identifier,
            ]
        );
    }

    /**
     * Returns the cart of a checkout that is still running, if there is one.
     *
     * Once the checkout has started, the reservation carries the identifier of that cart, so a
     * lookup by a missing identifier does not find it any more. While the reservation is still
     * held and nothing was paid for it yet, that cart is the one the user is working on.
     *
     * @param int $userid
     *
     * @return ?array
     *
     */
    public static function get_open_reservation(int $userid) {

        global $DB;

        $sql = "SELECT r.*
                  FROM {local_shopping_cart_reserv} r
                 WHERE r.userid = :userid
                   AND r.expirationtime > :now
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {local_shopping_cart_history} h
                        WHERE h.identifier = r.identifier
                          AND h.paymentstatus >= :success
                   )
              ORDER BY r.timemodified DESC, r.id DESC";

        $params = [
            'userid' => $userid,
            'now' => time(),
            'success' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
        ];

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        $record = reset($records);

        return $record ? json_decode($record->json, true) : null;
    }

    /**
     * Moves the stored expiration time of a reservation forward.
     *
     * The expiration time is prolonged while a payment is running, so it has to be stored and not
     * only kept in the cart cache. It is never shortened here: a reservation that is already held
     * longer stays held.
     *
     * @param int $userid
     * @param int $expirationtime
     *
     * @return void
     *
     */
    public static function update_expiration(int $userid, int $expirationtime) {

        global $DB;

        if (empty($expirationtime)) {
            return;
        }

        $records = $DB->get_records('local_shopping_cart_reserv', ['userid' => $userid]);

        foreach ($records as $record) {
            if ((int) $record->expirationtime >= $expirationtime) {
                continue;
            }

            $data = json_decode($record->json, true);

            if (is_array($data)) {
                $data['expirationtime'] = $expirationtime;
                foreach ($data['items'] ?? [] as $key => $item) {
                    $data['items'][$key]['expirationtime'] = $expirationtime;
                }
                $record->json = json_encode($data);
            }

            $record->expirationtime = $expirationtime;
            $record->timemodified = time();

            $DB->update_record('local_shopping_cart_reserv', $record);
        }
    }

    /**
     * Method to check if the cart is different with the same identifier.
     * This will also write to db if the cart is not there.
     *
     * @param mixed $data
     * @param mixed $identifier
     * @param bool $storenewcart
     *
     * @return bool
     *
     */
    public static function different_cart_with_same_identifier(
        $data,
        $identifier,
        $storenewcart = false
    ) {
        if (empty($data['userid'])) {
            return false;
        }
        $json = self::get_json_from_db($data['userid'], $identifier);

        if (!$json) {
            if ($storenewcart) {
                unset($data['historyitems']);
                self::save_reservation($data);
            }
            return false;
        }
        foreach ($data as $key => $value) {
            if (
                in_array(
                    $key,
                    [
                        'nowdate',
                        'checkboxid',
                        'historyitems',
                        'storedinhistory',
                        // A cart that is held longer is still the same cart, see update_expiration.
                        'expirationtime',
                    ]
                )
            ) {
                continue;
            }

            if (isset($json[$key]) && is_array($json[$key])) {
                foreach ($value as $item) {
                    if (count(array_filter($json[$key], fn($a) => $a['itemid'] == $item['itemid'])) == 0) {
                        return true;
                    }
                }
            } else if (!isset($json[$key]) || is_array($json[$key]) || $json[$key] != $value) {
                return true;
            }
        }

        return false;
    }
}
