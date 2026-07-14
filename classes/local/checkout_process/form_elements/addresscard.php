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
 * Address-card radio element for the checkout address step.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/radio.php');

/**
 * A radio element rendered as the existing shopping-cart address card.
 *
 * Keeps full mform semantics (submission value, set_data/default selection,
 * grouping by element name) while emitting the same markup as the legacy
 * address.mustache card list, so the existing CSS and behat selectors
 * (label.sc-address-item, input[name="selectedaddress_*"]) keep working.
 *
 * Registered under the type name 'shoppingcartaddresscard' by
 * addresses_form (custom elements cannot live in namespaced classes,
 * hence the global classname).
 *
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_shopping_cart_addresscard_element extends MoodleQuickForm_radio {
    /**
     * Address record used to render the card content.
     * @var \stdClass|null
     */
    protected $addressrecord;

    /**
     * Constructor.
     *
     * @param string|null $elementname e.g. selectedaddress_billing
     * @param string|null $elementlabel unused (the card is its own label)
     * @param string|null $text unused
     * @param mixed $value the address id
     * @param mixed $addressrecord the address record (stdClass with address, zip, city, country, name, company)
     */
    public function __construct(
        $elementname = null,
        $elementlabel = null,
        $text = null,
        $value = null,
        $addressrecord = null
    ) {
        parent::__construct($elementname, '', '', $value, ['class' => 'sc-address-card-input']);
        // Own type name: there is no core_form/element-* template for it, so
        // the renderer falls back to toHtml() and our card markup is used.
        $this->_type = 'shoppingcartaddresscard';
        $this->addressrecord = $addressrecord;
    }

    /**
     * Render the card: a label wrapping the radio input and the one-line address.
     *
     * @return string
     */
    public function toHtml() {
        global $OUTPUT;

        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }

        $id = $this->getAttribute('id') ?? ($this->getName() . 'card' . $this->getValue());
        $singleline = $OUTPUT->render_from_template(
            'local_shopping_cart/address_singleline',
            (array)($this->addressrecord ?? [])
        );

        $input = \html_writer::empty_tag('input', [
            'type' => 'radio',
            'name' => $this->getName(),
            'value' => $this->getValue(),
            'id' => $id,
            'checked' => $this->getChecked() ? 'checked' : null,
        ]);

        return \html_writer::tag(
            'label',
            $input . \html_writer::tag('span', $singleline, ['class' => 'local-shopping_cart-savedaddress']),
            ['class' => 'sc-address-item mb-2', 'for' => $id]
        );
    }
}
