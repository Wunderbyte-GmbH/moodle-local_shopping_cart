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

class delete_item_from_cart extends external_api {

    /**
     * Describes the paramters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array(
            'component'  => new external_value(PARAM_RAW, 'component name like mod_booking', VALUE_DEFAULT, ''),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, '0'),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, '0'),
            )
        );
    }

    /**
     * Excecute this websrvice.
     *
     * @param string $component
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function execute(string $component, int $itemid, int $userid) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'itemid' => $itemid,
            'userid' => $userid
        ]);

        require_login();

        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        // This treats the cache side.
        if (shopping_cart::delete_item_from_cart($params['component'], $params['itemid'], $params['userid'])) {
            return ['success' => 1];
        }
        return ['success' => 0];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(array(
            'success'  => new external_value(PARAM_INT, 'id'),
        ));
    }
}
