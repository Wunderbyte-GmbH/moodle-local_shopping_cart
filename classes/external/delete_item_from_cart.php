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
use local_shopping_cart\shopping_cart_bookingfee;
use local_shopping_cart\shopping_cart_history;
use local_shopping_cart\shopping_cart_rebookingcredit;
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
class delete_item_from_cart extends external_api {

    /**
     * Describes the paramters for this service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component'  => new external_value(PARAM_COMPONENT, 'component name like mod_booking', VALUE_DEFAULT, ''),
            'area'  => new external_value(PARAM_TEXT, 'area like main', VALUE_DEFAULT, ''),
            'itemid'  => new external_value(PARAM_INT, 'itemid', VALUE_DEFAULT, '0'),
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, '0'),
            ]
        );
    }

    /**
     * Excecute this websrvice.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param int $userid
     *
     * @return array
     */
    public static function execute(string $component, string $area, int $itemid, int $userid) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'userid' => $userid,
        ]);

        global $USER;

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        $context = context_system::instance();
        if ($params['userid'] == 0 ) {
            $userid = (int)$USER->id;
        } else if ($params['userid'] < 0) {
            if (has_capability('local/shopping_cart:cashier', $context)) {
                $userid = shopping_cart::return_buy_for_userid();
            }
        } else {
            $userid = $params['userid'];
        }

        // We can't delete the fee item via webservice.
        if (shopping_cart_bookingfee::is_fee($params['component'], $params['area'])
            && !has_capability('local/shopping_cart:cashier', $context)) {
            return false;
        }

        // We can't delete the rebookingcredit item via webservice.
        if (shopping_cart::is_rebookingcredit($params['component'], $params['area'])
            && !has_capability('local/shopping_cart:cashier', $context)) {
            return false;
        }

        if ($params['component'] == 'local_shopping_cart' && $params['area'] == 'rebookitem') {
            shopping_cart_history::toggle_mark_for_rebooking($params['itemid'], $userid, true);
            shopping_cart_bookingfee::add_fee_to_cart($userid);
            return ['success' => 1];
        }

        // This treats the cache side.
        if (shopping_cart::delete_item_from_cart($params['component'], $params['area'], $params['itemid'], $userid)) {
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
        return new external_single_structure([
            'success'  => new external_value(PARAM_INT, 'id'),
        ]);
    }
}
