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
     * Price of the item as a float value including tax (gross)
     *
     * @var float
     */
    private $price;

    /**
     * Tax applied to this item price in percent float (20% tax = 0.2 float value).
     * If both $taxpercentage and $tax are set, the absolute $tax value takes precedence.
     *
     * @var float|null
     */
    private $taxpercentage;

    /**
     * Tax applied to this item $price as an absolute value in $currency. Net price = $price - $tax
     *
     * @var float|null
     */
    private $tax;

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
     * @var string|null
     */
    private $imageurl;

    /**
     * A timestamp until when canceling is possible.
     *
     * @var null|int
     */
    private $canceluntil;

    /**
     * Constructor for creating a cartitem.
     *
     * @param int $itemid
     * @param string $itemname
     * @param float $price
     * @param string $currency
     * @param string $componentname
     * @param string $description
     * @param string|null $imageurl
     * @param int|null $canceluntil
     * @param float|null $tax
     * @param float|null $taxpercentage
     */
    public function __construct(
            int $itemid,
            string $itemname,
            float $price,
            string $currency,
            string $componentname,
            string $description = '',
            string $imageurl = null,
            int $canceluntil = null,
            float $tax = null,
            float $taxpercentage = null) {
        $this->itemid = $itemid;
        $this->itemname = $itemname;
        $this->price = $price;
        $this->currency = $currency;
        $this->componentname = $componentname;
        $this->description = $description;
        $this->imageurl = $imageurl;
        $this->canceluntil = $canceluntil;
        $this->tax = $tax;
        $this->taxpercentage = $taxpercentage;
    }

    /**
     * Returns all the values as array.
     *
     * @return array
     */
    public function as_array(): array {
        $item = array();
        $item['itemid'] = $this->itemid;
        $item['itemname'] = $this->itemname;
        $item['price'] = $this->price;
        $item['currency'] = $this->currency;
        $item['componentname'] = $this->componentname;
        $item['description'] = $this->description;
        $item['imageurl'] = $this->imageurl;
        $item['canceluntil'] = $this->canceluntil;
        $item['tax'] = $this->tax;
        $item['taxpercentage'] = $this->taxpercentage;
        return $item;
    }

    /**
     * Get the gross price of the cartitem including any tax.
     *
     * @return float
     */
    public function price(): float {
        return $this->price;
    }

    /**
     * Get the tax amount in $currency absolute value.
     * Returns null if there is no tax defined.
     *
     * @return float|null
     */
    public function tax_amount(): ?float {
        if ($this->tax !== null) {
            return $this->tax;
        }
        if ($this->taxpercentage !== null) {
            return $this->price * $this->taxpercentage;
        }

        return null;
    }

    /**
     * Get the net price of the cartitem excluding any tax.
     *
     * @return float
     */
    public function net_price(): float {
        return $this->price - $this->tax_amount();
    }

    /**
     * Get the currency of the cartitem price.
     *
     * @return string
     */
    public function currency(): string {
        return $this->currency;
    }

    /**
     * Get the itemid.
     *
     * @return int
     */
    public function itemid(): int {
        return $this->itemid;
    }

    /**
     * Get the canceluntil timestamp.
     *
     * @return int|null
     */
    public function cancel_until_timestamp(): ?int {
        return $this->canceluntil;
    }

    /**
     * @return string|null
     */
    public function imageurl(): ?string {
        return $this->imageurl;
    }
}
