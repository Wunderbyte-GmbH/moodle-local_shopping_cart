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

class delete_all_items_from_cart extends external_api {

    /**
     * Describes the paramters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(array(
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, '0'),
            )
        );
    }

    /**
     * Excecute this websrvice.
     *
     * @param int $userid
     *
     * @return array
     */
    public static function execute(int $userid) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid
        ]);

        require_login();

        $context = context_system::instance();

        if (!has_capability('local/shopping_cart:cashier', $context)) {
            $userid = $params['userid'] == 0 ? (int)$USER->id : $params['userid'];
        } else {
            $userid = (int)$USER->id;
        }

        shopping_cart::delete_all_items_from_cart($userid);
        return ["success" => 1];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(array(
            'success'  => new external_value(PARAM_INT, 'id'),
        ));
    }
}
