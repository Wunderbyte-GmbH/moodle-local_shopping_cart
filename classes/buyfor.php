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
 * Shopping cart class to manage the shopping cart.
 *
 * @package local_shopping_cart
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

use cache_helper;
use coding_exception;
use context_system;
use core_user;
use dml_exception;
use lang_string;
use local_shopping_cart\event\checkout_completed;
use local_shopping_cart\event\item_added;
use local_shopping_cart\event\item_bought;
use local_shopping_cart\event\item_canceled;
use local_shopping_cart\event\item_deleted;
use local_shopping_cart\event\payment_rebooked;
use local_shopping_cart\task\delete_item_task;
use moodle_exception;
use Exception;
use local_shopping_cart\event\item_notbought;
use local_shopping_cart\interfaces\interface_transaction_complete;
use local_shopping_cart\payment\service_provider;
use moodle_url;
use stdClass;

/**
 * Class buyfor
 * This class handles all matters of handling the user we buy for.
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class buyfor {

    /**
     *
     * @param int $userid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_user_id(int $userid = 0) {

        global $USER, $SESSION, $CFG, $DB;

        // If we are dealing with a guest user, we need a special treatment of the userid.
        if (isguestuser($USER)) {
            if (get_config('local_shopping_cart', 'selltoguests')) {
                // We'll use the negative session id as userid.

                // Retrieve the session ID of the current guest user.
                $sessionid = session_id();

                if (!$user = core_user::get_user_by_username($sessionid)) {
                    $newuser = (object)[
                        'firstname' => get_string('anonymous', 'local_shopping_cart'),
                        'lastname' => get_string('shopper', 'local_shopping_cart'),
                        'username' => $sessionid,
                        'email' => $sessionid . '@example.com',
                        'confirmed' => 1,
                        'policyagreed' => 1,
                        'mnethostid' => $CFG->mnet_localhost_id,
                    ];

                    require_once($CFG->dirroot.'/user/lib.php');
                    $userid = user_create_user($newuser, false);
                    $user = core_user::get_user($userid);
                }

                complete_user_login($user);
                set_moodle_cookie($user->username);

                $userid = $user->id;

                // We return the negative value of the cr32 of the session as unique way to identify this guest user.
                return [$userid, false];

            } else {
                require_login();
            }
        }

        $buyforuser = false;
        // If there is no user specified, we determine it automatically.
        if ($userid < 0 || $userid == self::return_buy_for_userid()) {
            $context = context_system::instance();
            if (has_capability('local/shopping_cart:cashier', $context)) {
                $userid = self::return_buy_for_userid();
                $buyforuser = true;
            }
        } else {
            // As we are not on cashier anymore, we delete buy for user.
            self::buy_for_user(0);
        }
        if ($userid < 1) {
            $userid = $USER->id;
        }
        return [$userid, $buyforuser];
    }

    /**
     * Add the selected user to cache in chachiermode
     *
     * @param int $userid
     * @return int
     */
    public static function buy_for_user(int $userid): int {
        $cache = \cache::make('local_shopping_cart', 'cashier');

        if ($userid == 0) {
            $cache->delete('buyforuser');
        } else {
            $cache->set('buyforuser', $userid);
        }
        return $userid;
    }

    /**
     * Return userid from cache. global userid if not to be found.
     *
     * @return int
     */
    public static function return_buy_for_userid() {
        global $USER;

        $cache = \cache::make('local_shopping_cart', 'cashier');
        $userid = $cache->get('buyforuser');

        if (!$userid) {
            $userid = $USER->id;
        }
        return $userid;
    }
}
