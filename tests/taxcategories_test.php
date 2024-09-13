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

use local_shopping_cart\local\entities\cartitem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test for taxcategories
 * @covers \taxcategories
 */
final class taxcategories_test extends TestCase {

    /**
     * Test complex raw string is valid: taxcategories::is_valid_raw_string()
     * @covers \taxcategories::is_valid_raw_string
     *
     * @return [type]
     */
    public function test_complex_raw_string_is_valid(): void {
        // We test different valid permutations of a complex configuration string.
        $validrawstring = 'at A:20 B:10 C:0
    de A:19 B:10 C:0
    default A:0 B:0 C:0';
        $this->assertTrue(taxcategories::is_valid_raw_string($validrawstring));

        $validrawstring = 'at A:20 B:10 C:0
    de C:0
    default A:0 B:0 C:0';
        $this->assertTrue(taxcategories::is_valid_raw_string($validrawstring));

        $validrawstring = '
    at A:20 B:10 C:0
    de C:0
    default A:0 B:0 C:0';
        $this->assertTrue(taxcategories::is_valid_raw_string($validrawstring));

        $validrawstring = 'default A:0 B:0 C:0
    at A:20
    de C:0';
        $this->assertTrue(taxcategories::is_valid_raw_string($validrawstring));
    }

    /**
     * Test complex raw string without default category is invalid: taxcategories::is_valid_raw_string()
     * @covers \taxcategories::is_valid_raw_string
     *
     * @return [type]
     */
    public function test_complex_raw_string_without_default_category_is_invalid(): void {
        $invalidrawstring = 'at A:20 B:10 C:0
    de A:19 B:10 C:0
    other A:0 B:0 C:0';
        $this->assertFalse(taxcategories::is_valid_raw_string($invalidrawstring));
    }

    /**
     * Test empty raw string is invalid
     * @covers \taxcategories::is_valid_raw_string
     *
     * @return [type]
     */
    public function test_empty_raw_string_is_invalid(): void {
        $raw = '';
        $this->assertFalse(taxcategories::is_valid_raw_string($raw));
    }

    /**
     * Test single value raw string is valid
     * @covers \taxcategories::is_valid_raw_string
     *
     * @return [type]
     */
    public function test_single_value_raw_string_is_valid(): void {
        $raw = '20';
        $this->assertTrue(taxcategories::is_valid_raw_string($raw));
    }

    /**
     * Test single line raw string is valid
     * @covers \taxcategories::is_valid_raw_string
     *
     * @return [type]
     */
    public function test_single_line_raw_string_is_valid(): void {
        $raw = 'A:20 B:10 C:0';
        $this->assertTrue(taxcategories::is_valid_raw_string($raw), "'$raw' is not a valid raw string");
    }

    /**
     * Test multi line raw string is valid
     * @covers \taxcategories::is_valid_raw_string
     *
     * @return [type]
     */
    public function test_multi_line_raw_string_is_valid(): void {
        $raw = "at A:20 B:10 C:0\nde A:19 B:9 C:0\ndefault A:0 B:0 C:0";
        $this->assertTrue(taxcategories::is_valid_raw_string($raw), "'$raw' is not a valid raw string");
    }

    /**
     * Test single line
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_single_line(): void {
        $raw = 'A:20 B:10 C:0';
        $fromraw = taxcategories::from_raw_string("A", $raw);

        $this->assertEquals("A", $fromraw->defaultcategory(), "Unexpected default category");
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $fromraw->validcategories());
        $expected = [
                taxcategories::LOCAL_SHOPPING_CART_DEFAULT_COUNTRY_INDEX => ["A" => 0.2, "B" => 0.1, "C" => 0.0],
        ];
        $this->assertEqualsCanonicalizing($expected, $fromraw->taxmatrix());
    }

    /**
     * Test single value empty default category
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_single_value_empty_default_category(): void {
        $raw = '20';
        $fromraw = taxcategories::from_raw_string("", $raw);

        $this->assertEquals(taxcategories::LOCAL_SHOPPING_CART_DEFAULT_CATEGORY_KEY,
            $fromraw->defaultcategory(), "Unexpected default category");
        $this->assertEqualsCanonicalizing([taxcategories::LOCAL_SHOPPING_CART_DEFAULT_CATEGORY_KEY], $fromraw->validcategories());
        $expected = [
            taxcategories::LOCAL_SHOPPING_CART_DEFAULT_COUNTRY_INDEX => [
                taxcategories::LOCAL_SHOPPING_CART_DEFAULT_CATEGORY_KEY => 0.2,
            ],
        ];
        $this->assertEqualsCanonicalizing($expected, $fromraw->taxmatrix());
    }

    /**
     * Test multi line
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_multi_line(): void {
        $raw = '
        default A:0 B:0 C:0
        at A:20 B:10 C:0';
        $fromraw = taxcategories::from_raw_string("A", $raw);

        $this->assertEquals("A", $fromraw->defaultcategory(), "Unexpected default category");
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $fromraw->validcategories());
        $expected = [
                taxcategories::LOCAL_SHOPPING_CART_DEFAULT_COUNTRY_INDEX => ["A" => 0, "B" => 0, "C" => 0.0],
                "at" => ["A" => 0.2, "B" => 0.1, "C" => 0.0],
        ];
        $this->assertEqualsCanonicalizing($expected, $fromraw->taxmatrix());
    }

    /**
     * Test tax for category no country code
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_tax_for_category_no_country_code(): void {
        $raw = 'A:25 B:10 C:1';
        $taxcategories = taxcategories::from_raw_string("A", $raw);

        $this->assertEquals(0.25, $taxcategories->tax_for_category("A"));
        $this->assertEquals(0.10, $taxcategories->tax_for_category("B"));
        $this->assertEquals(0.01, $taxcategories->tax_for_category("C"));

        // Unknown category.
        $this->assertEquals(-1, $taxcategories->tax_for_category("X"));
    }

    /**
     * Test tax for no category no country code
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_tax_for_no_category_no_country_code(): void {
        $raw = 'A:25 B:10 C:1';
        $taxcategories = taxcategories::from_raw_string("A", $raw);

        // Default category is used.
        $this->assertEquals(0.25, $taxcategories->tax_for_category(""));
    }

    /**
     * Test tax for no category but country code
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_tax_for_no_category_but_country_code(): void {
        $raw = 'default A:25
        mycountry A:30';
        $taxcategories = taxcategories::from_raw_string("A", $raw);

        $this->assertEquals(0.30, $taxcategories->tax_for_category("", "mycountry"));
        $this->assertEquals(0.25, $taxcategories->tax_for_category("", "other"));
    }

    /**
     * Test tax for category and country code
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_tax_for_category_and_country_code(): void {
        $raw = 'default A:25
        mycountry A:30';
        $taxcategories = taxcategories::from_raw_string("A", $raw);

        $this->assertEquals(0.3, $taxcategories->tax_for_category("A", "mycountry"));
        $this->assertEquals(0.25, $taxcategories->tax_for_category("A", "other"));
    }

    /**
     * Test tax for tax_for_category() and country code use defaults taxcategories::from_raw_string()
     * @covers \taxcategories::from_raw_string
     *
     * @return [type]
     */
    public function test_tax_for_category_and_country_code_use_default_fallback(): void {
        $raw = 'default A:25 C:10
        mycountry A:30';
        $taxcategories = taxcategories::from_raw_string("A", $raw);

        $this->assertEquals(0.1, $taxcategories->tax_for_category("C", "mycountry"));
        $this->assertEquals(0.1, $taxcategories->tax_for_category("C", "other"));
    }

}
