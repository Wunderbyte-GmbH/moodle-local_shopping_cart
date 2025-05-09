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

use advanced_testcase;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper;
use local_shopping_cart\local\vatnrchecker;
use PHPUnit\Framework\TestCase;
use SoapClient;
/**
 * Test for taxcategories
 * @covers \taxcategories
 */
final class vatnrchecker_test extends advanced_testcase {
    /**
     * Soap Mock instance.
     *
     * @var object
     */
    protected object $soapmock;

    /**
     * Tests set up.
     */
    public function setUp(): void {
        parent::setUp();
        // Mock SoapClient.
        $this->soapmock = $this->getMockBuilder(SoapClient::class)
            ->setConstructorArgs(["https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl"])
            ->setMethods(['checkVat'])
            ->getMock();
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        // Mandatory clean-up.
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    /**
     * Test vatnrchecker - invalid eu
     * @covers \vatnrchecker::check_vatnr_number
     * @return void
     */
    public function test_invalid_at_vat_number(): void {
        $countrycode = 'AT';
        $vatnrnumber = '123456789';

        $this->soapmock->method('checkVat')->willReturn(['valid' => false]);

        $checkvatnr = vatnumberhelper::is_vatnr_valid($countrycode, $vatnrnumber, $this->soapmock);

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

        $this->soapmock->method('checkVat')->willReturn(['valid' => true]);

        $checkvatnr = vatnumberhelper::is_vatnr_valid($countrycode, $vatnrnumber, $this->soapmock);

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

        $checkvatnr = vatnumberhelper::is_vatnr_valid($countrycode, $vatnrnumber);

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
        $checkvatnr = vatnumberhelper::is_vatnr_valid($countrycode, $vatnrnumber);
        $this->assertTrue($checkvatnr);
    }
}
