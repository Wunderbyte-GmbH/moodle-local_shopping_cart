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
 * Behat question-related steps definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\shopping_cart;

/**
 * Steps definitions related with the mooduell table management.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_shopping_cart extends behat_base {

    /*
     * Put item in my cart. This ads a dummy item to the cache. After reloading the page, the item will be visible.
     * @Given /^I put item "(?P<itemname_string>(?:[^"]|\\")*)" in my cart$/
     * @param string $itemname
     * @return void
     */
    public function i_put_item_in_my_cart(string $itemname) {
        global $USER;

        $shoppingcart = new shopping_cart();
        $curritems = $shoppingcart::local_shopping_cart_get_cache_data($USER->id);
        //TODO: get max itemid!
        $now = time();
        $canceluntil = strtotime('+14 days', $now);
        $serviceperiodestart = $now;
        $serviceperiodeend = strtotime('+100 days', $now);
        $itemid = 5;
        $price = 10.00;
        $tax = 'A';
        $area = 'main';
        $imageurl = new \moodle_url('/local/shopping_cart/pix/edu.png');

        $cartitem = new cartitem($itemid,
            $itemname . ' ' . $itemid,
            $price,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            $area,
            'item description',
            $imageurl->out(),
            $canceluntil,
            $serviceperiodestart,
            $serviceperiodeend,
            $tax,
        );

        $shoppingcart::add_item_to_cart('local_shopping_cart', 'bookingfee', $cartitem->itemid, $USER->id);
    }
}
