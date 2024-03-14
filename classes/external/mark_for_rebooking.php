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
 * This class contains a list of webservice functions related to the Shopping Cart Module by Wunderbyte.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_shopping_cart\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for shopping cart.
 *
 * @package   local_shopping_cart
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mark_for_rebooking extends external_api {

    /**
     * Describes the parameters for mark_for_rebooking.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'historyid'  => new external_value(PARAM_INT, 'historyid', VALUE_DEFAULT, 0),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Webservice for shopping_cart_history class to mark history item for rebooking.
     *
     * @param int $historyid
     * @param int $userid
     * @return array
     */
    public static function execute(int $historyid, int $userid): array {
        global $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }

        $params = self::validate_parameters(self::execute_parameters(), [
            'historyid' => $historyid,
            'userid' => $userid,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        // Only a cashier can book for other users than herself.
        if (!has_capability('local/shopping_cart:cashier', $context)
            && $params['userid'] != $USER->id) {
            throw new moodle_exception('norighttobookforotherusers', 'local_shopping_cart');
        }

        return shopping_cart_history::toggle_mark_for_rebooking($params['historyid'], $params['userid']);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'marked' => new external_value(PARAM_INT, '1 if marked, 0 if not.', VALUE_DEFAULT, 0),
            ]
        );
    }
}
