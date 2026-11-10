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
 * Adhoc Task to create the invoice of a cart purchase at the invoicing platform.
 *
 * @package    local_shopping_cart
 * @copyright  2023 David Bogner <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\task;

use local_shopping_cart\event\invoice_creation_failed;

/**
 * Adhoc Task to create the invoice of a cart purchase at the invoicing platform.
 *
 * Failures throw so the ad-hoc queue retries with backoff; every failed attempt is logged as
 * invoice_creation_failed with the reason.
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
        return get_string('task:createinvoice', 'local_shopping_cart');
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
        $userid = (int) $this->get_userid();
        $identifier = (int) $taskdata->identifier;
        $classname = $taskdata->classname;
        mtrace('Try to create invoice for user ' . $userid . ' with identifier ' . $identifier);

        $invoiceprovider = null;
        $exception = null;
        try {
            $invoiceprovider = new $classname();
            // Safe to retry: an invoice created by an earlier attempt is reused, only missing steps are done.
            $success = $invoiceprovider->create_invoice($identifier);
            $error = (string) ($invoiceprovider->errormessage ?? '');
        } catch (\Throwable $e) {
            mtrace_exception($e);
            $exception = $e;
            $success = false;
            $error = $e->getMessage() . (!empty($e->debuginfo) ? ' ' . $e->debuginfo : '');
        }

        if ($success) {
            mtrace('Invoice ' . ($invoiceprovider->invoiceid ?? '') . ' created for user ' . $userid
                . ' with identifier ' . $identifier);
            return;
        }

        // The attempt counter is decreased after a failure, so 1 means no retry follows.
        $attemptsleft = max(0, $this->get_attempts_available() - 1);
        if ($error === '') {
            $error = 'Invoice creation failed without an error message, see the task log.';
        }
        invoice_creation_failed::log(
            $userid,
            $identifier,
            0,
            (string) ($invoiceprovider->invoiceid ?? ''),
            $error,
            $attemptsleft
        );
        if ($exception) {
            throw $exception;
        }
        throw new \moodle_exception('erpnextrequestfailed', 'local_shopping_cart', '', $error);
    }
}
