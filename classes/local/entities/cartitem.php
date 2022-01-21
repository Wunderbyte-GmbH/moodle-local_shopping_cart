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

    /**
     * Item id
     *
     * @var int
     */
    private $itemid;

    /**
     * Name of the item
     *
     * @var string
     */
    private $itemname;

    /**
     * Price of the item as a float value
     *
     * @var float
     */
    private $price;

    /**
     * Currency must be the same for all items in cart.
     *
     * @var string
     */
    private $currency;

    /**
     * Name of component (like local_shopping_cart)
     *
     * @var string
     */
    private $componentname;

    /**
     * Description of the item
     *
     * @var string
     */
    private $description;

    /**
     * Link to image
     *
     * @var string
     */
    private $imageurl;

    public function __construct(int $itemid,
                                string $itemname,
                                float $price,
                                string $currency,
                                string $componentname,
                                string $description = '',
                                string $imageurl = null) {
        $this->itemid = $itemid;
        $this->itemname = $itemname;
        $this->price = $price;
        $this->currency = $currency;
        $this->componentname = $componentname;
        $this->description = $description;
        $this->imageurl = $imageurl;
    }

    /**
     * Returns all the values as array.
     *
     * @return array
     */
    public function getitem():array {
        $item = array();
        $item['itemid'] = $this->itemid;
        $item['itemname'] = $this->itemname;
        $item['price'] = $this->price;
        $item['currency'] = $this->currency;
        $item['componentname'] = $this->componentname;
        $item['description'] = $this->description;
        $item['imageurl'] = $this->imageurl;
        return $item;
    }

    /**
     * Get the price of the cartitem.
     *
     * @return float
     */
    public function getprice(): float {
        return $this->price;
    }

    /**
     * Get the currency of the cartitem price.
     *
     * @return string
     */
    public function getcurrency(): string {
        return $this->currency;
    }

    /**
     * Get the itemid.
     *
     * @return int
     */
    public function getitemid(): int {
        return $this->itemid;
    }
}
