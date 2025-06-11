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
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\entities\cartitem;
use tool_mocktesttime\time_mock;
use ReflectionClass;

/**
 * Test for cartitem
 * @covers \local_shopping_cart\local\entities\cartitem
 */
final class cartitem_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        time_mock::init();
        time_mock::set_mock_time(strtotime('now'));
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        // Mandatory clean-up.
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
        time_mock::reset_mock_time();
    }

    /**
     * Test taxcategory not set
     * @covers \local_shopping_cart\local\entities\cartitem
     *
     * @return [type]
     */
    public function test_taxcategory_not_set(): void {
        $price = 10.00;
        $cartitem = new cartitem(
            1,
            'Testitem 1',
            $price,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'main',
            'My Testitem 1 description'
        );

        $this->assertNull($cartitem->tax_category());
    }

    /**
     * Test taxcategory set
     * @covers \local_shopping_cart\local\entities\cartitem
     *
     * @return [type]
     */
    public function test_taxcategory_set(): void {
        $price = 10.00;
        $cartitem = new cartitem(
            1,
            'Testitem 1',
            $price,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'main',
            'My Testitem 1 description',
            '',
            null,
            null,
            null,
            'A'
        );

        $this->assertEquals('A', $cartitem->tax_category());
    }

    /**
     * Test array contains all fields
     * @covers \local_shopping_cart\local\entities\cartitem
     *
     * @return [type]
     */
    public function test_as_array_contains_all_fields(): void {
        $reflection = new ReflectionClass(cartitem::class);
        $definedproperties = $reflection->getProperties();

        $cartitem = new cartitem(
            1,
            'Testitem 1',
            10.0,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'main',
            'My Testitem 1 description',
        );

        $cartitemarray = $cartitem->as_array();

        $this->assertIsArray($cartitemarray);
        foreach ($definedproperties as $property) {
            $this->assertArrayHasKey($property->getName(), $cartitemarray);
        }
    }

    /**
     * Test costcenter not set
     * @covers \local_shopping_cart\local\entities\cartitem
     *
     * @return void
     */
    public function test_costcenter_is_set(): void {
        $price = 10.00;
        $cartitem = new cartitem(
            1,
            'Testitem 1',
            $price,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'main',
            'My Testitem 1 description'
        );

        $this->assertNull($cartitem->costcenter);

        $costcenter = 'TestitemCostcenter';
        $cartitem = new cartitem(
            1,
            'Testitem 1',
            $price,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'main',
            'My Testitem 1 description',
            '',
            null,
            null,
            null,
            null,
            0,
            $costcenter
        );

        $this->assertEquals($costcenter, $cartitem->costcenter);
    }
}
