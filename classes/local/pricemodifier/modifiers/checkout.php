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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\pricemodifier\modifiers;

use dml_exception;
use coding_exception;
use local_shopping_cart\local\pricemodifier\modifier_base;
use local_shopping_cart\payment\service_provider;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot.'/user/lib.php');

/**
 * Class checkout
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class checkout extends modifier_base {

    /**
     * The id is nedessary for the hierarchie of modifiers.
     * @var int
     */
    public static $id = LOCAL_SHOPPING_CART_PRICEMOD_CHECKOUT;

    /**
     * Applies the given price modifiers on the cached data.
     * The checkout modifier doesn't apply anything automatically.
     * Instead, it can apply the prepare_checkout method.
     * @param array $data
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function apply(array &$data): array {

        return $data;
    }

    /**
     * This adds a few necessary keys for checkout.
     * It also adds the identifier, if it is not yet there.
     * @param array $data
     * @param string $identifier
     * @return array
     * @throws dml_exception
     * @throws coding_exception
     */
    public static function prepare_checkout(array &$data, string $identifier = ''): array {

        global $USER, $CFG;

        $userid = $data['userid'];

        if (empty($identifier)) {
            $identifier = (string)shopping_cart_history::create_unique_cart_identifier($userid);
        }

        foreach ($data['items'] as $key => $item) {
            // This function is only used for online payment.
            $data['items'][$key]['payment'] = LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE;
            $data['items'][$key]['paymentstatus'] = LOCAL_SHOPPING_CART_PAYMENT_PENDING;
            $data['items'][$key]['identifier'] = $identifier;
            $data['items'][$key]['usermodified'] = $USER->id; // The user who actually effected the transaction.
            $data['items'][$key]['identifier'] = $identifier;
        }

        $data['identifier'] = $identifier;

        // Make sure we have this data in the schistory cache.
        $history = new shopping_cart_history();
        $history->store_in_schistory_cache($data);

        $data['wwwroot'] = $CFG->wwwroot;
        $sp = new service_provider();
        $data['successurl'] = $sp->get_success_url('shopping_cart', (int)$identifier)->out(false);
        $data['usecreditvalue'] = $data['usecredit'] == 1 ? 'checked' : '';

        $users = user_get_users_by_id([$userid]);
        $user = reset($users);

        $data['name'] = "$user->firstname $user->lastname";
        $data['mail'] = $user->email;

        shopping_cart::convert_prices_to_number_format($data);

        return $data;
    }
}
