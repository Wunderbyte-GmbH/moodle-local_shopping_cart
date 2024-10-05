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
 * phpUnit shopping_cart_credits_test class definitions.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\local\cartstore;
use PHPUnit\Framework\TestCase;
use phpunit_util;

/**
 * Test for shopping_cart_credits
 * @covers \shopping_cart_credits
 */
final class shopping_cart_credits_test extends TestCase {

    /**
     * Test shopping_cart_credits - single
     * @covers \shopping_cart_credits::add_credit
     * @covers \shopping_cart_credits::get_balance
     */
    public function test_shopping_cart_credits_simple_credits(): void {

        $user1 = $this->get_data_generator()->create_user();

        $balance0 = shopping_cart_credits::add_credit($user1->id, 10.10, 'EUR', '');

        // Check add_credit response.
        $this->assertIsArray($balance0);
        $this->assertEquals(10.1, $balance0[0]);
        $this->assertEquals('EUR', $balance0[1]);
        $this->assertEquals('', $balance0[2]);

        $balance1 = shopping_cart_credits::get_balance($user1->id);

        // Check get_balance response.
        $this->assertIsArray($balance1);
        $this->assertEquals(10.1, $balance1[0]);
        $this->assertEquals('EUR', $balance1[1]);
        $this->assertArrayNotHasKey(2, $balance1);

        // Check get_balance_for_all_costcenters response.
        $balance2 = shopping_cart_credits::get_balance_for_all_costcenters($user1->id);
        $this->assertIsArray($balance2);
        $balance2 = array_shift($balance2);
        $this->assertIsArray($balance2);
        $this->assertEquals(10.1, $balance2['balance']);
        $this->assertEquals('EUR', $balance2['currency']);
        $this->assertEquals('', $balance2['costcenter']);
        $this->assertEquals('', $balance2['costcenterlabel']);

        // Check add_credit and get_balance are the same.
        $this->assertEquals($balance0[0], $balance1[0]);
        $this->assertEquals($balance0[1], $balance1[1]);
        $this->assertEquals($balance0[0], $balance2['balance']);
        $this->assertEquals($balance0[1], $balance2['currency']);
    }

    public function test_shopping_cart_credits_costcenter_credits(): void {

        $user1 = $this->get_data_generator()->create_user();

        $balance01 = shopping_cart_credits::add_credit($user1->id, 11.10, 'EUR', 'CC1');
        $balance02 = shopping_cart_credits::add_credit($user1->id, 22.20, 'EUR', 'CC2');

        // Check add_credit response.
        $this->assertIsArray($balance01);
        $this->assertEquals(11.1, $balance01[0]);
        $this->assertEquals('EUR', $balance01[1]);
        $this->assertEquals('CC1', $balance01[2]);
        $this->assertIsArray($balance02);
        $this->assertEquals(22.2, $balance02[0]);
        $this->assertEquals('EUR', $balance02[1]);
        $this->assertEquals('CC2', $balance02[2]);

        $balance11 = shopping_cart_credits::get_balance($user1->id, 'CC1');
        $balance12 = shopping_cart_credits::get_balance($user1->id, 'CC2');

        // Check get_balance response.
        $this->assertIsArray($balance11);
        $this->assertEquals(11.1, $balance11[0]);
        $this->assertEquals('EUR', $balance11[1]);
        $this->assertArrayNotHasKey(2, $balance11);
        $this->assertIsArray($balance12);
        $this->assertEquals(22.2, $balance12[0]);
        $this->assertEquals('EUR', $balance12[1]);
        $this->assertArrayNotHasKey(2, $balance12);

        // Check get_balance_for_all_costcenters response.
        $balance2 = shopping_cart_credits::get_balance_for_all_costcenters($user1->id);
        $this->assertIsArray($balance2);
        $balance21 = array_shift($balance2);
        $this->assertIsArray($balance2);
        $this->assertEquals(11.1, $balance21['balance']);
        $this->assertEquals('EUR', $balance21['currency']);
        $this->assertEquals('CC1', $balance21['costcenter']);
        $this->assertEquals('CC1', $balance21['costcenterlabel']);
        $balance22 = array_shift($balance2);
        $this->assertIsArray($balance2);
        $this->assertEquals(22.2, $balance22['balance']);
        $this->assertEquals('EUR', $balance22['currency']);
        $this->assertEquals('CC2', $balance22['costcenter']);
        $this->assertEquals('CC2', $balance22['costcenterlabel']);

        // Check add_credit and get_balance are the same.
        $this->assertEquals($balance01[0], $balance11[0]);
        $this->assertEquals($balance01[1], $balance11[1]);
        $this->assertEquals($balance02[0], $balance12[0]);
        $this->assertEquals($balance02[1], $balance12[1]);
        $this->assertEquals($balance01[0], $balance21['balance']);
        $this->assertEquals($balance01[1], $balance21['currency']);
        $this->assertEquals($balance02[0], $balance22['balance']);
        $this->assertEquals($balance02[1], $balance22['currency']);
        $this->assertEquals($balance01[2], $balance21['costcenter']);
        $this->assertEquals($balance02[2], $balance22['costcenter']);
    }

    /**
     * Data provider for test_shopping_cart_credits_get_data
     *
     * @return array
     */
    public static function shopping_cart_credits_get_data_provider(): array {
        return [
            ['items'],
            ['expirationtime'],
            ['userid'],
            ['credit'],
            ['remainingcredit'],
            ['currency'],
            ['count'],
            ['maxitems'],
            ['price'],
            ['taxesenabled'],
            ['initialtotal'],
            ['deductible'],
            ['nowdate'],
            ['checkouturl'],
        ];
    }

    /**
     * Get data generator
     * @return \testing_data_generator
     */
    public static function get_data_generator() {
        return phpunit_util::get_data_generator();
    }
}
