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
class get_quota_consumed extends external_api {

    /**
     * Describes the parameters for add_item_to_cart.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component'  => new external_value(PARAM_COMPONENT, 'component', VALUE_DEFAULT, ''),
            'area'  => new external_value(PARAM_ALPHA, 'area', VALUE_DEFAULT, ''),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, 0),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
            'historyid'  => new external_value(PARAM_INT, 'historyid', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     * @param int $historyid
     *
     * @return array
     */
    public static function execute(string $component, string $area, int $itemid, int $userid, int $historyid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'userid' => $userid,
            'historyid' => $historyid,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        // The transformation of the userid will be done in the add_item_to_cart function.

        return shopping_cart::get_quota_consumed(
            $params['component'],
            $params['area'],
            $params['itemid'],
            $params['userid'],
            $params['historyid']);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_INT, 'Success 1, no success 0', VALUE_DEFAULT, 0),
            'quota' => new external_value(PARAM_FLOAT, 'Item id', VALUE_DEFAULT, 0),
            'remainingvalue' => new external_value(PARAM_FLOAT, 'Item name', VALUE_DEFAULT, 0),
            'initialvalue' => new external_value(PARAM_FLOAT, 'Item price', VALUE_DEFAULT, 0),
            'currency' => new external_value(PARAM_ALPHA, 'Currency', VALUE_DEFAULT, ''),
            'cancelationfee' => new external_value(PARAM_FLOAT, 'Cancelaton fee', VALUE_DEFAULT, 0),
            ]
        );
    }
}
