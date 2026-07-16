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
 * Shortcodes for local_shopping_cart
 *
 * @package local_shopping_cart
 * @subpackage db
 * @since Moodle 3.11
 * @copyright 2022 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_shopping_cart;

use context_system;
use local_shopping_cart\output\renderer;
use local_shopping_cart\output\embed_views;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\output\userinfocard;
use local_wunderbyte_table\local\helper\actforuser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Deals with local_shortcodes regarding booking.
 */
class shortcodes {
    /**
     * Prints out list of previous history items in a card..
     * Arguments can be 'userid'.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function shoppingcarthistory($shortcode, $args, $content, $env, $next) {

        global $USER, $PAGE;

        $foruserid = actforuser::get_foruserid($args);
        $hascashiercapability = has_capability('local/shopping_cart:cashier', context_system::instance());

        // Important security check.
        // The user must have the cashier capability to fetch data of other users.
        if (isset($args['userid']) && $hascashiercapability) {
            $userid = $args['userid'];
        } else if ($foruserid > 0 && $hascashiercapability) {
            $userid = $foruserid;
        } else {
            $userid = $USER->id;
        }

        // If the given user doesn't want to see the history for herself...
        // ... we check her permissions.
        if ($USER->id != $userid) {
            $context = context_system::instance();
            if (!has_capability('local/shopping_cart:cashier', $context, $USER)) {
                return '';
            }
        }

        if (!$historylist = new shoppingcart_history_list($userid)) {
            return '';
        }
        /** @var renderer $output */
        $output = $PAGE->get_renderer('local_shopping_cart');
        $out = $output->render_history_card($historylist);
        if (empty($out)) {
            $out = '';
        }
        return $out;
    }

    /**
     * Prints the userinfocard.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function userinfocard($shortcode, $args, $content, $env, $next): string {

        global $USER, $PAGE;

        self::fix_args($args);

        $userid = $args['userid'] ?? 0;
        // If the id argument was not passed on, we have a fallback in the config.
        $context = context_system::instance();
        if (empty($userid) && has_capability('local/shopping_cart:cashier', $context)) {
            // Check if rendering is for another user id.
            $userid = actforuser::get_foruserid($args, $USER->id);
        } else if (!has_capability('local/shopping_cart:cashier', $context)) {
            $userid = $USER->id;
        }

        if (!isset($args['fields'])) {
            $args['fields'] = '';
        }

        $data = new userinfocard($userid, $args['fields']);
        /** @var renderer $output */
        $output = $PAGE->get_renderer('local_shopping_cart');
        $out = $output->render_userinfocard($data);
        if (empty($out)) {
            $out = '';
        }
        return $out;
    }

    /**
     * Renders the compact cart badge (icon, item count, total) for embedding.
     *
     * Guarded by the shortcode security token: the configured token must be
     * passed in the securitytoken argument, otherwise a warning is shown. This
     * keeps the cart UI from being surfaced on arbitrary pages by anyone who
     * merely knows the shortcode tag. The optional checkouturl argument is
     * validated against the redirect allow list inside the renderable.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param \Closure $next
     * @return string
     */
    public static function cartbadge($shortcode, $args, $content, $env, $next): string {
        if ($warning = self::verify_shortcode_token($args)) {
            return $warning;
        }
        return embed_views::render_badge((array) $args);
    }

    /**
     * Renders the full cart checkout (stepper, VAT, credits, coupons) for embedding.
     *
     * Guarded by the shortcode security token exactly like {@see self::cartbadge()}.
     * The optional checkouturl/successurl arguments are validated against the
     * redirect allow list inside the renderable.
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param \Closure $next
     * @return string
     */
    public static function cartcheckout($shortcode, $args, $content, $env, $next): string {
        if ($warning = self::verify_shortcode_token($args)) {
            return $warning;
        }
        return embed_views::render_checkout((array) $args);
    }

    /**
     * Verifies the shortcode security token passed to a protected shortcode.
     *
     * Compares the securitytoken argument against the configured shortcodetoken
     * with a constant-time comparison. The check is deterministic and value
     * based (no language or phrase matching): an empty configured or provided
     * token, or a mismatch, yields a warning; a match yields null (render).
     *
     * @param array $args Shortcode arguments; the securitytoken key is read.
     * @return string|null Warning HTML if the token is missing or invalid, null when valid.
     */
    private static function verify_shortcode_token($args): ?string {
        $configuredtoken = (string) get_config('local_shopping_cart', 'shortcodetoken');
        $providedtoken = isset($args['securitytoken']) ? trim((string) $args['securitytoken']) : '';

        if ($configuredtoken === '' || $providedtoken === '') {
            return self::render_shortcode_warning('shortcode_warning_missing_securitytoken');
        }
        if (!hash_equals($configuredtoken, $providedtoken)) {
            return self::render_shortcode_warning('shortcode_warning_invalid_securitytoken');
        }
        return null;
    }

    /**
     * Renders a shortcode warning message as a bootstrap alert.
     *
     * @param string $stringidentifier Lang string key in local_shopping_cart.
     * @param mixed $a Optional lang string parameter.
     * @return string
     */
    private static function render_shortcode_warning(string $stringidentifier, $a = null): string {
        return '<div class="alert alert-warning local-shopping-cart-shortcode-warning" role="alert">'
            . s(get_string($stringidentifier, 'local_shopping_cart', $a))
            . '</div>';
    }

    /**
     * Helper function to remove quotation marks from args.
     *
     * @param array $args reference to arguments array
     */
    private static function fix_args(array &$args) {
        foreach ($args as $key => &$value) {
            // Get rid of quotation marks.
            $value = str_replace('"', '', $value);
            $value = str_replace("'", "", $value);
        }
    }
}
