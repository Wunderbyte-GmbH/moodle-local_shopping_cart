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

        // If the id argument was not passed on, we have a fallback in the connfig.

        if (!isset($args['userid'])) {
            $userid = $USER->id;
        } else {
            $userid = $args['userid'];
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
            if ($urlparamforuserid = actforuser::get_urlparamforuserid($args)) {
                $userid = optional_param($urlparamforuserid, 0, PARAM_INT);
                $userid = $userid > 0 ? $userid : $USER->id;
            } else {
                $userid = $USER->id;
            }
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
