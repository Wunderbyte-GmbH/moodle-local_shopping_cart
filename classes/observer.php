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
use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\local\guestcheckout;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;

/**
 * Event observer for local_shopping_cart.
 */
class observer {
    /**
     * Triggered when a user logs in.
     *
     * If the login originated from guest checkout, migrate the guest cart and
     * checkout cache to the logged-in account and remember the step to resume.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function user_loggedin(\core\event\base $event): void {
        global $SESSION;

        if (!get_config('local_shopping_cart', 'guestoncheckout')) {
            return;
        }

        $context = $SESSION->local_shopping_cart_guest_login_context ?? null;
        if (empty($context) || empty($context['guestuserid'])) {
            return;
        }

        $guestuserid = (int)$context['guestuserid'];
        $targetuserid = (int)$event->userid;

        unset($SESSION->local_shopping_cart_guest_login_context);

        if ($guestuserid < 1 || $targetuserid < 1 || $guestuserid === $targetuserid) {
            return;
        }

        if (!guestcheckout::is_guest_checkout_user($guestuserid)) {
            return;
        }

        $resumestep = guestcheckout::migrate_guest_checkout_to_user($guestuserid, $targetuserid);

        $SESSION->local_shopping_cart_checkout_resume = [
            'userid' => $targetuserid,
            'step' => max(0, (int)$resumestep),
            'timecreated' => time(),
        ];

        guestcheckout::delete_guest_user($guestuserid);
    }

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

    /**
     * Triggered when a checkout is completed successfully.
     *
     * If the purchasing user is a guest checkout user (created automatically when
     * they first added an item to the cart), this observer converts that temporary
     * account into a permanent Moodle user using the registration data they entered
     * in the addresses/registration checkout step.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function checkout_completed(\core\event\base $event): void {
        if (!get_config('local_shopping_cart', 'guestoncheckout')) {
            return;
        }

        $userid = (int) $event->relateduserid;
        if ($userid < 1) {
            return;
        }

        if (!guestcheckout::is_guest_checkout_user($userid)) {
            return;
        }

        // Retrieve the cached registration data saved by the addresses checkout step.
        $cachedata = checkout_manager::get_cache($userid);
        $stepdata  = $cachedata['steps']['addresses']['data'] ?? [];

        $firstname = trim($stepdata['guest_firstname'] ?? '');
        $lastname  = trim($stepdata['guest_lastname'] ?? '');
        $email     = trim($stepdata['guest_email'] ?? '');

        if (empty($firstname) || empty($lastname) || empty($email)) {
            // Registration data is missing – cannot convert. Leave the guest user
            // in place; the 24-hour cleanup task will remove it eventually.
            return;
        }

        guestcheckout::convert_guest_to_real_user($userid, $firstname, $lastname, $email);
    }

    /**
     * Triggered for any provider event named "subscription_cancelled".
     * Cancels each purchased item in shopping cart history and notifies the owning component.
     *
     * Expected payload in $event->other:
     * - component (string)
     * - itemid (int) as cart identifier
     * - userid (int) optional, falls back to $event->userid
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function subscription_cancelled(\core\event\base $event): void {
        if (empty(get_config('local_shopping_cart', 'enablesubscriptioncancelobserver'))) {
            return;
        }

        $data = $event->get_data();
        $stringarray = explode("\\", $data['eventname'] ?? '');
        if (end($stringarray) !== 'subscription_cancelled') {
            return;
        }

        $other = $data['other'] ?? [];

        if (
            empty($other['component']) ||
            $other['component'] !== 'local_shopping_cart' ||
            empty($other['itemid'])
        ) {
            return;
        }

        $identifier = (int) $other['itemid'];
        $userid     = (int) ($other['userid'] ?? ($data['userid'] ?? 0));
        if (empty($userid)) {
            return;
        }

        // Get all items bought under this cart identifier.
        $records = shopping_cart_history::return_data_via_identifier($identifier);
        if (empty($records)) {
            return;
        }

        foreach ($records as $record) {
            // Mark as cancelled first; if this returns no success, skip callback to stay idempotent.
            $result = shopping_cart_history::cancel_purchase(
                $record->itemid,
                $userid,
                $record->componentname,
                $record->area,
                $record->id  // historyid
            );
            if (empty($result[0])) {
                continue;
            }

            // Notify the component (e.g. mod_booking) so it can unenrol / revoke access.
            shopping_cart::cancel_purchase_for_component(
                $record->componentname,
                $record->area,
                $record->itemid,
                $userid
            );
        }
    }
}
