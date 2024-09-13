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
 * Event observers used in forum.
 *
 * @package    local_shopping_cart
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\interfaces\invoice;
use local_shopping_cart\invoice\invoicenumber;

/**
 * Event observer for local_shopping_cart.
 */
class observer {

    /**
     * Triggered via payment_error event from any payment provider
     * If we receive a payment error, check for the order id in our shopping cart history.
     * And set it to error, if it was pending.
     *
     * @param \core\event\base $event
     * @return string
     */
    public static function payment_error(\core\event\base $event): string {
        $data = $event->get_data();

        // First check, to make it fast.
        if ($data['target'] !== 'payment') {
            return '';
        }

        // Next check.
        $stringarray = explode("\\", $data['eventname']);
        if (end($stringarray) !== 'payment_error') {
            return '';
        }

        // Next, we look in the shopping cart history if there is a pending payment.
        if (empty($data['other']['itemid'])) {
            return '';
        }

        $itemid = $data['other']['itemid'];

        // Bugfix: If we call this function, pending items are set to aborted which leads to
        // missing items in deliver_order function of service_provider.php.
        // So we comment this out for now. However, we might need a better fix...

        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /* shopping_cart_history::error_occured_for_identifier($itemid, $data['userid']); */

        return 'registered_payment_error';
    }

    /**
     * Observer triggers the creation of the invoice upon successfull payment and checkout.
     *
     * @param \core\event\base $event
     */
    public static function payment_confirmed(\core\event\base $event): void {

        global $DB;

        $invoiceprovidername = get_config('local_shopping_cart', 'invoicingplatform');
        $invoiceproviderclass = "local_shopping_cart\\invoice\\" . $invoiceprovidername . "_invoice";

        if ($invoiceprovidername == 'noinvoice') {
            return;
        }

        if ($invoiceprovidername == 'saveinvoicenumber') {

            invoicenumber::save_invoice_number($event);
            return;
        }

        if (class_exists($invoiceproviderclass)) {
            $rc = new \ReflectionClass($invoiceproviderclass);
            if (!$rc->implementsInterface(invoice::class)) {
                throw new \coding_exception("$invoiceprovidername does implement the right interface");
            }
        } else {
            throw new \coding_exception("$invoiceproviderclass was not found by class_exists.");
        }
        $invoiceproviderclass::create_invoice_task($event);
    }
}
