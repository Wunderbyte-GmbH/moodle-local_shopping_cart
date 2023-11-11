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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_single_structure;
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
class get_shopping_cart_items extends external_api {

    /**
     * Describes the parameters for this service.
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
     *
     * @param int $userid
     * @return void
     */
    public static function execute($userid) {
        global $USER;

        $params = external_api::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);

        require_login();

        $context = context_system::instance();
        if ($params['userid'] == 0) {
            $userid = (int) $USER->id;
        } else if ($params['userid'] < 0) {
            if (has_capability('local/shopping_cart:cashier', $context)) {
                $userid = (int) shopping_cart::return_buy_for_userid();
            }
        } else {
            $userid = (int) $params['userid'];
        }

        return shopping_cart::local_shopping_cart_get_cache_data($userid, true);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {

        return new external_single_structure(
                [
                        'count' => new external_value(PARAM_INT, 'Number of items'),
                        'price' => new external_value(PARAM_FLOAT, 'Total price'),
                        'price_net' => new external_value(PARAM_FLOAT, 'Total net price', false),
                        'credit' => new external_value(PARAM_FLOAT, 'Credit'),
                        'currency' => new external_value(PARAM_RAW, 'Currency'),
                        'taxesenabled' => new external_value(PARAM_BOOL, 'Is tax information enabled'),
                        'initialtotal' => new external_value(PARAM_FLOAT, 'Initial price before deduced credits'),
                        'initialtotal_net' => new external_value(PARAM_FLOAT, 'Initial price before deduced credits net amount',
                                false),
                        'remainingcredit' => new external_value(PARAM_FLOAT, 'Credits after reducation'),
                        'deductible' => new external_value(PARAM_FLOAT, 'Deductible amount'),
                        'usecredit' => new external_value(PARAM_INT, 'If we want to use the credit or not'),
                        'discount' => new external_value(PARAM_FLOAT, 'The sum of all discounts on the items.', VALUE_DEFAULT, 0),
                        'expirationdate' => new external_value(PARAM_INT, 'Expiration timestamp of cart'),
                        'nowdate' => new external_value(PARAM_INT, 'current Timestamp'),
                        'maxitems' => new external_value(PARAM_INT, 'Max Items'),
                        'items' => new external_multiple_structure (
                                new external_single_structure(
                                        [
                                                'userid' => new external_value(PARAM_INT, 'userid'),
                                                'itemid' => new external_value(PARAM_INT, 'Item id'),
                                                'itemname' => new external_value(PARAM_TEXT, 'Item name'),
                                                'price' => new external_value(PARAM_FLOAT, 'Price of item'),
                                                'price_gross' => new external_value(PARAM_FLOAT, 'Gross price of item', false),
                                                'price_net' => new external_value(PARAM_FLOAT, 'Net price of item', false),
                                                'tax' => new external_value(PARAM_FLOAT, 'Net tax of item price', false),
                                                'taxcategory' => new external_value(PARAM_TAG, 'Tax category of item'),
                                                'taxpercentage' => new external_value(PARAM_FLOAT,
                                                        'Tax percentage of item price as float', false),
                                                'taxpercentage_visual' => new external_value(PARAM_FLOAT,
                                                        'Tax percentage of item price as an int', false),
                                                'currency' => new external_value(PARAM_ALPHA, 'Currency'),
                                                'componentname' => new external_value(PARAM_TEXT, 'Component name'),
                                                'costcenter' => new external_value(PARAM_TEXT, 'Cost center for item'),
                                                'area' => new external_value(PARAM_TEXT, 'Area'),
                                                'description' => new external_value(PARAM_RAW, 'Item description'),
                                                'imageurl' => new external_value(PARAM_RAW, 'Image url'),
                                                'canceluntil' => new external_value(PARAM_INT,
                                                        'Timestamp until when cancel is possible'),
                                        ]
                                )
                        ),
                ]
        );
    }
}
