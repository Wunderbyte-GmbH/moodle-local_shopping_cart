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
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\items;

use core_auth\output\login as login_renderable;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\checkout_base_item;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;
use local_shopping_cart\local\guestcheckout;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addresses extends checkout_base_item {
    /**
     * Collected error messages of the last check_status() call.
     * @var array
     */
    private static $lasterrors = [];

    /**
     * Returns the post-login redirect target preserving current GET params.
     *
     * @return string
     */
    private static function get_post_login_wantsurl(): string {
        global $PAGE;

        $wantsurl = new \moodle_url($PAGE->url->out(false));

        // Keep the seamless checkout resume marker when the login form is shown in checkout.
        if ($wantsurl->get_path() === '/local/shopping_cart/checkout.php') {
            $wantsurl->param('checkoutresume', 1);
        }

        return $wantsurl->out(false);
    }

    /**
     * Returns order number of form.
     * @return int
     */
    public static function get_ordernumber(): int {
        return 0;
    }

    /**
     * Renders checkout item.
     * @param array $changedinput
     * @param array $managercache
     * @return bool
     */
    public static function is_active($changedinput, $managercache): bool {
        if (!empty(self::get_required_address_keys())) {
            return true;
        }
        // Also show this step when a guest checkout user needs to register.
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
        ) {
            global $USER;
            if (guestcheckout::is_guest_checkout_user($USER->id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * This step is implemented as dynamic form (phase 3 of the forms migration).
     * Remove this override to roll back to the legacy render_body/check_status path.
     *
     * @return string
     */
    public static function get_form_classname(): string {
        return \local_shopping_cart\local\checkout_process\steps\addresses_form::class;
    }

    /**
     * Markup around the addresses form: the guest login/registration panel
     * (contains a real login <form>) before it, the add/edit/delete address
     * actions (contain another dynamic form) after it.
     *
     * @return array
     */
    public static function render_form_surroundings(): array {
        global $PAGE, $USER, $SESSION;

        $renderer = $PAGE->get_renderer('local_shopping_cart');
        $isguestcheckoutuser = (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user($USER->id)
        );

        $before = '';
        if ($isguestcheckoutuser) {
            $wantsurl = self::get_post_login_wantsurl();
            $SESSION->local_shopping_cart_guest_login_context = [
                'guestuserid' => (int)$USER->id,
                'timecreated' => time(),
                'source' => 'checkout',
            ];
            $SESSION->wantsurl = $wantsurl;

            $before = $renderer->render_from_template('local_shopping_cart/guest_login_panel', [
                'loginoptions' => self::get_login_options_data($wantsurl),
            ]);
        }

        $after = '';
        $requiredaddresses = array_values(self::get_required_address_data());
        if (!empty($requiredaddresses)) {
            $after = $renderer->render_from_template('local_shopping_cart/address_actions', [
                'required_addresses' => $requiredaddresses,
                'is_guest_checkout_user' => $isguestcheckoutuser,
                'has_saved_addresses' => !empty(address_operations::get_all_user_addresses((int)$USER->id)),
            ]);
        }

        return [
            'before' => $before,
            'after' => $after,
            'wrapperclass' => 'local-shopping_cart-addressselection mb-4',
        ];
    }

    /**
     * Renders checkout item.
     * @return string
     */
    public static function get_icon_progress_bar(): string {
        return 'fa-solid fa-address-book';
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     *
     * @return array all required template data
     */
    public static function get_template_render_data(): array {
        $data = self::get_user_data();
        $addressesfromdb = address_operations::get_all_user_addresses($data["userid"]);
        $countries = get_string_manager()->get_list_of_countries();

        $savedaddresses = [];
        foreach ($addressesfromdb as $dbaddress) {
            $dbaddress->country = $countries[$dbaddress->state];
            $savedaddresses[] = $dbaddress;
        }

        $data['is_guest_checkout_user'] = (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user((int)$data["userid"])
        );

        $requiredaddresseslocalized = self::get_required_address_data();
        $data['required_addresses'] = array_values($requiredaddresseslocalized);
        $isfirst = true;
        foreach ($data['required_addresses'] as &$requiredaddress) {
            $requiredaddress['saved_addresses'] = $savedaddresses;
            $requiredaddress['has_saved_addresses'] = !empty($savedaddresses);
            $requiredaddress['isactive'] = $isfirst;
            $isfirst = false;
        }
        $data['required_addresses_keys'] = array_reduce($requiredaddresseslocalized, function ($keys, $addressdata) {
            $keys[] = $addressdata['addresskey'];
            return $keys;
        }, []);
        $data['required_addresses_multiple'] = count($requiredaddresseslocalized) > 1;
        $data['has_saved_addresses'] = !empty($savedaddresses);
        $data['default_address_key'] = $data['required_addresses'][0]['addresskey'] ?? 'billing';
        return $data;
    }

    /**
     * Get some data of current user.
     *
     * @return array array containing user data
     */
    public static function get_user_data(): array {
        global $USER;
        return [
            "userid" => $USER->id ?? 0,
            "username" => $USER->username ?? '',
            "firstname" => $USER->firstname ?? '',
            "lastname" => $USER->lastname ?? '',
            "email" => $USER->email ?? '',
        ];
    }

    /**
     * Generates complete required-address data as specified by the plugin config.
     *
     * @return array list of all required addresses with a key and localized string
     */
    public static function get_required_address_data(): array {
        $requiredaddresseslocalized = [];
        $requiredaddresskeys = self::get_required_address_keys();
        foreach ($requiredaddresskeys as $addresstype) {
            $requiredaddresseslocalized[$addresstype] = [
                    "addresskey" => $addresstype,
                    "addresslabel" => get_string('addresses:' . $addresstype, 'local_shopping_cart'),
            ];
        }
        return $requiredaddresseslocalized;
    }

    /**
     * Renders checkout item.
     */
    public static function is_mandatory(): bool {
        return true;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return array list of all required address keys
     */
    public static function get_required_address_keys(): array {
        global $USER;

        $addressesrequired = get_config('local_shopping_cart', 'addresses_required');
        $requiredaddresskeys = array_map(
            fn(string $key): string => preg_replace('/^selectedaddress_/', '', trim($key)),
            array_filter(explode(',', (string)$addressesrequired))
        );

        // Checkout UX is intentionally billing-centric.
        if (in_array('billing', $requiredaddresskeys, true)) {
            return ['billing'];
        }

        // Guest checkout always requires at least one billing address.
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user((int)($USER->id ?? 0))
        ) {
            return ['billing'];
        }

        if (!empty($requiredaddresskeys)) {
            return [reset($requiredaddresskeys)];
        }

        // Keep the billing step visible after guest->user migration when a
        // selected address is already present in the checkout cache.
        $checkoutcache = \local_shopping_cart\local\checkout_process\checkout_manager::get_cache((int)($USER->id ?? 0));
        $stepdata = $checkoutcache['steps']['addresses']['data'] ?? [];
        if (!empty($stepdata['selectedaddress_billing']) || !empty($stepdata['selectedaddress_shipping'])) {
            return ['billing'];
        }

        return [];
    }

    /**
     * Validation core for the dynamic-form step (addresses_form): validates
     * guest registration and address selection, fills the error feedback store
     * and persists selected addresses to the cartstore.
     *
     * @param array $data Merged step data (selectedaddress_*, guest_*).
     * @return array ['data' => array, 'mandatory' => bool, 'valid' => bool]
     */
    public function evaluate_step(array $data): array {
        global $USER;

        self::$lasterrors = [];
        $requiredaddresskeys = self::get_required_address_keys();

        $guestvalid = true;
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user($USER->id)
        ) {
            $guesterror = self::get_guest_registration_error($data);
            $guestvalid = ($guesterror === '');
            if (!$guestvalid) {
                self::$lasterrors[] = $guesterror;
                // Show the error inline below the e-mail field on the next body render.
                $data['guest_email_error'] = $guesterror;
            } else {
                unset($data['guest_email_error']);
            }
        }

        $addressesvalid = self::is_valid($data, $requiredaddresskeys);
        if (!$addressesvalid) {
            foreach (self::get_required_address_data() as $requiredaddress) {
                if (empty($data['selectedaddress_' . $requiredaddress['addresskey']])) {
                    self::$lasterrors[] = get_string(
                        'addresses:feedback',
                        'local_shopping_cart',
                        $requiredaddress['addresslabel']
                    );
                }
            }
        }

        return [
            'data'      => $data,
            'mandatory' => self::is_mandatory(),
            'valid'     => $addressesvalid && $guestvalid,
        ];
    }

    /**
     * Adapts the legacy changedinput (JSON array of {name,value}) to the data
     * shape expected by evaluate_step(). Starts from the already cached step
     * data so that several submissions (e.g. billing, then shipping) accumulate.
     *
     * @param mixed $changedinput
     * @return array
     */
    public function parse_changed_input($changedinput): array {
        global $USER;

        $cache = \local_shopping_cart\local\checkout_process\checkout_manager::get_cache((int)self::$identifier);
        $data = $cache['steps']['addresses']['data'] ?? [];
        if (!is_array($data)) {
            $data = [];
        }

        $decoded = json_decode($changedinput);
        $validationdata = is_array($decoded) ? $decoded : [];

        // Collect selected address fields.
        foreach (self::get_required_address_keys() as $requiredaddresskey) {
            $requiredinputname = 'selectedaddress_' . $requiredaddresskey;
            foreach ($validationdata as $address) {
                if (isset($address->name) && $address->name === $requiredinputname) {
                    $data[$address->name] = $address->value;
                }
            }
        }

        // Collect guest registration fields.
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user($USER->id)
        ) {
            foreach ($validationdata as $input) {
                if (isset($input->name) && in_array($input->name, ['guest_firstname', 'guest_lastname', 'guest_email'])) {
                    $data[$input->name] = clean_param($input->value ?? '', PARAM_TEXT);
                }
            }
        }

        return $data;
    }

    /**
     * Validation feedback shown when the step is invalid.
     *
     * @return string
     */
    public static function get_error_feedback(): string {
        return implode('<br>', self::$lasterrors);
    }

    /**
     * Validates the guest-registration fields stored in the step data.
     *
     * @param array $data The cached step data containing guest_firstname, guest_lastname, guest_email.
     * @return bool
     */
    public static function is_guest_registration_valid(array $data): bool {
        return self::get_guest_registration_error($data) === '';
    }

    /**
     * Validates the guest-registration fields and returns a localized error message.
     *
     * @param array $data The cached step data containing guest_firstname, guest_lastname, guest_email.
     * @return string Empty string when valid, otherwise the localized error.
     */
    public static function get_guest_registration_error(array $data): string {
        global $CFG, $DB;

        $firstname = trim($data['guest_firstname'] ?? '');
        $lastname  = trim($data['guest_lastname'] ?? '');
        $email     = trim($data['guest_email'] ?? '');

        if (empty($firstname) || empty($lastname) || empty($email)) {
            return get_string('guestcheckout:errormissingfields', 'local_shopping_cart');
        }

        if (!validate_email($email)) {
            return get_string('guestcheckout:errorinvalidemail', 'local_shopping_cart');
        }

        // Reject if the e-mail is already registered to a different, non-guest
        // account. Like core (login/lib.php), compare case-insensitively and
        // tolerate multiple matches when accounts may share an e-mail.
        $existing = $DB->get_record_select(
            'user',
            $DB->sql_equal('email', ':email', false) . " AND deleted = 0 AND mnethostid = :mnethostid",
            ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id],
            '*',
            IGNORE_MULTIPLE
        );
        if ($existing && !guestcheckout::is_guest_checkout_user((int)$existing->id)) {
            return get_string('guestcheckout:emailexists', 'local_shopping_cart', [
                'loginurl' => (new \moodle_url('/login/index.php'))->out(false),
            ]);
        }

        return '';
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @param array $data     The cached step data (key-value pairs for selected addresses / guest fields).
     * @param array $requiredaddresskeys The address-type keys that must be present (e.g. ['billing', 'shipping']).
     *
     * @return bool
     *
     */
    public function is_valid(
        $data,
        $requiredaddresskeys
    ): bool {
        // When addresses are required, validate them normally.
        if (!empty($requiredaddresskeys)) {
            foreach ($requiredaddresskeys as $requiredaddresskey) {
                if (empty($data['selectedaddress_' . $requiredaddresskey])) {
                    return false;
                }
            }

            if (!$this->is_address_valid($requiredaddresskeys, $data)) {
                return false;
            }

            $cartstore = cartstore::instance(self::$identifier);
            $cartstoredata = [];
            if (!empty($data["selectedaddress_billing"])) {
                $cartstoredata['billing'] = $data["selectedaddress_billing"];
            }
            if (!empty($data["selectedaddress_shipping"])) {
                $cartstoredata['shipping'] = $data["selectedaddress_shipping"];
            }
            $cartstore->local_shopping_cart_save_address_in_cache($cartstoredata);
        }
        // When the step is shown only for guest registration (no addresses required),
        // we consider address validation passed if there are no required keys.
        return true;
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @param array $requiredaddresskeys
     * @param array $data
     *
     * @return bool
     *
     */
    private static function is_address_valid(
        $requiredaddresskeys,
        $data
    ): bool {
        $addressesfromdb = address_operations::get_all_user_addresses(self::$identifier);
        foreach ($requiredaddresskeys as $requiredaddresskey) {
            $selectedid = (int)($data['selectedaddress_' . $requiredaddresskey] ?? 0);
            if (empty($selectedid) || !isset($addressesfromdb[$selectedid])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build data for compact login + SSO options in guest checkout.
     *
     * @param string $wantsurl
     * @return array
     */
    private static function get_login_options_data(string $wantsurl = ''): array {
        global $PAGE, $CFG;

        // Core auth login renderable relies on auth_plugin_base and helpers from authlib.
        if (!class_exists('auth_plugin_base')) {
            require_once($CFG->libdir . '/authlib.php');
        }

        if (!class_exists('auth_plugin_base')) {
            return [
                'loginurl' => '',
                'logintoken' => '',
                'canloginbyemail' => false,
                'forgotpasswordurl' => '',
                'hasidentityproviders' => false,
                'identityproviders' => [],
                'cansignup' => false,
                'signupurl' => '',
            ];
        }

        $corerenderer = $PAGE->get_renderer('core');
        $authsequence = get_enabled_auth_plugins();
        $loginform = new login_renderable($authsequence);
        $logindata = (array)$loginform->export_for_template($corerenderer);

        return [
            'loginurl' => (new \moodle_url('/login/index.php'))->out(false),
            'logintoken' => $logindata['logintoken'] ?? '',
            'wantsurl' => $wantsurl ?: self::get_post_login_wantsurl(),
            'canloginbyemail' => $logindata['canloginbyemail'] ?? false,
            'forgotpasswordurl' => $logindata['forgotpasswordurl'] ?? '',
            'hasidentityproviders' => $logindata['hasidentityproviders'] ?? false,
            'identityproviders' => $logindata['identityproviders'] ?? [],
            'cansignup' => $logindata['cansignup'] ?? false,
            'signupurl' => $logindata['signupurl'] ?? '',
        ];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     * @return bool
     *
     */
    public function get_info_feedback(): string {
        global $USER;
        $carstore = cartstore::instance($USER->id);

        if (!$carstore->has_items()) {
            return get_string('cartisempty', 'local_shopping_cart');
        }

        $feedback = [];
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user((int)$USER->id)
        ) {
            $feedback[] = get_string('guestcheckout:feedbackregister', 'local_shopping_cart');
        }
        $requiredaddresses = self::get_required_address_data();
        foreach ($requiredaddresses as $requiredaddress) {
            $feedback[] = get_string('addresses:feedback', 'local_shopping_cart', $requiredaddress['addresslabel']);
        }
        if (empty($feedback)) {
            return '';
        }
        return implode('<br>', $feedback);
    }
}
