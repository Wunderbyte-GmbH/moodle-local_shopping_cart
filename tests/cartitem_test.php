<?php

namespace local_shopping_cart;

use local_shopping_cart\local\entities\cartitem;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class cartitem_test extends TestCase {
    public function test_tax_absolute() {
        $totalTax = 2.00;
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description',
                null,
                null,
                $totalTax);

        $this->assertEquals($totalTax, $cartitem->tax_amount());
        $this->assertEquals($price - $totalTax, $cartitem->net_price());
    }

    public function test_tax_absolute_zero_amount() {
        $totalTax = 0.00;
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description',
                null,
                null,
                $totalTax);

        $this->assertNotNull($cartitem->tax_amount());  // in php 0.0 is equal to null, that's why we have to check for non-null
        $this->assertEquals($totalTax, $cartitem->tax_amount());
        $this->assertEquals($price, $cartitem->net_price());
    }

    public function test_tax_percentage() {
        $taxpercent = 0.20;
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description',
                null,
                null,
                null,
                $taxpercent);

        $this->assertEquals(2.00, $cartitem->tax_amount());
        $this->assertEquals($price - ($price * $taxpercent), $cartitem->net_price());
    }

    public function test_tax_percentage_zero() {
        $taxpercent = 0.0;
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description',
                null,
                null,
                null,
                $taxpercent);

        $this->assertNotNull($cartitem->tax_amount()); // in php 0.0 is equal to null, that's why we have to check for non-null
        $this->assertEquals(0.00, $cartitem->tax_amount());
        $this->assertEquals($price, $cartitem->net_price());
    }

    public function test_tax_null_values() {
        $price = 10.00;
        $cartitem = new cartitem(1,
                'Testitem 1',
                $price,
                'EUR',
                'local_shopping_cart',
                'My Testitem 1 description');

        $this->assertNull($cartitem->tax_amount());
        $this->assertEquals($price, $cartitem->net_price());
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