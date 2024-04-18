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

namespace local_shopping_cart\local\pricemodifier\modifiers;

use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\shopping_cart_handler;

/**
 * Class taxes
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class installments extends modifier_base {

    /**
     * The id is nedessary for the hierarchie of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_INSTALLMENTS;

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $data
     * @return array
     */
    public static function apply(array &$data): array {

        global $DB;

        foreach ($data['items'] as $key => $itemdata) {
            if (shopping_cart_handler::installment_exists(
                $itemdata['componentname'],
                $itemdata['area'],
                $itemdata['itemid'])) {

                if ($data['useinstallments']) {
                    $searchdata = [
                        'itemid' => $itemdata['itemid'],
                        'componentname' => $itemdata['componentname'],
                        'area' => $itemdata['area'],
                    ];

                    $record = $DB->get_record('local_shopping_cart_iteminfo', $searchdata);
                    $jsonobject = json_decode($record->json);

                    // Check which payment it is.
                    // If this is the first payment, price is price - firstamount.
                    // If this is a further payment, price is installment rate.

                    $data['items'][$key]['price'] -= $jsonobject->firstamount;
                }
                $data['installments'] = true;
            }
        }

        return  $data;
    }
}
