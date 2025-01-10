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
 * phpUnit taxcategories_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\vatnrchecker;
use PHPUnit\Framework\TestCase;
/**
 * Test for taxcategories
 * @covers \taxcategories
 */
final class vatnrchecker_test extends TestCase {
    /**
     * Test vatnrchecker - invalid eu
     * @covers \vatnrchecker::check_vatnr_number
     * @return void
     */
    public function test_invalid_at_vat_number(): void {
        $countrycode = 'AT';
        $vatnrnumber = '123456789';

        $checkvatnr = vatnrchecker::check_vatnr_number($countrycode, $vatnrnumber);

        $this->assertFalse($checkvatnr);
    }

    /**
     * Test vatnrchecker - valid eu
     * @covers \vatnrchecker::check_vatnr_number
     * @return void
     */
    public function test_valid_at_vat_number(): void {
        $countrycode = 'AT';
        $vatnrnumber = 'U74259768';

        $checkvatnr = vatnrchecker::check_vatnr_number($countrycode, $vatnrnumber);

        $this->assertTrue($checkvatnr);
    }

    /**
     * Test vatnrchecker - invalid eu
     * @covers \vatnrchecker::check_vatnr_number
     * @return void
     */
    public function test_invalid_gb_vat_number(): void {
        $countrycode = 'GB';
        $vatnrnumber = '123456789';

        $checkvatnr = vatnrchecker::check_vatnr_number($countrycode, $vatnrnumber);

        $this->assertFalse($checkvatnr);
    }

    /**
     * Test vatnrchecker - valid eu
     * @covers \vatnrchecker::check_vatnr_number
     * @return void
     */
    public function test_valid_gb_vat_number(): void {
        $countrycode = 'GB';
        $vatnrnumber = '100079899';
        $checkvatnr = vatnrchecker::check_vatnr_number($countrycode, $vatnrnumber);
        $this->assertTrue($checkvatnr);
    }
}
