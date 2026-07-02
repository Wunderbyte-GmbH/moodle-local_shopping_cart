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

use cache;
use core\task\manager as taskmanager;
use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\task\delete_guest_user_task;
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
     * - auth = 'manual'   →  treated as active by Moodle internals during login side-effects
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

        require_once($CFG->dirroot . '/user/lib.php');

        $uniqid = md5(uniqid('guestcheckout_', true));

        $user = new stdClass();
        $user->username    = 'guest_checkout_' . $uniqid;
        $user->email       = 'guest_' . $uniqid . '@noreply.local';
        $user->firstname   = 'Guest';
        $user->lastname    = 'User';
        $user->confirmed   = 1;
        $user->auth        = 'manual';
        $user->suspended   = 0;
        $user->mnethostid  = $CFG->mnet_localhost_id;
        $user->lang        = $CFG->lang ?? 'en';
        $user->timecreated = time();
        $user->timemodified = time();

        $userid = user_create_user($user, false, false);
        $user->id = $userid;

        // Map the guest user to the configured system role. Defaults to the "Authenticated user"
        // role, but an admin can point this at a purpose-built, more restricted role instead.
        self::assign_guest_role($userid);

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
     * Assigns the configured system role to a freshly created guest checkout user.
     *
     * The role id comes from the `guestcheckoutrole` admin setting (defaults to the
     * "Authenticated user" role). When the setting is empty or points to a role that no
     * longer exists the assignment is silently skipped.
     *
     * @param int $userid
     * @return void
     */
    private static function assign_guest_role(int $userid): void {
        global $DB;

        $roleid = (int) get_config('local_shopping_cart', 'guestcheckoutrole');
        if ($roleid <= 0 || !$DB->record_exists('role', ['id' => $roleid])) {
            return;
        }

        role_assign($roleid, $userid, \context_system::instance()->id);
    }

    /**
     * Optionally creates a guest checkout user for an anonymous visitor based on
     * configurable URL patterns.
     *
     * Only runs when the guest checkout feature is enabled and at least one URL pattern
     * is configured; the pattern match itself is handled by
     * {@see url_matches_auto_create_patterns()}.
     *
     * @param \moodle_url $url
     * @return bool True if a guest user was created in this call.
     */
    public static function maybe_auto_create_guest_user_for_url(\moodle_url $url): bool {
        if (!get_config('local_shopping_cart', 'guestoncheckout')) {
            return false;
        }

        if (isloggedin() || isguestuser()) {
            return false;
        }

        if (!self::url_matches_auto_create_patterns($url)) {
            return false;
        }

        self::create_guest_user();

        // Reload the exact URL (including query params) so the new login state
        // is reflected in a fresh request cycle immediately.
        redirect((new \moodle_url($url->out(false)))->out(false));
        return true;
    }

    /**
     * Checks whether a URL path matches one of the configured auto-create patterns.
     *
     * @param \moodle_url $url
     * @return bool
     */
    private static function url_matches_auto_create_patterns(\moodle_url $url): bool {
        $rawpatterns = (string)get_config('local_shopping_cart', 'guestautocreatepatterns');
        if ($rawpatterns === '') {
            return false;
        }

        $path = self::normalize_path($url->get_path());
        $patterns = preg_split('/[\r\n,;]+/', $rawpatterns);

        foreach ($patterns as $pattern) {
            $pattern = self::normalize_path(trim((string)$pattern));
            if ($pattern === '') {
                continue;
            }

            // Wildcard suffix supports prefix matching, e.g. /course/*.
            if (substr($pattern, -1) === '*') {
                $prefix = self::normalize_path(rtrim(substr($pattern, 0, -1)));
                if ($prefix === '' || $prefix === '/') {
                    return true;
                }
                if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                    return true;
                }
                continue;
            }

            if ($pattern === '/' && $path === '/') {
                return true;
            }

            if ($path === $pattern || strpos($path, $pattern . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalizes local paths for robust path matching.
     *
     * @param string $path
     * @return string
     */
    private static function normalize_path(string $path): string {
        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
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
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');

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
        $resetrecord->userid        = $user->id;
        $resetrecord->timerequested = time();
        $resetrecord->token         = random_string(32);
        global $DB;
        $DB->insert_record('user_password_resets', $resetrecord);

        // Build the password-reset URL including the token so the user lands directly
        // on the reset form rather than having to request another token.
        $reseturl = (new \moodle_url('/login/forgot_password.php', [
            'token' => $resetrecord->token,
        ]))->out(false);

        // Send e-mail using Moodle's built-in mechanism.
        $supportuser = \core_user::get_support_user();
        $emailmessage = get_string(
            'guestcheckout:passwordresetemail',
            'local_shopping_cart',
            (object)[
                'firstname' => $user->firstname,
                'reseturl'  => $reseturl,
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

    /**
     * Migrate a guest checkout cart to a real user after successful login.
     *
     * @param int $guestuserid
     * @param int $targetuserid
     * @return int Suggested checkout step to resume.
     */
    public static function migrate_guest_checkout_to_user(int $guestuserid, int $targetuserid): int {
        global $DB;

        if ($guestuserid < 1 || $targetuserid < 1 || $guestuserid === $targetuserid) {
            return 0;
        }

        $guestcartstore = cartstore::instance($guestuserid);
        $targetcartstore = cartstore::instance($targetuserid);

        $guestdata = $guestcartstore->get_data();
        $targetdata = $targetcartstore->get_data();

        $targetitems = $targetdata['items'] ?? [];
        foreach (($guestdata['items'] ?? []) as $itemkey => $guestitem) {
            $guestitem['userid'] = $targetuserid;

            if (self::must_merge_as_single_item($guestitem)) {
                $guestitem = self::normalize_single_item($guestitem);
            }

            if (!isset($targetitems[$itemkey])) {
                $targetitems[$itemkey] = $guestitem;
                continue;
            }

            $targetitem = $targetitems[$itemkey];

            if (self::must_merge_as_single_item($targetitem) || self::must_merge_as_single_item($guestitem)) {
                $targetitem = self::normalize_single_item($targetitem);
                $targetitem['userid'] = $targetuserid;
                $targetitems[$itemkey] = $targetitem;
                continue;
            }

            $targetcount = (int)($targetitem['nritems'] ?? 1);
            $guestcount = (int)($guestitem['nritems'] ?? 1);
            $targetitem['nritems'] = $targetcount + $guestcount;

            if (isset($targetitem['price']) && isset($guestitem['price'])) {
                $targetitem['price'] = (float)$targetitem['price'] + (float)$guestitem['price'];
            }
            if (isset($targetitem['discount']) && isset($guestitem['discount'])) {
                $targetitem['discount'] = (float)$targetitem['discount'] + (float)$guestitem['discount'];
            }
            $targetitem['userid'] = $targetuserid;
            $targetitems[$itemkey] = $targetitem;
        }

        $targetdata['items'] = $targetitems;
        $targetdata['userid'] = $targetuserid;
        $targetdata['expirationtime'] = max((int)($targetdata['expirationtime'] ?? 0), (int)($guestdata['expirationtime'] ?? 0));

        foreach (['address_billing', 'address_shipping', 'taxcountrycode', 'vatnrcountry', 'vatnrnumber'] as $key) {
            if (empty($targetdata[$key]) && !empty($guestdata[$key])) {
                $targetdata[$key] = $guestdata[$key];
            }
        }

        $targetcartstore->set_cache($targetdata);
        $targetcartstore->save_cart_to_db();

        // Move guest addresses so selected address ids remain valid after login.
        $DB->set_field('local_shopping_cart_address', 'userid', $targetuserid, ['userid' => $guestuserid]);

        $resumestep = self::migrate_checkout_cache($guestuserid, $targetuserid);

        // Clear guest cache + fallback reservation after successful migration.
        $guestcartstore->delete_all_items();

        return $resumestep;
    }

    /**
     * True when an item must not be multiplied during cart merge.
     *
     * @param array $item
     * @return bool
     */
    private static function must_merge_as_single_item(array $item): bool {
        return self::is_booking_fee_item($item) || !self::is_item_multipliable($item);
    }

    /**
     * Normalize an item to single-quantity semantics.
     *
     * @param array $item
     * @return array
     */
    private static function normalize_single_item(array $item): array {
        $item['nritems'] = 1;
        return $item;
    }

    /**
     * Checks if an item is marked as multipliable.
     *
     * @param array $item
     * @return bool
     */
    private static function is_item_multipliable(array $item): bool {
        if (!array_key_exists('multipliable', $item)) {
            return false;
        }

        $value = $item['multipliable'];
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int)$value) > 0;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Checks if an item is a booking fee line.
     *
     * @param array $item
     * @return bool
     */
    private static function is_booking_fee_item(array $item): bool {
        return (($item['area'] ?? '') === 'bookingfee');
    }

    /**
     * Move checkout process cache from guest user to target user.
     *
     * @param int $guestuserid
     * @param int $targetuserid
     * @return int Suggested step for resume.
     */
    private static function migrate_checkout_cache(int $guestuserid, int $targetuserid): int {
        $preprocesscache = cache::make('local_shopping_cart', 'cachebookingpreprocess');

        $guestcache = checkout_manager::get_cache($guestuserid);
        if (empty($guestcache)) {
            return 0;
        }

        $targetcache = checkout_manager::get_cache($targetuserid);
        if (empty($targetcache)) {
            $targetcache = $guestcache;
        } else {
            $targetcache['steps'] = $guestcache['steps'] ?? $targetcache['steps'] ?? [];
            $targetcache['viewed'] = $guestcache['viewed'] ?? $targetcache['viewed'] ?? [];
            $targetcache['checkout_validation'] = $guestcache['checkout_validation']
                ?? $targetcache['checkout_validation']
                ?? false;
            $targetcache['body_mandatory_count'] = $guestcache['body_mandatory_count']
                ?? $targetcache['body_mandatory_count']
                ?? [];
            $targetcache['feedback'] = $guestcache['feedback'] ?? $targetcache['feedback'] ?? [];
        }

        $preprocesscache->set($targetuserid, $targetcache);
        $preprocesscache->delete($guestuserid);

        $viewedcount = count($targetcache['viewed'] ?? []);
        return max(0, $viewedcount - 1);
    }
}
