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
 * phpUnit cartitem_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

/**
 * A way to mock time()
 */
class time_mock {
    /**
     * The time which should be returned.
     *
     * @var int
     */
    private static $mocktime = 0;

    /**
     * Sets the mock time.
     *
     * @param mixed $timestamp
     *
     */
    public static function set_mock_time($timestamp) {

        if (empty($timestamp)) {
            $timestamp = time();
        }

        self::$mocktime = $timestamp;
    }

    /**
     * Resets the mock time.
     *
     */
    public static function reset_mock_time() {
        self::$mocktime = null;
    }

    /**
     * Returns the mock time.
     *
     * @return int
     *
     */
    public static function get_mock_time() {

        if (empty(self::$mocktime)) {
            self::$mocktime = 1000000000;
        }

        return self::$mocktime;
    }
}
