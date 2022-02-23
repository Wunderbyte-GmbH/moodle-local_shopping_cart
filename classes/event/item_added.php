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
 * Item added event.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\event;

/**
 * Message deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int itemid: the id of the item.
 * }
 *
 * @package    local_shopping_cart
 * @copyright  2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_added extends \core\event\base {

    /**
     * Create event using id
     *
     * @param int $userid
     * @param array $item
     *
     * @return item_added
     */
    public static function create_from_ids(int $userid, array $item) : item_added {
        // We set the userid to the user who deleted the message, nothing to do
        // with whether or not they sent or received the message.
        $event = self::create(array(
            'userid' => $userid,
            'context' => \context_system::instance(),
            'other' => $item,
        ));

        return $event;
    }

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
        return "get_string('item_added', 'shopping_cart')";
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $str = "bla";
        return $str;
    }
}
