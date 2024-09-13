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
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use external_single_structure;
use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for shopping cart.
 *
 * @package   local_shopping_cart
 * @copyright 2024 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Georg Maißer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reload_history extends external_api {
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

        $userid = $params['userid'] == 0 ? (int)$USER->id : $params['userid'];

        // If the given user doesn't want to see the history for herself...
        // ... we check her permissions.
        if ($USER->id != $userid) {
            if (!has_capability('local/shopping_cart:cashier', $context, $USER)) {
                throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
            }
        }

        if (!$historylist = new shoppingcart_history_list($userid)) {
            throw new moodle_exception('couldnotgeneratehistory', 'local_shopping_cart');
        }

        $list = $historylist->return_list();

        return $list;
    }

    /**
     * Definition of return value
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'historyitems' => get_history_items::execute_returns(),
            'has_historyitems' => new external_value(PARAM_BOOL, 'Has history items marker', VALUE_DEFAULT, false),
            'canpayback' => new external_value(PARAM_BOOL, 'Can pay back', VALUE_DEFAULT, false),
            'taxesenabled' => new external_value(PARAM_BOOL, 'Taxes enabled', VALUE_DEFAULT, false),
            'currency' => new external_value(PARAM_ALPHAEXT),
            'credit' => new external_value(PARAM_TEXT, 'Credit', VALUE_DEFAULT, ""),
            'costcentercredits' => new external_multiple_structure(
                new external_single_structure([
                    'balance' => new external_value(PARAM_TEXT, 'balance', VALUE_DEFAULT, ""),
                    'costcenter' => new external_value(PARAM_TEXT, 'costcenter', VALUE_DEFAULT, ""),
                    'currency' => new external_value(PARAM_TEXT, 'currency', VALUE_DEFAULT, ""),
                    ])
                ),
            'userid' => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
        ]);
    }
}
