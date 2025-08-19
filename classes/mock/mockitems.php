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
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\mock;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Class cartstore
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mockitems {
    /**
     * Transformas item data for testing.
     *
     * @param int $itemid
     * @param float $price
     * @param string $tax
     * @param string $description
     * @param string $costcenter
     * @param int $nritems
     * @param int $multipliable
     *
     * @return array
     *
     */
    public static function transform_item(
        int $itemid,
        float &$price,
        string &$tax,
        string &$description,
        string &$costcenter,
        int &$nritems,
        int &$multipliable
    ): array {

        switch ($itemid) {
            case 1:
                $price = 10.00;
                $tax = 'A';
                $description = 'dummy item, tax category: ' . $tax;
                break;
            case 2:
                $price = 20.30;
                $tax = 'B';
                $description = 'dummy item, tax category: ' . $tax;
                break;
            case 3:
                $price = 13.8;
                $tax = 'C';
                $description = 'dummy item, tax category: ' . $tax;
                break;
            case 5:
                $price = 42.42;
                $tax = 'B';
                $installment = 1;
                $description = '(installment enabled), tax category: ' . $tax;
                break;
            case 6:
                $price = 10.00;
                $tax = 'A';
                $costcenter = 'CostCenter1';
                $description = '(' . $costcenter . '), tax category: ' . $tax;
                break;
            case 7:
                $price = 20.30;
                $tax = 'B';
                $costcenter = 'CostCenter2';
                $description = '(' . $costcenter . '), tax category: ' . $tax;
                break;
            case 8:
                $price = 13.8;
                $tax = 'C';
                $costcenter = 'CostCenter2';
                $description = '(' . $costcenter . '), tax category: ' . $tax;
                break;
            case 9:
                $price = 10.00;
                $tax = 'A';
                $description = 'Multipliable item, tax category: ' . $tax;
                $multipliable = 1;
                $nritems = 2; // Multipliable item with 2 items.
                break;
            default:
                $price = 12.12;
                $tax = '';
                break;
        }

        return [
            'itemid' => $itemid,
            'price' => $price,
            'tax' => $tax,
            'description' => $description,
            'costcenter' => $costcenter,
            'nritems' => $nritems,
            'multipliable' => $multipliable,
        ];
    }
}
