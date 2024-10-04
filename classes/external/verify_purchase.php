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
use local_shopping_cart\interfaces\interface_transaction_complete;
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
class verify_purchase extends external_api {
    /**
     * Describes the parameters for add_item_to_cart.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'identifier'  => new external_value(PARAM_INT, 'identifier', VALUE_DEFAULT, 0),
            'tid'  => new external_value(PARAM_TEXT, 'tid', VALUE_DEFAULT, ''),
            'paymentgateway'  => new external_value(PARAM_TEXT, 'paymentgateway', VALUE_DEFAULT, ''),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
            ]);
    }

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     *
     * @param int $identifier
     * @param string $tid
     * @param string $paymentgateway
     * @param int $userid
     *
     * @return array
     */
    public static function execute(int $identifier, string $tid, string $paymentgateway, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'identifier' => $identifier,
            'tid' => $tid,
            'paymentgateway' => $paymentgateway,
            'userid' => $userid,
        ]);

        global $USER;

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canverifypayments', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        $success = shopping_cart_history::has_successful_checkout($params['identifier']);

        // Translate success.
        // Success 1 means here not ok.
        $success = $success ? 0 : 1;

        return ['status' => $success];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, 'Status', VALUE_DEFAULT, 0),
            ]);
    }
}
