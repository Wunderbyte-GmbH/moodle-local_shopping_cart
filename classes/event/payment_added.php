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
 * The payment_added event.
 *
 * @package     local_shopping_cart
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_shopping_cart\event;

/**
 * The payment_added event.
 *
 * @package     local_shopping_cart
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Georg Maißer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_added extends \core\event\base {

    /**
     * Init vars.
     *
     * @return mixed
     *
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_shopping_cart_history';
    }

    /**
     * Gets name.
     *
     * @return mixed
     *
     */
    public static function get_name() {
        return get_string('payment_added', 'local_shopping_cart');
    }

    /**
     * Gets description.
     *
     * @return mixed
     *
     */
    public function get_description() {

        $data = (object)[
            'userid' => $this->userid,
            'relateduserid' => $this->relateduserid,
            'itemid' => $this->other['itemid'],
            'component' => $this->other['component'],
            'identifier' => $this->other['identifier'],
        ];

        return get_string('payment_added_log', 'local_shopping_cart', $data);
    }

    /**
     * Gets url.
     *
     * @return mixed
     *
     */
    public function get_url() {
        return new \moodle_url('/local/shopping_cart/checkout.php');
    }
}
