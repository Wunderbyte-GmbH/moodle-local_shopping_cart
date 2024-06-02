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
     * Tax category applied to this item.
     *
     * @var string|null
     */
    private $taxcategory;

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
     * Name of area (like main)
     *
     * @var string
     */
    private $area;

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
     * Nodelete is 1 when item can't be deleted by user.
     *
     * @var ?int
     */
    private $nodelete;

    /**
     * Cost center.
     *
     * @var ?string
     */
    public $costcenter; // Needs to be public!

    /**
     * Installment.
     *
     * @var ?string
     */
    private $installment; // Needs to be public!

    /**
     * Linkeditem identifiers define items that are connected.
     * This information is used eg to calculate installments.
     *
     * @var ?string
     */
    private $linkeditem;

    /**
     * Constructor for creating a cartitem.
     *
     * @param int $itemid id of cartitem
     * @param string $itemname name of item
     * @param float $price item price
     * @param string $currency currency for purchase
     * @param string $componentname moodle compoment that sells the item
     * @param string $area moodle area that applies
     * @param string $description item description
     * @param string $imageurl url to the item image
     * @param int|null $canceluntil cancellation possible until
     * @param int|null $serviceperiodstart start of service period
     * @param int|null $serviceperiodend end of service period
     * @param string|null $taxcategory the tax category of this item
     * @param int $nodelete if item can't bedeleted from cart by user.
     * @param string|null $costcenter The cost center of this item.
     * @param string|null $installment The identifier (unixtimestamp) of the installment.
     * @param string|null $linkeditem The identifier of linked items.
     */
    public function __construct(int $itemid,
            string $itemname,
            float $price,
            string $currency,
            string $componentname,
            string $area,
            string $description = '',
            string $imageurl = '',
            ?int $canceluntil = null,
            ?int $serviceperiodstart = null,
            ?int $serviceperiodend = null,
            ?string $taxcategory = null,
            int $nodelete = 0,
            ?string $costcenter = null,
            ?string $installment = null,
            ?string $linkeditem = null) {
        $this->itemid = $itemid;
        $this->itemname = $itemname;
        $this->price = $price;
        $this->currency = $currency;
        $this->componentname = $componentname;
        $this->area = $area;
        $this->description = $description;
        $this->imageurl = $imageurl;
        $this->canceluntil = $canceluntil;
        $this->serviceperiodstart = $serviceperiodstart;
        $this->serviceperiodend = $serviceperiodend;
        $this->taxcategory = $taxcategory;
        $this->nodelete = $nodelete;
        $this->costcenter = $costcenter;
        $this->installment = $installment;
        $this->linkeditem = $linkeditem;
    }

    /**
     * Returns all the values as array.
     *
     * @return array
     */
    public function as_array(): array {
        $item = [];
        $item['itemid'] = $this->itemid;
        $item['itemname'] = $this->itemname;
        $item['price'] = $this->price;
        $item['currency'] = $this->currency;
        $item['componentname'] = $this->componentname;
        $item['area'] = $this->area;
        $item['description'] = $this->description;
        $item['imageurl'] = $this->imageurl;
        $item['canceluntil'] = $this->canceluntil;
        $item['serviceperiodstart'] = $this->serviceperiodstart;
        $item['serviceperiodend'] = $this->serviceperiodend;
        $item['taxcategory'] = $this->taxcategory;
        $item['nodelete'] = $this->nodelete;
        $item['costcenter'] = $this->costcenter;
        $item['installment'] = $this->installment;
        $item['linkeditem'] = $this->linkeditem;
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
     * Returns the tax category
     *
     * @return string|null the tax category for this item
     */
    public function tax_category(): ?string {
        return $this->taxcategory;
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
     * Returns imageurl.
     *
     * @return string|null
     */
    public function imageurl(): ?string {
        return $this->imageurl;
    }

    /**
     *  Returns itemkey
     *
     * @return string|null
     */
    public function itemkey(): ?string {
        return $this->componentname . '-' . $this->area . '-' . $this->itemid;
    }
}
