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
 * Mock class for vat checking with external platforms.
 *
 * @package    local_shopping_cart
 * @copyright  2025 Wunderbyte Gmbh <info@wunderbyte.at>
 * @author     Andrii Semenets
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\tests;

/**
 * Mock class for vat checking with external platforms.
 *
 * @package    local_shopping_cart
 * @copyright  2025 Wunderbyte Gmbh <info@wunderbyte.at>
 * @author     Andrii Semenets
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class SoapClientMock {
    /**
     * Make ctor harmless — do NOT call parent SoapClient constructor
     */
    public function __construct() {
    }

    /**
     * Declare the method so PHPUnit's onlyMethods() sees it.
     * It is empty — we'll mock it in the test.
     */
    public function checkVat($params) { // phpcs:ignore
    }
}
