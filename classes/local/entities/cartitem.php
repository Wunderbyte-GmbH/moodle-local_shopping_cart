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
 * The cartitem class.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\entities;

use stdClass;

/**
 * The cartitem class.
 *
 * @copyright  2022 Georg Maißer Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cartitem {
    private $itemid;
    private $itemname;
    private $price;
    private $currency;
    private $componentname;
    private $description;

    public function __construct(int $itemid,
                                string $itemname,
                                float $price,
                                string $currency,
                                string $componentname,
                                string $description = '') {
        $this->itemid = $itemid;
        $this->itemname = $itemname;
        $this->price = $price;
        $this->currency = $currency;
        $this->componentname = $componentname;
        $this->description = $description;
    }

    /**
     * Returns all the values as array.
     *
     * @return array
     */
    public function get_item():array {
        $item = array();
        $item['itemid'] = $this->itemid;
        $item['itemname'] = $this->itemname;
        $item['price'] = $this->price;
        $item['currency'] = $this->currency;
        $item['componentname'] = $this->componentname;
        $item['description'] = $this->description;
        return $item;
    }

    /**
     * Get the price of the cartitem.
     *
     * @return float
     */
    public function get_price(): float {
        return $this->price;
    }

    /**
     * Get the currency of the cartitem price.
     *
     * @return string
     */
    public function get_currency(): string {
        return $this->currency;
    }

    /**
     * Get the itemid.
     *
     * @return int
     */
    public function get_itemid(): int {
        return $this->itemid;
    }
}
