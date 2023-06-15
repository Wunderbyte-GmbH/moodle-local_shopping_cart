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
 * This class contains the webservice for manual rebooking of a user from cashier view.
 *
 * @package   local_shopping_cart
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author    Bernhard Fischer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_shopping_cart\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\event\payment_rebooked;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * This class contains the webservice for manual rebooking of a user from cashier view.
 *
 * @package   local_shopping_cart
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author    Bernhard Fischer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manual_rebooking extends external_api {

    /**
     * Describes the paramters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array(
            'userid'  => new external_value(PARAM_INT, 'userid'),
            'identifier' => new external_value(PARAM_INT, 'identifier'),
            'orderid' => new external_value(PARAM_RAW, 'orderid'),
        ));
    }

    /**
     * Excecute this webservice.
     * @param int $userid
     * @param int $identifier
     * @param string $orderid
     * @return array $status 1 if successful (0 if not), $error if there was an error, $userid, $identifier
     */
    public static function execute(int $userid, int $identifier, string $orderid): array {

        global $USER;

        require_login();
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'identifier' => $identifier,
            'orderid' => $orderid,
        ]);

        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashiermanualrebook', $context)) {
            return [0, get_string('error:capabilitymissing', 'local_shopping_cart'), $userid, $identifier, $orderid];
        }

        // Trigger manual rebook event, so we can react on it within other plugins.
        $event = payment_rebooked::create([
            'context' => context_system::instance(),
            'userid' => $USER->id, // The cashier.
            'relateduserid' => $userid, // The user for whom the rebooking was done.
            'other' => [
                'userid' => $params['userid'], // The user for whom the rebooking was done.
                'identifier' => $params['identifier'],
                'orderid' => $params['orderid'],
                'usermodified' => $USER->id, // The cashier.
            ],
        ]);

        $event->trigger();

        return [1, '', $userid, $identifier, $orderid];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, '1 if successful, 0 if not.'),
                'error' => new external_value(PARAM_RAW, 'Error message.'),
                'userid' => new external_value(PARAM_INT, 'user id'),
                'identifier' => new external_value(PARAM_INT, 'identifier of the transaction (this is NOT the order id)'),
                'orderid' => new external_value(PARAM_RAW, 'order id'),
            )
        );
    }
}
