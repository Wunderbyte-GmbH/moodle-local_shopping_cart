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
 * The payment confirmed event.
 *
 * @package    local_shopping_cart
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\event;

/**
 * The payment confirmed event.
 *
 * @package    local_shopping_cart
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_confirmed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localized general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('paymentconfirmed', 'local_shopping_cart');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $this->data['identifier'] = $this->data['other']['identifier'];
        return get_string('paymentconfirmed_desc', 'local_shopping_cart', $this->data);
    }
}
