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
 * Adhoc task to delete temporary guest checkout users.
 *
 * @package local_shopping_cart
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\task;

use core\task\adhoc_task;
use local_shopping_cart\local\guestcheckout;

/**
 * Deletes a guest checkout user if they have not purchased anything.
 *
 * The task is queued when a guest user is first created and runs after
 * {@see guestcheckout::GUEST_USER_TTL} seconds.  If the user has already been
 * converted into a real account the tracking record in
 * `local_shopping_cart_guestusers` will be gone and the task exits silently.
 *
 * @package local_shopping_cart
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_guest_user_task extends adhoc_task {
    /**
     * Returns the localised task name shown in the admin task list.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:deleteguestuser', 'local_shopping_cart');
    }

    /**
     * Executes the task: deletes the guest user if still unconverted.
     *
     * @return void
     */
    public function execute() {
        $data   = $this->get_custom_data();
        $userid = (int) ($data->userid ?? 0);

        if ($userid < 1) {
            return;
        }

        if (!guestcheckout::is_guest_checkout_user($userid)) {
            // Already converted to a real user – nothing to do.
            mtrace("delete_guest_user_task: user $userid has already been converted, skipping.");
            return;
        }

        mtrace("delete_guest_user_task: deleting unconverted guest user $userid.");
        guestcheckout::delete_guest_user($userid);
    }
}
