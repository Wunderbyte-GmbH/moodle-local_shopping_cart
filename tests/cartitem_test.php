<?php

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