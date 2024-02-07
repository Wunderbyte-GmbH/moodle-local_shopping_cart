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
class get_booking_item extends external_api {

    /**
     * Describes the parameters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Excecute this webservice.
     *
     * @param int $userid
     * @return void
     */
    public static function execute($itemid) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'itemid' => $itemid,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        return shopping_cart::get_booking_item_by_id($itemid);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'Number of items', VALUE_REQUIRED),
                'text' => new external_value(PARAM_TEXT, 'Booking item text', VALUE_REQUIRED),
                'price' => new external_value(PARAM_TEXT, 'Total price', VALUE_REQUIRED),
            ]
        );
    }
}
