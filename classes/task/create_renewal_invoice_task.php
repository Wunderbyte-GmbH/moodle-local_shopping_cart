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

use core\task\adhoc_task;
use core\task\manager;
use core_user;
use local_shopping_cart\invoice\erpnext_invoice;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;
use local_shopping_cart\local\subscriptions\renewal_handler;
use local_shopping_cart\local\subscriptions\renewal_invoice_builder;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Ad-hoc task that issues the invoice for a recorded subscription renewal at the invoicing platform.
 *
 * Transient failures (platform unreachable) throw so the queue retries with backoff. Permanent
 * problems (unknown item, no billing address, amounts that do not reconcile) are stored on the
 * renewal row and do not retry.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_renewal_invoice_task extends adhoc_task {
    /**
     * Queue invoicing for a renewal when an invoicing platform is configured.
     *
     * @param stdClass $renewal row of local_shopping_cart_renewals
     * @return bool true when a task was queued
     */
    public static function queue(stdClass $renewal): bool {
        global $DB;
        if (get_config('local_shopping_cart', 'invoicingplatform') !== 'erpnext') {
            return false;
        }
        $renewal->invoicestatus = renewal_handler::INVOICE_PENDING;
        $renewal->timemodified = time();
        $DB->update_record('local_shopping_cart_renewals', $renewal);

        $task = new self();
        $task->set_userid((int) $renewal->userid);
        $task->set_custom_data((object) ['renewalid' => (int) $renewal->id]);
        $task->set_next_run_time(time());
        manager::reschedule_or_queue_adhoc_task($task);
        return true;
    }

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:createrenewalinvoice', 'local_shopping_cart');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $renewal = $DB->get_record('local_shopping_cart_renewals', ['id' => (int) $data->renewalid]);
        if (!$renewal) {
            mtrace('Renewal ' . (int) $data->renewalid . ' not found, nothing to invoice.');
            return;
        }
        if (!empty($renewal->erpinvoiceid)) {
            mtrace('Renewal ' . $renewal->id . ' already invoiced as ' . $renewal->erpinvoiceid);
            return;
        }
        if (get_config('local_shopping_cart', 'invoicingplatform') !== 'erpnext') {
            return;
        }

        $ledgerrows = $DB->get_records('local_shopping_cart_ledger', [
            'identifier' => (int) $renewal->identifier,
            'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_SUBSCRIPTION_RENEWAL,
            'annotation' => $renewal->externalinvoiceid,
        ], 'id ASC');
        if (empty($ledgerrows)) {
            $this->set_status($renewal, renewal_handler::INVOICE_FAILED);
            mtrace('Renewal ' . $renewal->id . ' has no ledger rows, invoice not created.');
            return;
        }

        $user = core_user::get_user((int) $renewal->userid);
        $billing = $this->billing_address_of($renewal, $ledgerrows);
        if (!$user || !$billing) {
            $this->set_status($renewal, renewal_handler::INVOICE_FAILED);
            mtrace('Renewal ' . $renewal->id . ': no user or billing address, invoice not created.');
            return;
        }

        $invoice = new erpnext_invoice();

        // An unknown item is a configuration problem that no retry can fix.
        foreach ($ledgerrows as $row) {
            if (!$invoice->item_exists((string) $row->itemname)) {
                $this->set_status($renewal, renewal_handler::INVOICE_FAILED);
                mtrace('Renewal ' . $renewal->id . ': item "' . $row->itemname . '" does not exist at the invoicing platform.');
                return;
            }
        }

        $dto = renewal_invoice_builder::build($renewal, array_values($ledgerrows), $user, $billing);
        $success = $invoice->create_invoice_from_data($dto);

        if ($success) {
            $renewal->erpinvoiceid = $invoice->invoiceid;
            $this->set_status($renewal, renewal_handler::INVOICE_CREATED);
            mtrace('Renewal ' . $renewal->id . ' invoiced as ' . $invoice->invoiceid);
            return;
        }
        if (!empty($invoice->reconciliationfailed)) {
            $this->set_status($renewal, renewal_handler::INVOICE_HELD);
            mtrace('Renewal ' . $renewal->id . ' held: ' . $invoice->errormessage);
            return;
        }
        // Anything else is treated as transient: keep pending and let the queue retry.
        throw new \moodle_exception('serverconnection', 'local_shopping_cart', '', null, $invoice->errormessage);
    }

    /**
     * The billing address of the original purchase, or the user's first address.
     *
     * @param stdClass $renewal
     * @param stdClass[] $ledgerrows
     * @return stdClass|null
     */
    private function billing_address_of(stdClass $renewal, array $ledgerrows): ?stdClass {
        $addressid = 0;
        foreach ($ledgerrows as $row) {
            if (!empty($row->address_billing)) {
                $addressid = (int) $row->address_billing;
                break;
            }
        }
        if (!$addressid) {
            $addresses = address_operations::get_all_user_addresses((int) $renewal->userid);
            if (empty($addresses)) {
                return null;
            }
            $addressid = (int) array_key_first($addresses);
        }
        $address = address_operations::get_specific_user_address($addressid);
        return $address ? (object) $address : null;
    }

    /**
     * Persist the invoice status on the renewal row.
     *
     * @param stdClass $renewal
     * @param string $status
     * @return void
     */
    private function set_status(stdClass $renewal, string $status): void {
        global $DB;
        $renewal->invoicestatus = $status;
        $renewal->timemodified = time();
        $DB->update_record('local_shopping_cart_renewals', $renewal);
    }
}
