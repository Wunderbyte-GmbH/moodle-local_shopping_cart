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
use local_shopping_cart\output\shoppingcart_history_list;
use stdClass;

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

        $output = $PAGE->get_renderer('local_shopping_cart');
        $out = $output->render_history_card($historylist);

        return $out;
    }

}
