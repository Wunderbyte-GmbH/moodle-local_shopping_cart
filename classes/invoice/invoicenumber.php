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
 * Creat invoices with ERPNext using this class
 *
 * @package local_shopping_cart
 * @author David Bogner
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\invoice;

/**
 * Class invoice. To handle task linked to managing the invoice numbers.
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invoicenumber {

    /**
     * Save invoice number to db.
     *
     * @param \core\event\base $event
     *
     * @return [type]
     *
     */
    public static function save_invoice_number(\core\event\base $event) {

        global $DB;

        $eventdata = $event->get_data();
        $identifier = $eventdata['other']['identifier'];

        $startinvoicenumber = get_config('local_shopping_cart', 'startinvoicenumber');

        [$prefix, $number] = self::return_prefix_and_number($startinvoicenumber);

        if (!empty($prefix)) {
            $params = [
                'prefix' => $prefix . '%',
            ];
            $where = ' WHERE invoiceid LIKE :prefix ';
        } else {
            $where = '';
        }

        $sql = "SELECT COALESCE(MAX(invoiceid), '0') AS highestinvoiceid
                FROM {local_shopping_cart_invoices}
                $where";

        $highestinvoiceid = $DB->get_field_sql($sql, $params);
        [$highestprefix, $highestnumber] = self::return_prefix_and_number($highestinvoiceid);

        if ($highestnumber < $number) {
            $highestnumber = $number;
        } else {
            $highestnumber++;
        }

        $data = [
            'identifier' => $identifier,
            'invoiceid' => 0,
            'timecreated' => time(),
        ];
        $recordid = $DB->insert_record('local_shopping_cart_invoices', $data);
        $data = [
            'id' => $recordid,
            'invoiceid' => $prefix . $highestnumber,
        ];
        $DB->update_record('local_shopping_cart_invoices', $data);

        return;
    }

    /**
     * Function to use regex to return prefix & number.
     *
     * @param mixed $invoicenumber
     *
     * @return array
     *
     */
    private static function return_prefix_and_number($invoicenumber) {
        $pattern = '/^(.*\D)?(\d+)$/';
        preg_match($pattern, $invoicenumber, $matches);

        // If we have a prefix, we attribute it.
        if (!empty($matches[1])) {
            $prefix = $matches[1];
        }
        $number = empty($matches[2]) ? 0 : $matches[2];

        return [$prefix ?? '', $number];
    }

    /**
     * Returns the invoicenumber linked to the identifier.
     *
     * @param int $identifier
     *
     * @return string
     *
     */
    public static function get_invoicenumber_by_identifier(int $identifier) {
        global $DB;

        $invoicenumber = $DB->get_field('local_shopping_cart_invoices', 'invoiceid', ['identifier' => $identifier]);

        return $invoicenumber ?? '';
    }
}
