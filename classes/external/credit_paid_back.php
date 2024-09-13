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
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * External Service for shopping cart.
 *
 * @package   local_shopping_cart
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_paid_back extends external_api {

    /**
     * Describes the paramters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, '0'),
            'method' => new external_value(PARAM_INT, 'method', VALUE_DEFAULT,
                LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH),
            'costcenter' => new external_value(PARAM_TEXT, 'costcenter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Excecute this websrvice.
     * @param int $userid
     * @param int $method
     * @param string $costcenter
     * @return array
     */
    public static function execute(int $userid, int $method, string $costcenter) {

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'method' => $method,
            'costcenter' => $costcenter,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:cashier', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        return shopping_cart::credit_paid_back($params['userid'], $params['method'], $params['costcenter']);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_INT, 'Just to confirm payment went through 0 is fail.'),
                'error' => new external_value(PARAM_RAW, 'Error message.'),
            ]
        );
    }
}
