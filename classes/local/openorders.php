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

/**
 * Knows whether a payment of a user is still on its way at a payment provider.
 *
 * Every gateway that sends the user to a provider writes an open order before it does so and
 * updates its status when the provider answers. As long as that answer has not arrived, the seat
 * must stay reserved: the money may still come, and the user cannot influence it any more.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openorders {
    /**
     * Status values of an open order that are final: the provider has answered.
     *
     * 2 is a definitive failure, 3 a completed payment. Every other value means that the gateway is
     * still waiting, so the reservation is kept. Gateways that need a different mapping bring it
     * with their own adapter.
     *
     * @var int[]
     */
    private const TERMINAL_STATUS = [2, 3];

    /**
     * The open order tables of the installed gateways, resolved once per request.
     *
     * @var string[]|null
     */
    private static $tables = null;

    /**
     * Returns the open order tables of all installed payment gateways.
     *
     * @return string[]
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
            if (!isset($columns['userid']) || !isset($columns['status'])) {
                continue;
            }
            $tables[] = $tablename;
        }

        self::$tables = $tables;

        return self::$tables;
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
     * Tells whether a payment of this user is still waiting for an answer of the provider.
     *
     * @param int $userid
     * @param int|null $identifier the cart identifier, when only one order is of interest
     * @return bool
     */
    public static function has_pending_order(int $userid, ?int $identifier = null): bool {

        global $DB;

        if (empty($userid)) {
            return false;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(self::TERMINAL_STATUS, SQL_PARAMS_NAMED, 'status', false);

        foreach (self::get_tables() as $tablename) {
            $select = "userid = :userid AND status $insql";
            $params = array_merge(['userid' => $userid], $inparams);

            if ($identifier !== null) {
                $select .= ' AND itemid = :identifier';
                $params['identifier'] = $identifier;
            }

            if ($DB->record_exists_select($tablename, $select, $params)) {
                return true;
            }
        }

        return false;
    }
}
