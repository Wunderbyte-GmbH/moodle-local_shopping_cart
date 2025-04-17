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
use core_payment\helper;
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
class purchase_notification extends external_api {
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
            'justcheck'  => new external_value(PARAM_BOOL, 'userid', VALUE_DEFAULT, false),
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
    public static function execute(int $identifier, string $tid, string $paymentgateway, int $userid, bool $justcheck): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'identifier' => $identifier,
            'tid' => $tid,
            'paymentgateway' => $paymentgateway,
            'userid' => $userid,
            'justcheck' => $justcheck,
        ]);

        global $USER, $DB;

        require_login();

        $context = context_system::instance();

        self::validate_context($context);

        if (!has_capability('local/shopping_cart:canverifypayments', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }
        $successurl = '';
        $success = self::return_checkout_status($params['identifier'], $params['paymentgateway']);
        if ($justcheck) {
            if ($success == 3) {
                $successurl = helper::get_success_url('local_shopping_cart', '', $params['identifier'])->__toString();
            } else if ($success == 2) {
                $successurl = helper::get_success_url('local_shopping_cart', '', $params['identifier'])->__toString();
                $successurl = str_replace('success=1', 'success=0', $successurl);
            }
            return ['status' => $success, 'url' => $successurl];
        }
        if (($success !== 3 || $success !== 2) && !empty($params['paymentgateway'])) {
            if ((!empty($params['tid']) || $params['tid'] == '') && !empty($params['identifier'])) {
                $dbrec = $DB->get_record('paygw_' . $params['paymentgateway'] . '_openorders',
                ['itemid' => $params['identifier']]);
                if ($dbrec) {
                    $params['tid'] = $dbrec->customorderid;
                }
            }
            // If the payment is not successful yet, we can call transaction complete with the data we have here.
            $transactioncompletestring = 'paygw_' . $params['paymentgateway'] . '\external\transaction_complete';
            if (class_exists($transactioncompletestring)) {
                try {
                    $transactioncomplete = new $transactioncompletestring();
                    if ($transactioncomplete instanceof interface_transaction_complete) {
                        $response = $transactioncomplete::execute(
                            'local_shopping_cart',
                            '',
                            $params['identifier'],
                            $params['tid'],
                            '',
                            '',
                            true,
                            '',
                            $params['userid'],
                        );
                        $success = $response['success'] ?? false;
                    } else {
                        throw new moodle_exception(
                            'ERROR: transaction_complete does not implement transaction_complete interface!'
                        );
                    }
                } catch (\Throwable $e) {
                    $success = false;
                }
            }
        }
        // Translate success.
        // Success 1 means here not ok.
        $success = $success ? 3 : 0;

        return ['status' => $success, 'url' => $success ? '' : $successurl];
    }

    /**
     * Returns the checkout status for a given item and payment provider.
     *
     * @param int $itemid The ID of the item to check
     * @param string $provider The payment provider to check
     * @return int Returns the payment status:
     *             0 = Payment not successful/pending
     *             2 = Payment status 2 (specific to provider)
     *             3 = Payment status 3 (specific to provider)
     */
    public static function return_checkout_status($itemid, $provider) {
        global $DB;
        $success = 0;
        $records = $DB->get_records('paygw_' . $provider . '_openorders', ['itemid' => $itemid]);
        if ($records) {
            foreach ($records as $record) {
                if ($record->status == 3) {
                    $success = 3;
                } else if ($record->status == 2) {
                    $success = 2;
                } else {
                    $success = 0;
                }
            }
        }
        return $success;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_INT, 'Status', VALUE_DEFAULT, 0),
            'url' => new external_value(PARAM_URL, 'Success URL', VALUE_OPTIONAL),
            ]);
    }
}
