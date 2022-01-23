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
namespace local_shopping_cart\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * Adhoc Task to remove expired items from the shopping cart.
 */
class delete_item_task extends \core\task\adhoc_task
{

    /**
     * Get name of Module.
     *
     * @return \lang_string|string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('modulename', 'local_shopping_cart');
    }

    /**
     * Execution function.
     *
     * {@inheritdoc}
     * @throws \coding_exception
     * @throws \dml_exception
     * @see \core\task\task_base::execute()
     */
    public function execute() {

        $taskdata = $this->get_custom_data();

        // Call the delete item from cart function.
        // This triggers the callback to the module where the item came from.

    }
}
