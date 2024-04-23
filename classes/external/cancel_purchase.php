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
class cancel_purchase extends external_api {

    /**
     * Describes the parameters for cancel_purchase.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'componentname'  => new external_value(PARAM_COMPONENT, 'componentname', VALUE_REQUIRED),
            'area'  => new external_value(PARAM_TEXT, 'area', VALUE_REQUIRED),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_REQUIRED),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'historyid'  => new external_value(PARAM_INT, 'id of entry in shopping_cart_history db', VALUE_REQUIRED),
            'credit' => new external_value(PARAM_FLOAT, 'Custom credit value', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     *
     * @param string $componentname
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @param int $historyid
     * @param float $credit
     * @return array
     */
    public static function execute(
            string $componentname,
            string $area,
            int $itemid,
            int $userid,
            int $historyid,
            float $credit): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'componentname' => $componentname,
            'area' => $area,
            'itemid' => $itemid,
            'userid' => $userid,
            'historyid' => $historyid,
            'credit' => $credit,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        return shopping_cart::cancel_purchase($params['itemid'], $params['area'], $params['userid'], $params['componentname'],
            $params['historyid'],  $params['credit']);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_INT, 'Success value 0 or 1'),
            'error' => new external_value(PARAM_RAW, 'Error message if something went wrong'),
            'credit' => new external_value(PARAM_FLOAT, 'New credit value'),
            ]
        );
    }
}
