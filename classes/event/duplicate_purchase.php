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
 * Duplicate purchase event.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\event;

/**
 * Duplicate purchase class.
 *
 * Triggered when an order was paid for an item the user already owns, so the component delivered
 * nothing. The amount is given back as credit; this event lets other plugins - for example a
 * booking rule - inform the office about it.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int itemid: the id of the item.
 *      - string component: the component the item belongs to.
 *      - string area: the area of the item within that component.
 *      - int identifier: the identifier of the order the item was paid with.
 *      - float price: the amount that was paid for it and given back as credit.
 * }
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicate_purchase extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('duplicate_purchase', 'local_shopping_cart');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {

        $this->data['itemid'] = $this->data['other']['itemid'];
        $this->data['component'] = $this->data['other']['component'];
        $this->data['identifier'] = $this->data['other']['identifier'] ?? '';

        return get_string('userduplicatepurchase', 'local_shopping_cart', $this->data);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
