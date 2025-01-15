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

use local_shopping_cart\local\checkout_process\checkout_base_item;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_credits extends checkout_base_item {
    /**
     * Renders checkout item.
     * @return string
     */
    public static function get_icon_progress_bar() {
        return 'fa-solid fa-coins';
    }

    /**
     * Renders checkout item.
     */
    public static function is_head() {
        return true;
    }

    /**
     * Renders checkout item.
     */
    public static function is_mandatory() {
        return false;
    }

    /**
     * Renders checkout item.
     * @return array
     */
    public static function render_body($cachedata) {
        global $PAGE;
        $template = $PAGE->get_renderer('local_shopping_cart')
            ->render_from_template("local_shopping_cart/shopping_cart_credits", $cachedata);
        return [
            'template' => $template,
        ];
    }

    /**
     * Returns the required-address keys as specified in the plugin config.
     *
     * @return array list of all required address keys
     */
    public static function check_status(
        $managercachestep,
        $changedinput
    ): array {
        return [
            'data' => '',
            'mandatory' => self::is_mandatory(),
            'valid' => true,
        ];
    }
}
