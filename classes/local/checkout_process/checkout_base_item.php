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

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class checkout_base_item {
    /**
     * Optional properties for the checkout manager.
     * @var string
     */
    public static $identifier;

    /**
     * Renders checkout item.
     *
     * @param string $identifier
     * @return bool
     */
    public function __construct(
        $identifier = null
    ) {
        self::$identifier = $identifier;
    }

    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_head(): bool {
        return false;
    }

    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_active(): bool {
        return true;
    }

    /**
     * Renders checkout item.
     * @param array $cachedata
     * @return array
     */
    public static function render_body($cachedata): array {
        return [];
    }

    /**
     * Checks status of checkout item.
     * @return bool
     */
    public static function check(): bool {
        return true;
    }

    /**
     * Checks status of checkout item.
     * @return string
     */
    public static function get_icon_progress_bar(): string {
        return 'fa-solid fa-cart-shopping';
    }

    /**
     * Checks status of checkout item.
     * @return string
     */
    public static function get_status_progress_bar(): string {
        return 'inactive';
    }

    /**
     * Validation feedback.
     * @return string
     */
    public static function get_validation_feedback(): string {
        return '';
    }

    /**
     * Validation feedback.
     * @return string
     */
    public static function get_error_feedback(): string {
        return '';
    }
}
