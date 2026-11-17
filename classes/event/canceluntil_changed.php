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
 * The cancellation deadline of an existing purchase was changed.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\event;

use context_system;
use core\event\base;

/**
 * The cancellation deadline of an existing purchase was changed.
 *
 * Logged once per purchase (row of local_shopping_cart_history), because the deadline that is
 * replaced can differ from buyer to buyer. The user who changed it is the userid of the event,
 * the buyer is the relateduserid. Payload in $event->other: historyid, itemid, component, area,
 * oldcanceluntil, newcanceluntil (timestamps, 0 = no deadline).
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class canceluntil_changed extends base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_shopping_cart_history';
    }

    /**
     * Log the change of one purchase.
     *
     * @param \stdClass $historyitem the purchase as it was before the change
     * @param int $newcanceluntil the new deadline, 0 for no deadline
     * @return void
     */
    public static function log(\stdClass $historyitem, int $newcanceluntil): void {
        $event = self::create([
            'context' => context_system::instance(),
            'objectid' => $historyitem->id,
            'relateduserid' => $historyitem->userid,
            'other' => [
                'historyid' => (int)$historyitem->id,
                'itemid' => (int)$historyitem->itemid,
                'component' => $historyitem->componentname,
                'area' => $historyitem->area,
                'oldcanceluntil' => (int)$historyitem->canceluntil,
                'newcanceluntil' => $newcanceluntil,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('canceluntilchanged', 'local_shopping_cart');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $format = get_string('strftimedatetime', 'langconfig');
        $nodeadline = get_string('canceluntilnodeadline', 'local_shopping_cart');
        $old = (int)($this->other['oldcanceluntil'] ?? 0);
        $new = (int)($this->other['newcanceluntil'] ?? 0);

        $a = (object)[
            'userid' => $this->userid,
            'relateduserid' => $this->relateduserid,
            'historyid' => $this->other['historyid'] ?? 0,
            'itemid' => $this->other['itemid'] ?? 0,
            'component' => $this->other['component'] ?? '',
            'area' => $this->other['area'] ?? '',
            'old' => empty($old) ? $nodeadline : userdate($old, $format),
            'new' => empty($new) ? $nodeadline : userdate($new, $format),
        ];
        return get_string('canceluntilchanged_desc', 'local_shopping_cart', $a);
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
