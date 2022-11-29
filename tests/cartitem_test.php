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

namespace local_shopping_cart;

use local_shopping_cart\local\entities\cartitem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class cartitem_test extends TestCase {

    public function test_taxcategory_not_set() {
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description');

        $this->assertNull($cartitem->tax_category());
    }

    public function test_taxcategory_set() {
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description',
                '',
                null,
                null,
                null,
                'A');

        $this->assertEquals('A', $cartitem->tax_category());
    }

    public function test_as_array_contains_all_fields() {
        $reflection = new ReflectionClass(cartitem::class);
        $defined_properties = $reflection->getProperties();

        $cartitem = new cartitem(1,
                'Testitem 1',
                10.0,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description',
        );

        $cartitem_array = $cartitem->as_array();

        $this->assertIsArray($cartitem_array);
        foreach ($defined_properties as $property) {
            $this->assertArrayHasKey($property->getName(), $cartitem_array);
        }
    }
}