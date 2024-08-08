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
 * Adhoc Task to remove expired items from the shopping cart.
 *
 * @package    local_shopping_cart
 * @copyright  2023 David Bogner <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\task;

use context_system;
use local_shopping_cart\interfaces\invoice;
use local_shopping_cart\invoice\erpnext_invoice;
use local_shopping_cart\shopping_cart;

/**
 * Adhoc Task to remove expired items from the shopping cart.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_invoice_task extends \core\task\adhoc_task {

    /**
     * Get name of the component.
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
        $anyexception = false;
        $taskdata = $this->get_custom_data();
        $userid = $this->get_userid();
        $classname = $taskdata->classname;
        $success = false;
        mtrace('Try to created invoice for user ' . $userid . ' with identifier ' . $taskdata->identifier);
        try {
            $invoiceprovider = new $classname();
            $success = $invoiceprovider->create_invoice($taskdata->identifier);
            mtrace('Invoice creation success: ' . $success);

        } catch (\Throwable $e) {
            mtrace_exception($e);
            $anyexception = $e;
        }
        if ($anyexception) {
            // If there was any error, ensure the task fails.
            throw $anyexception;
        }
        if (!$success) {
            throw new \moodle_exception('serverconnection', 'local_shopping_cart', '', null,
                    $invoiceprovider->errormessage);
        }
        mtrace('Invoice created for user ' . $userid . ' with identifier ' . $taskdata->identifier);
    }
}
