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
use external_multiple_structure;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;
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
class get_price extends external_api {

    /**
     * Describes the parameters for get_price.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                        'userid' => new external_value(PARAM_INT, 'userid', VALUE_DEFAULT, 0),
                        'usecredit' => new external_value(PARAM_INT, 'use credit', VALUE_DEFAULT, 0),
                        'useinstallments' => new external_value(PARAM_INT, 'use installments', VALUE_DEFAULT, 0),
                ]
        );
    }

    /**
     * Webservice for shopping_cart class to add a new item to the cart.
     *
     * @param int $userid
     * @param int $usecredit
     * @param int $useinstallments
     *
     * @return array
     */
    public static function execute(int $userid, int $usecredit, int $useinstallments): array {
        $params = self::validate_parameters(self::execute_parameters(), [
                'userid' => $userid,
                'usecredit' => $usecredit,
                'useinstallments' => $useinstallments,
        ]);

        global $USER;

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canbuy', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        // As we need the userid in two functions below, we have this logic here.
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

        // Add the state to the cache.
        shopping_cart::save_used_credit_state($userid, $usecredit);
        $cartstore = cartstore::instance($userid);
        $cartstore->save_useinstallments_state($params['useinstallments']);

        // The price is calculated from the cache, but there is a fallback to DB, if no cache is available.
        $cartstore = cartstore::instance($userid);
        $data = $cartstore->get_data();

        // For the webservice, we must make sure that the keys exist.
        $data['remainingcredit'] = $data['remainingcredit'] ?? 0;
        $data['deductible'] = $data['deductible'] ?? 0;

        return $data;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                        'price' => new external_value(PARAM_FLOAT, 'Total price', VALUE_REQUIRED),
                        'price_net' => new external_value(PARAM_FLOAT, 'Total price net amount', VALUE_DEFAULT, 0),
                        'count' => new external_value(PARAM_INT, 'Number of items', VALUE_REQUIRED),
                        'taxesenabled' => new external_value(PARAM_BOOL, 'Is tax information enabled', VALUE_REQUIRED),
                        'credit' => new external_value(PARAM_FLOAT, 'Credit', VALUE_REQUIRED),
                        'currency' => new external_value(PARAM_ALPHA, 'Currency', VALUE_REQUIRED),
                        'initialtotal' => new external_value(PARAM_FLOAT, 'Initial price before deduced credits', VALUE_REQUIRED),
                        'initialtotal_net' => new external_value(
                            PARAM_FLOAT,
                            'Initial price before deduced credits net amount',
                            VALUE_DEFAULT,
                            0),
                        'remainingcredit' => new external_value(PARAM_FLOAT, 'Credits after reduction', VALUE_REQUIRED),
                        'deductible' => new external_value(PARAM_FLOAT, 'Deductible amount', VALUE_REQUIRED),
                        'usecredit' => new external_value(PARAM_INT, 'If we want to use the credit or not', VALUE_REQUIRED),
                        'useinstallments' => new external_value(PARAM_INT, 'If we want to use installments or not', VALUE_REQUIRED),
                        'discount' => new external_value(PARAM_FLOAT, 'The sum of all discounts on the items.', VALUE_DEFAULT, 0),
                        'installmentscheckboxid' => new external_value(
                            PARAM_TEXT,
                            'As indicator if installments are used at all.',
                            VALUE_DEFAULT,
                            ''
                        ),
                        'installments' => new external_multiple_structure(
                            new external_single_structure([
                                'initialpayment' => new external_value(PARAM_FLOAT, 'Initialpayment', VALUE_REQUIRED),
                                'originalprice' => new external_value(PARAM_FLOAT, 'Original price', VALUE_REQUIRED),
                                'itemname' => new external_value(PARAM_TEXT, 'Item name', VALUE_REQUIRED),
                                'currency' => new external_value(PARAM_TEXT, 'Currency', VALUE_REQUIRED),
                                'payments' => new external_multiple_structure(
                                    new external_single_structure([
                                        'price' => new external_value(PARAM_FLOAT, 'Amount to pay', VALUE_REQUIRED),
                                        'date' => new external_value(PARAM_TEXT, 'Date as string', VALUE_REQUIRED),
                                        'currency' => new external_value(PARAM_TEXT, 'Currency', VALUE_REQUIRED),
                                    ])
                                ),
                                'installmentslinkeditems' => new external_multiple_structure(
                                    new external_single_structure([
                                        'initialpayment' => new external_value(PARAM_FLOAT, 'Initialpayment', VALUE_REQUIRED),
                                        'originalprice' => new external_value(PARAM_FLOAT, 'Original price', VALUE_REQUIRED),
                                        'itemname' => new external_value(PARAM_TEXT, 'Item name', VALUE_REQUIRED),
                                        'currency' => new external_value(PARAM_TEXT, 'Currency', VALUE_REQUIRED),
                                        ]
                                    ),
                                ),
                            ])
                        ),
                ]
        );
    }
}
