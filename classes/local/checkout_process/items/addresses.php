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
     * Renders checkout item.
     * @return string
     */
    public static function get_icon_progress_bar(): string {
        return 'fa-solid fa-address-book';
    }

    /**
     * Renders checkout item.
     * @param array $cachedata
     * @return array
     */
    public static function render_body($cachedata): array {
        global $PAGE, $USER, $SESSION;
        $renderer = $PAGE->get_renderer('local_shopping_cart');
        $requiredaddresskeys = self::get_required_address_keys();
        $isguestcheckoutuser = (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user($USER->id)
        );

        $template = '';

        // When the current user is a guest checkout user, prepend the registration form.
        if ($isguestcheckoutuser) {
            $wantsurl = self::get_post_login_wantsurl();

            $SESSION->local_shopping_cart_guest_login_context = [
                'guestuserid' => (int)$USER->id,
                'timecreated' => time(),
                'source' => 'checkout',
            ];
            $SESSION->wantsurl = $wantsurl;

            $cacheddata = $cachedata['data'] ?? [];
            $guestdata = [
                'guest_firstname'   => $cacheddata['guest_firstname'] ?? '',
                'guest_lastname'    => $cacheddata['guest_lastname'] ?? '',
                'guest_email'       => $cacheddata['guest_email'] ?? '',
                'guest_email_error' => $cacheddata['guest_email_error'] ?? '',
                'loginoptions'      => self::get_login_options_data($wantsurl),
                'showaddresspanel'  => !empty($requiredaddresskeys),
            ];
            $template .= $renderer->render_from_template(
                'local_shopping_cart/guest_registration_form',
                $guestdata
            );
        }

        // Render the address selection section only when addresses are configured.
        if (!empty($requiredaddresskeys)) {
            $data = self::get_template_render_data();
            $data['required_addresses'] = self::set_data_from_cache(
                $data['required_addresses'],
                $cachedata['data'] ?? []
            );
            $data['is_guest_checkout_user'] = $isguestcheckoutuser;
            $template .= $renderer->render_from_template("local_shopping_cart/address", $data);
        }

        return [
            'template' => $template,
        ];
    }

    /**
     * Generates the data for rendering the templates/address.mustache template.
     * @param array $requiredaddresses
     * @param array $cachedata
     */
    public static function set_data_from_cache(&$requiredaddresses, $cachedata) {
        foreach ($requiredaddresses as &$requiredaddress) {
            $newsavedaddresses = [];
            foreach ($requiredaddress['saved_addresses'] as $savedaddress) {
                $savedaddresscopy = clone $savedaddress;
                if (
                    $savedaddresscopy->id == ($cachedata['selectedaddress_' . $requiredaddress['addresskey']] ?? 0)
                ) {
                    $savedaddresscopy->selected = true;
                } else {
                    unset($savedaddresscopy->selected);
                }
                $newsavedaddresses[] = $savedaddresscopy;
            }
            $requiredaddress['saved_addresses'] = $newsavedaddresses;
        }
        return $requiredaddresses;
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

        return [];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @param mixed $managercachestep
     * @param mixed $validationdata
     *
     * @return array list of all required address keys
     *
     */
    public function check_status(
        $managercachestep,
        $validationdata
    ): array {
        global $USER;

        $data = $managercachestep['data'] ?? [];
        $requiredaddresskeys = self::get_required_address_keys();
        $decoded = json_decode($validationdata);
        $validationdata = is_array($decoded) ? $decoded : [];

        // Collect selected address fields.
        foreach ($requiredaddresskeys as $requiredaddresskey) {
            $requiredinputname = 'selectedaddress_' . $requiredaddresskey;
            foreach ($validationdata as $address) {
                if (
                    isset($address->name) &&
                    $address->name === $requiredinputname
                ) {
                    $data[$address->name] = $address->value;
                }
            }
        }

        // Collect and validate guest registration fields.
        $guestvalid = true;
        if (
            get_config('local_shopping_cart', 'guestoncheckout')
            && guestcheckout::is_guest_checkout_user($USER->id)
        ) {
            foreach ($validationdata as $input) {
                if (isset($input->name) && in_array($input->name, ['guest_firstname', 'guest_lastname', 'guest_email'])) {
                    $data[$input->name] = clean_param($input->value ?? '', PARAM_TEXT);
                }
            }
            $guestvalid = self::is_guest_registration_valid($data);
        }

        return [
            'data'      => $data,
            'mandatory' => self::is_mandatory(),
            'valid'     => self::is_valid($data, $requiredaddresskeys) && $guestvalid,
        ];
    }

    /**
     * Validates the guest-registration fields stored in the step data.
     *
     * @param array $data The cached step data containing guest_firstname, guest_lastname, guest_email.
     * @return bool
     */
    public static function is_guest_registration_valid(array $data): bool {
        global $DB;

        $firstname = trim($data['guest_firstname'] ?? '');
        $lastname  = trim($data['guest_lastname'] ?? '');
        $email     = trim($data['guest_email'] ?? '');

        if (empty($firstname) || empty($lastname) || empty($email)) {
            return false;
        }

        if (!validate_email($email)) {
            return false;
        }

        // Reject if the e-mail is already registered to a different, non-guest account.
        $existing = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        if ($existing && !guestcheckout::is_guest_checkout_user($existing->id)) {
            return false;
        }

        return true;
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
