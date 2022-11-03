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

    /**
     * A timestamp until when canceling is possible.
     *
     * @var ?int
     */
    private $canceluntil;

    /**
     * Service period start timestamp
     *
     * @var ?int
     */
    private $serviceperiodstart;

    /**
     * Service period end timestamp
     *
     * @var ?int
     */
    private $serviceperiodend;

    /**
     * Cartitem Constructor.
     *
     * @param int $itemid id of cartitem
     * @param string $itemname name of item
     * @param float $price item price
     * @param string $currency currency for purchase
     * @param string $componentname moodle compoment that sells the item
     * @param string $description item description
     * @param string $imageurl url to the item image
     * @param int|null $canceluntil cancellation possible until
     * @param int|null $serviceperiodstart start of service period
     * @param int|null $serviceperiodend end of service period
     */
    public function __construct(int $itemid,
        string $itemname,
        float $price,
        string $currency,
        string $componentname,
        string $description = '',
        string $imageurl = '',
        ?int $canceluntil = null,
        ?int $serviceperiodstart = null,
        ?int $serviceperiodend = null) {
        $this->itemid = $itemid;
        $this->itemname = $itemname;
        $this->price = $price;
        $this->currency = $currency;
        $this->componentname = $componentname;
        $this->description = $description;
        $this->imageurl = $imageurl;
        $this->canceluntil = $canceluntil;
        $this->serviceperiodstart = $serviceperiodstart;
        $this->serviceperiodend = $serviceperiodend;
    }

    /**
     * Returns all the values as array.
     *
     * @return array
     */
    public function getitem(): array {
        $item = array();
        $item['itemid'] = $this->itemid;
        $item['itemname'] = $this->itemname;
        $item['price'] = $this->price;
        $item['currency'] = $this->currency;
        $item['componentname'] = $this->componentname;
        $item['description'] = $this->description;
        $item['imageurl'] = $this->imageurl;
        $item['canceluntil'] = $this->canceluntil;
        $item['serviceperiodstart'] = $this->serviceperiodstart;
        $item['serviceperiodend'] = $this->serviceperiodend;
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

    /**
     * Get the canceluntil timestamp.
     *
     * @return int
     */
    public function getcanceluntil(): int {
        return $this->canceluntil;
    }
}
