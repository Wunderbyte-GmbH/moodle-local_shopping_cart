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

namespace local_shopping_cart\local;

use core_component;
use local_shopping_cart\interfaces\interface_transaction_complete;

/**
 * Knows whether a payment of a user is still on its way at a payment provider.
 *
 * A gateway that sends the user to a provider writes an open order before it does so. Whether that
 * payment is still running can only be told by asking the provider: an order the user walked away
 * from stays open forever, no gateway marks it as failed on its own. So this class asks - through
 * the gateway's transaction_complete, the same status check the checkout page runs - and only a
 * question that has just been asked and has not been resolved counts as a running payment.
 *
 * Gateways without that status check (e.g. stripe, paypal) cannot be asked, so their open orders
 * never hold a cart; otherwise the items of an abandoned checkout would never be released.
 *
 * The answer is only used to hold the automatic release of a reservation (expiration, ad hoc
 * task). A user removing an item from the cart by hand is never blocked.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openorders {
    /**
     * Status values of an open order that are final: the provider has answered.
     *
     * 2 is a definitive failure, 3 a completed payment - the values transaction_complete of the
     * gateways writes. Every other value means that the gateway is still waiting.
     *
     * @var int[]
     */
    private const TERMINAL_STATUS = [2, 3];

    /**
     * How long after its creation an open order is asked about at all, in seconds.
     *
     * This is the window the checkout page uses for the same question (check_for_ongoing_payment);
     * an older order is left to the provider's own notification.
     *
     * @var int
     */
    private const CHECK_WINDOW = 24 * 60 * 60;

    /**
     * The open order tables of the installed gateways, resolved once per request.
     *
     * @var array<string, string>|null gateway name => table name
     */
    private static $tables = null;

    /**
     * Test seam: replaces the call to the gateway's transaction_complete.
     *
     * Receives (string $gateway, \stdClass $openorder) and returns nothing; it is expected to write
     * the resolved status to the open order like the gateway would. Null means the real call.
     *
     * @var callable|null
     */
    private static $statuscheck = null;

    /**
     * Returns the open order tables of the installed gateways that carry the columns the check needs.
     *
     * Whether the gateway can be asked is decided per order in self::can_ask(): loading the gateway
     * classes here would pull in every gateway's external API on every cart read.
     *
     * @return array<string, string> gateway name => open order table
     */
    public static function get_tables(): array {
        global $DB;

        if (self::$tables !== null) {
            return self::$tables;
        }

        $dbman = $DB->get_manager();
        $tables = [];

        foreach (array_keys(core_component::get_plugin_list('paygw')) as $gateway) {
            $tablename = 'paygw_' . $gateway . '_openorders';
            if (!$dbman->table_exists($tablename)) {
                continue;
            }
            $columns = $DB->get_columns($tablename);
            foreach (['userid', 'status', 'itemid', 'tid', 'timecreated'] as $column) {
                if (!isset($columns[$column])) {
                    continue 2;
                }
            }
            $tables[$gateway] = $tablename;
        }

        self::$tables = $tables;

        return self::$tables;
    }

    /**
     * Tells whether a gateway offers the status check the hold relies on.
     *
     * A gateway qualifies when its transaction_complete implements the shopping cart's interface
     * (payone, mpay24, qenta, saferpay, unigraz, payunity ...). Core paypal has a transaction_complete
     * without it, stripe has none: they cannot be asked.
     *
     * @param string $gateway
     * @return bool
     */
    public static function can_ask(string $gateway): bool {
        $classname = 'paygw_' . $gateway . '\external\transaction_complete';
        return class_exists($classname) && is_subclass_of($classname, interface_transaction_complete::class);
    }

    /**
     * Forgets the resolved table list, needed when gateways are installed during a run.
     *
     * @return void
     */
    public static function reset_tables() {
        self::$tables = null;
    }

    /**
     * Test seam, see self::$statuscheck. Null restores the real gateway call.
     *
     * @param callable|null $statuscheck
     * @return void
     */
    public static function set_status_check_for_testing(?callable $statuscheck) {
        self::$statuscheck = $statuscheck;
    }

    /**
     * Asks the providers whether a payment of this user is still running right now.
     *
     * Every open order of the user that belongs to a checkout still waiting for payment, was created
     * within the check window and whose gateway can be asked is checked live. The gateway writes the
     * answer to the open order: a completed or definitively failed order is resolved and does not
     * hold anything. Only an order that is still open after the question counts.
     *
     * @param int $userid
     * @return bool true when a payment is running and the reservation must be kept
     */
    public static function is_payment_ongoing(int $userid): bool {
        global $DB;

        if (empty($userid)) {
            return false;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(self::TERMINAL_STATUS, SQL_PARAMS_NAMED, 'status', false);

        foreach (self::get_tables() as $gateway => $tablename) {
            // The open order names the cart by its identifier (itemid). Only a checkout that is
            // itself still waiting for its payment can be running.
            $sql = "SELECT oo.*
                      FROM {" . $tablename . "} oo
                     WHERE oo.userid = :userid
                       AND oo.status $insql
                       AND oo.timecreated > :since
                       AND EXISTS (
                               SELECT 1
                                 FROM {local_shopping_cart_history} sch
                                WHERE sch.identifier = oo.itemid
                                  AND sch.userid = oo.userid
                                  AND sch.paymentstatus IN (0, 1)
                           )";
            $params = array_merge($inparams, [
                'userid' => $userid,
                'since' => time() - self::CHECK_WINDOW,
            ]);

            $openorders = $DB->get_records_sql($sql, $params);
            if (!$openorders || !self::can_ask($gateway)) {
                // Nobody can tell whether the user is still at the provider: not a running payment.
                continue;
            }

            foreach ($openorders as $openorder) {
                self::ask_provider($gateway, $openorder, $userid);

                $status = (int) $DB->get_field($tablename, 'status', ['id' => $openorder->id]);
                if (!in_array($status, self::TERMINAL_STATUS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Runs the gateway's status check for one open order.
     *
     * The gateway writes the outcome to the open order itself; a failure to reach the provider
     * leaves it open, which is the safe direction for a reservation.
     *
     * @param string $gateway
     * @param \stdClass $openorder
     * @param int $userid
     * @return void
     */
    private static function ask_provider(string $gateway, \stdClass $openorder, int $userid) {
        if (self::$statuscheck !== null) {
            call_user_func(self::$statuscheck, $gateway, $openorder);
            return;
        }

        $classname = 'paygw_' . $gateway . '\external\transaction_complete';
        try {
            $classname::execute(
                'local_shopping_cart',
                '',
                (int) $openorder->itemid,
                (string) $openorder->tid,
                '',
                '',
                true,
                '',
                $userid
            );
        } catch (\Throwable $e) {
            debugging('Status check of gateway ' . $gateway . ' failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
