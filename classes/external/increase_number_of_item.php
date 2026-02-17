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
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * External Service for shopping cart.
 *
 * @package   local_shopping_cart
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class increase_number_of_item extends external_api {
    /**
     * Describes the parameters for add_item_to_cart.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component'  => new external_value(PARAM_COMPONENT, 'component', VALUE_DEFAULT, ''),
            'area'  => new external_value(PARAM_TEXT, 'area', VALUE_DEFAULT, ''),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, 0),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Increases the number of items.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function execute(string $component, string $area, int $itemid, int $userid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'userid' => $userid,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('nopermissions', 'core');
        }

        // Security: If modifying cart for another user, must be cashier.
        if ($params['userid'] != 0 && $params['userid'] != $USER->id) {
            if (!has_capability('local/shopping_cart:cashier', $context)) {
                throw new moodle_exception('nopermissions', 'core');
            }
        }

        $providerclass = shopping_cart::get_service_provider_classname($params['component']);
        $cartstore = cartstore::instance($params['userid']);
        $nritems = $cartstore->get_number_of_items_for_item($params['component'], $params['area'], $params['itemid']);
        // We increase them.
        $nritems++;
        $allowed = component_class_callback(
            $providerclass,
            'adjust_number_of_items',
            [$params['area'], $params['itemid'], $nritems, $params['userid']]
        );

        if ($allowed) {
            return $cartstore->increase_number_of_item($params['component'], $params['area'], $params['itemid']);
        }
        return ['success' => 0];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_INT, 'Successfully increased the number'),
            ]
        );
    }
}
