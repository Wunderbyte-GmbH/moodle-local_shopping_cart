<?php
// This file is part of Moodle - http://moodle.org/.
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
 * Unit tests for the checkout_manager class.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\classes;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper;
use SoapClient;

/**
 * Test for vatnumberhelper
 *
 * @covers \local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper
 */
final class vatnumberhelper_test extends advanced_testcase {
    /**
     * Soap Mock instance.
     *
     * @var object
     */
    protected object $soapmock;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Mock SoapClient.
        $this->soapmock = $this->getMockBuilder(SoapClient::class)
            ->setConstructorArgs(["https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl"])
            ->setMethods(['checkVat'])
            ->getMock();
    }

    /**
     * Test the get_countrycodes_array method.
     */
    public function test_get_countrycodes_array(): void {
        $countries = vatnumberhelper::get_countrycodes_array();

        $this->assertIsArray($countries, 'Expected get_countrycodes_array to return an array.');
        $this->assertArrayHasKey('AT', $countries, 'Expected country code "AT" in the array.');
        $this->assertArrayHasKey('DE', $countries, 'Expected country code "DE" in the array.');
        $this->assertEquals('Austria', $countries['AT'], 'Expected "AT" to map to "Austria".');
    }

    /**
     * Test the check_vatnr_number method.
     */
    public function test_invalid_check_vatnr_number(): void {
        $this->soapmock->method('checkVat')->willReturn(['valid' => false]);
        $result = vatnumberhelper::is_vatnr_valid('DE', '123456789', $this->soapmock);
        $this->assertFalse($result, 'Expected check_vatnr_number to return true for a valid VAT number.');
    }

    /**
     * Test the check_vatnr_number method.
     */
    public function test_valid_check_vatnr_number(): void {
        $this->soapmock->method('checkVat')->willReturn(['valid' => true]);
        $result = vatnumberhelper::is_vatnr_valid('AT', 'ATU74259768', $this->soapmock);
        $this->assertTrue($result, 'Expected check_vatnr_number to return true for a valid VAT number.');
    }

    /**
     * Test the validate_with_hmrc method.
     */
    public function test_validate_with_hmrc(): void {
        $result = vatnumberhelper::validate_with_hmrc('123456789');
        $this->assertIsArray($result, 'Expected validate_with_hmrc to return an array.');
        $this->assertArrayHasKey('valid', $result, 'Expected array to have key "valid".');
    }
}
