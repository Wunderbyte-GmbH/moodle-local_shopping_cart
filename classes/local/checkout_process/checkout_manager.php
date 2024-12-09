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

namespace local_shopping_cart\local\checkout_process;

use Exception;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checkout_manager {
    /**
     * Applies the given price modifiers on the cached data.
     */
    public static function render_overview() {
        global $CFG;
        $checkoutmanager = [
            'checkout_manager_head' => [],
            'checkout_manager_body' => [],
        ];
        $namespaceprefix = 'local_shopping_cart\\local\\checkout_process\\items\\';
        $path = $CFG->dirroot . '/local/shopping_cart/classes/local/checkout_process/items/*';
        $itemlist = glob($path . '*.php');
        $hasactivestep = false;
        foreach ($itemlist as $item) {
            $filename = basename($item, '.php');
            $classname = $namespaceprefix . $filename;
            if (self::class_exists_is_active($classname)) {
                $iteminstance = new $classname();
                $renderdestination = $iteminstance->is_head() ? 'head' : 'body';
                if ($iteminstance->get_status_progress_bar() == 'active') {
                    $hasactivestep = true;
                }
                $checkoutmanager['checkout_manager_' . $renderdestination]['item_list'][] = [
                    'item' => $iteminstance->get_icon_progress_bar(),
                    'status' => $iteminstance->get_status_progress_bar(),
                    'classname' => $classname,
                ];
            }
        }
        if (!$hasactivestep) {
            self::set_first_step_active($checkoutmanager['checkout_manager_body']['item_list']);
        }
        if (self::has_multiple_items($checkoutmanager['checkout_manager_body'])) {
            $checkoutmanager['checkout_manager_body']['buttons'] =
                self::render_checkout_buttons($checkoutmanager['checkout_manager_body']['item_list']);
        }

        $checkoutmanager['checkout_manager_body']['body'] =
            self::render_checkout_body($checkoutmanager['checkout_manager_body']['item_list']);
        return $checkoutmanager;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $itemlist
     */
    public static function render_checkout_body($itemlist) {
        try {
            foreach ($itemlist as $item) {
                if ($item['status'] == 'active') {
                    $iteminstance = new $item['classname']();
                    return $iteminstance->render_body();
                }
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $itemlist
     */
    public static function set_first_step_active(&$itemlist) {
        if (isset($itemlist[2])) {
            $itemlist[2]['status'] = 'active';
        }
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param array $classname
     * @return array
     */
    public static function render_checkout_buttons($itemlist) {
        $firstcheckoutitem = reset($itemlist);
        $lastcheckoutitem = end($itemlist);
        $previousbutton = [
            'text' => get_string('previousbutton', 'local_shopping_cart'),
            'hidden' => $firstcheckoutitem['status'] == 'active' ? true : false,
        ];
        $nextbutton = [
            'text' => get_string('nextbutton', 'local_shopping_cart'),
            'hidden' => $lastcheckoutitem['status'] == 'active' ? true : false,
        ];

        return [
            'previous_button' => $previousbutton,
            'next_button' => $nextbutton,
        ];
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return bool
     */
    public static function class_exists_is_active($classname) {
        if (class_exists($classname)) {
            $iteminstance = new $classname();
            if ($iteminstance->is_active()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applies the given price modifiers on the cached data.
     * @param string $classname
     * @return bool
     */
    public static function has_multiple_items($body) {
        if (
            isset($body['item_list']) &&
            count($body['item_list']) > 1
        ) {
            return true;
        }
        return false;
    }
}
