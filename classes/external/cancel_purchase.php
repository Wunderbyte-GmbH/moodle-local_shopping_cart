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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class cancel_purchase extends external_api {

    /**
     * Describes the paramters for add_item_to_cart.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array(
            'componentname'  => new external_value(PARAM_RAW, 'componentname', VALUE_DEFAULT, ''),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, 0),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
            'historyid'  => new external_value(PARAM_INT, 'id of entry in shopping_cart_history db', VALUE_DEFAULT, 0),
            'credit' => new external_value(PARAM_FLOAT, 'Custom credit value', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function execute(string $componentname, int $itemid, int $userid, int $historyid, float $credit): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'componentname' => $componentname,
            'itemid' => $itemid,
            'userid' => $userid,
            'historyid' => $historyid,
            'credit' => $credit
        ]);

        return shopping_cart::cancel_purchase($params['itemid'], $params['userid'], $params['componentname'],
            $params['historyid'],  $params['credit']);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_INT, 'Success value 0 or 1'),
            'error' => new external_value(PARAM_RAW, 'Error message if something went wrong'),
            'credit' => new external_value(PARAM_FLOAT, 'New credit value')
            )
        );
    }
}
