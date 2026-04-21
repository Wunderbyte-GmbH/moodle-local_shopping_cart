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
 * Guest checkout functionality for local_shopping_cart.
 *
 * @package local_shopping_cart
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use core\task\manager as taskmanager;
use local_shopping_cart\task\delete_guest_user_task;
use moodle_exception;
use stdClass;

/**
 * Class guestcheckout
 *
 * Manages the lifecycle of temporary guest checkout users:
 * - Creates a disposable Moodle user when an unauthenticated visitor adds an item
 *   to the cart (feature must be enabled via the `guestoncheckout` admin setting).
 * - The temporary user is automatically scheduled for deletion 24 hours after creation.
 * - Once a successful purchase is completed the guest account is converted into a
 *   permanent Moodle user with the name and e-mail address provided during checkout.
 *
 * @package local_shopping_cart
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guestcheckout {

    /** Seconds until a guest user that has not purchased anything is deleted (24 h). */
    const GUEST_USER_TTL = 86400;

    /**
     * Creates a temporary guest checkout user and logs that user in for the
     * current request.
     *
     * The user record is created with:
     * - auth = 'nologin'  →  cannot authenticate via the login page
     * - confirmed = 1     →  avoids confirm-email flows that block activity
     * - suspended = 0     →  must be active so the session works
     *
     * A row is inserted into `local_shopping_cart_guestusers` and an adhoc task
     * is queued to delete the user after {@see GUEST_USER_TTL} seconds.
     *
     * @return stdClass The newly created (and now active) user record.
     */
    public static function create_guest_user(): stdClass {
        global $CFG, $DB;

        $uniqid = md5(uniqid('guestcheckout_', true));

        $user = new stdClass();
        $user->username    = 'guest_checkout_' . $uniqid;
        $user->email       = 'guest_' . $uniqid . '@noreply.local';
        $user->firstname   = 'Guest';
        $user->lastname    = 'User';
        $user->confirmed   = 1;
        $user->auth        = 'nologin';
        $user->mnethostid  = $CFG->mnet_localhost_id;
        $user->lang        = $CFG->lang ?? 'en';
        $user->timecreated = time();
        $user->timemodified = time();

        $userid = user_create_user($user, false, false);
        $user->id = $userid;

        // Record the guest user so we can identify and clean it up later.
        $guestrecord = new stdClass();
        $guestrecord->userid      = $userid;
        $guestrecord->timecreated = time();
        $DB->insert_record('local_shopping_cart_guestusers', $guestrecord);

        // Schedule cleanup.
        self::schedule_guest_cleanup($userid);

        // Log the user in for the current request.
        $fulluser = get_complete_user_data('id', $userid);
        complete_user_login($fulluser);

        return $fulluser;
    }

    /**
     * Returns true when the given userid belongs to a guest checkout user that
     * has not yet been converted to a real account.
     *
     * @param int $userid
     * @return bool
     */
    public static function is_guest_checkout_user(int $userid): bool {
        global $DB;
        if ($userid < 1) {
            return false;
        }
        return $DB->record_exists('local_shopping_cart_guestusers', ['userid' => $userid]);
    }

    /**
     * Converts a temporary guest checkout user into a permanent Moodle account.
     *
     * The Moodle user record is updated with the supplied personal data and the
     * `auth` method is switched to `'manual'` so the user can request a password-
     * reset e-mail and subsequently log in normally.
     *
     * The entry in `local_shopping_cart_guestusers` is removed and any pending
     * cleanup task is cancelled via the adhoc-task custom-data sentinel.
     *
     * @param int    $userid    ID of the guest user to convert.
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     * @return bool True on success.
     */
    public static function convert_guest_to_real_user(
        int $userid,
        string $firstname,
        string $lastname,
        string $email
    ): bool {
        global $DB;

        if (!self::is_guest_checkout_user($userid)) {
            return false;
        }

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $user->firstname    = $firstname;
        $user->lastname     = $lastname;
        $user->email        = $email;
        $user->auth         = 'manual';
        $user->timemodified = time();
        $user->username     = 'checkout_' . $email; // Deterministic & human-readable.

        // Ensure the new username is available; if not, keep a unique one.
        if ($DB->record_exists('user', ['username' => $user->username])) {
            $user->username = 'checkout_' . md5($email . time());
        }

        user_update_user($user, false, false);

        // Remove the guest-user record so the cleanup task silently skips.
        $DB->delete_records('local_shopping_cart_guestusers', ['userid' => $userid]);

        // Send a password-set e-mail so the user can log in next time.
        self::send_set_password_email($userid);

        return true;
    }

    /**
     * Schedules an adhoc task that will delete the guest user after
     * {@see GUEST_USER_TTL} seconds unless the user has purchased something.
     *
     * @param int $userid
     * @return void
     */
    public static function schedule_guest_cleanup(int $userid): void {
        $task = new delete_guest_user_task();
        $task->set_custom_data(['userid' => $userid]);
        $task->set_next_run_time(time() + self::GUEST_USER_TTL);
        taskmanager::queue_adhoc_task($task);
    }

    /**
     * Immediately deletes a guest checkout user if it has not yet been converted.
     * Cart items are NOT explicitly deleted here because Moodle will invalidate
     * the cache once the user no longer exists.
     *
     * @param int $userid
     * @return bool
     */
    public static function delete_guest_user(int $userid): bool {
        global $DB;

        if (!self::is_guest_checkout_user($userid)) {
            return false;
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            // Already gone – remove the tracking record and return.
            $DB->delete_records('local_shopping_cart_guestusers', ['userid' => $userid]);
            return true;
        }

        $DB->delete_records('local_shopping_cart_guestusers', ['userid' => $userid]);
        delete_user($user);

        return true;
    }

    /**
     * Triggers a "set your password" e-mail for the newly converted real user.
     *
     * @param int $userid
     * @return void
     */
    private static function send_set_password_email(int $userid): void {
        $user = get_complete_user_data('id', $userid);
        if (!$user) {
            return;
        }
        // Generate a nonce for the password-reset URL.
        set_user_preference('auth_forcepasswordchange', 0, $user);
        $resetrecord = new stdClass();
        $resetrecord->userid    = $user->id;
        $resetrecord->timerequested = time();
        $resetrecord->timererequested = 0;
        $resetrecord->token     = random_string(32);
        global $DB;
        $DB->insert_record('user_password_resets', $resetrecord);

        // Send e-mail using Moodle's built-in mechanism.
        $supportuser = \core_user::get_support_user();
        $emailmessage = get_string('guestcheckout:passwordresetemail', 'local_shopping_cart',
            (object)[
                'firstname' => $user->firstname,
                'wwwroot'   => (new \moodle_url('/'))->out(false),
                'token'     => $resetrecord->token,
            ]
        );
        email_to_user(
            $user,
            $supportuser,
            get_string('guestcheckout:passwordresetemailsubject', 'local_shopping_cart'),
            html_to_text($emailmessage),
            $emailmessage
        );
    }
}
