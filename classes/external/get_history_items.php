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

use block_recentlyaccesseditems\external;
use context_system;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;

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
class get_history_items extends external_api {

    /**
     * Describes the paramters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Excecute this webservice.
     * @param int $userid
     * @return array
     */
    public static function execute($userid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:cashier', $context)) {
            $userid = $params['userid'] == 0 ? (int)$USER->id : $params['userid'];
        } else {
            $userid = (int)$USER->id;
        }

        return shopping_cart_history::get_history_list_for_user($userid);
    }

    /**
     * Definition of return value
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'itemid' => new external_value(PARAM_INT, 'Item id'),
                    'id' => new external_value(PARAM_INT, 'Historyid id'),
                    'itemname' => new external_value(PARAM_TEXT, 'Item name'),
                    'price' => new external_value(PARAM_FLOAT, 'Price of item'),
                    'currency' => new external_value(PARAM_ALPHA, 'Currency'),
                    'componentname' => new external_value(PARAM_COMPONENT, 'Component name'),
                    'paymentstatus' => new external_value(PARAM_INT, 'Paymentstatus'),
                ]
            )
        );
    }
}
