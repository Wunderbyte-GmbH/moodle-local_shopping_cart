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

use coding_exception;
use context_system;
use dml_exception;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use local_shopping_cart\output\shoppingcart_history_list;
use moodle_exception;
use require_login_exception;

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
class get_history_item extends external_api {

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
     * @return array
     * @throws coding_exception
     * @throws require_login_exception
     * @throws moodle_exception
     * @throws dml_exception
     */
    public static function execute(
            string $componentname,
            string $area,
            int $itemid,
            int $userid): array {

        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'componentname' => $componentname,
            'area' => $area,
            'itemid' => $itemid,
            'userid' => $userid,
        ]);

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        $item = shopping_cart_history::get_most_recent_historyitem(
            $params['componentname'],
            $params['area'],
            $params['itemid'],
            $params['userid'],
        );

        // Users can, unless the are cashier, only fetch information about their own items.
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            if ($USER->id != $item->userid) {
                throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
            }
        }

        if (empty($item->id)) {
            return [
                'success' => 0,
                'id' => 0,
                'price' => 0,
                'currency' => '',
                'quotaconsumed' => 0,
                'round' => 0,
                'cancelationfee' => 0,
            ];
        }

        shopping_cart::add_quota_consumed_to_item($item, $userid);
        shoppingcart_history_list::add_round_config($item);

        return [
            'success' => 1,
            'id' => $item->id,
            'price' => $item->price,
            'currency' => $item->currency,
            'quotaconsumed' => $item->quotaconsumed,
            'round' => $item->round ? 1 : 0,
            'cancelationfee' => get_config('local_shopping_cart', 'cancelationfee') < 1
                ? 0 : get_config('local_shopping_cart', 'cancelationfee'),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_INT, 'Success value 0 or 1'),
            'id' => new external_value(PARAM_INT, 'History id'),
            'price' => new external_value(PARAM_FLOAT, 'Price'),
            'currency' => new external_value(PARAM_ALPHA, 'Currency'),
            'quotaconsumed' => new external_value(PARAM_FLOAT, 'Quotaconsumed'),
            'round' => new external_value(PARAM_FLOAT, 'Round'),
            'cancelationfee' => new external_value(PARAM_FLOAT, 'Cancelationfee'),
            ]
        );
    }
}
