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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use core\task\adhoc_task;
use core\task\manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Class cartstore
 *
 * @author Georg Maißer
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class taskmanager extends manager {
    /**
     * Schedule a new task, or reschedule an existing adhoc task which has matching data.
     *
     * Only a task matching the same user, classname, component, and customdata will be rescheduled.
     * If these values do not match exactly then a new task is scheduled.
     *
     * @param \core\task\adhoc_task $task - The new adhoc task information to store.
     * @since Moodle 3.7
     * @return string
     */
    public static function reschedule_or_queue_adhoc_task_to_later(adhoc_task $task): string {
        global $DB;

        $nextruntime = 0;
        if ($existingrecord = self::get_queued_adhoc_task_record($task)) {
            // Only update the next run time if it is explicitly set on the task.
            $nextruntime = $task->get_next_run_time();
            if ($nextruntime && ($existingrecord->nextruntime < $nextruntime)) {
                $DB->set_field('task_adhoc', 'nextruntime', $nextruntime, ['id' => $existingrecord->id]);
            } else if ($nextruntime && ($existingrecord->nextruntime > $nextruntime)) {
                $nextruntime = $existingrecord->nextruntime;
            }
        } else {
            // There is nothing queued yet. Just queue as normal.
            self::queue_adhoc_task($task);
        }
        return $nextruntime;
    }
}
