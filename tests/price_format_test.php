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
 * Tests for price formatting on the checkout page.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use core_payment\helper;
use local_shopping_cart\external\add_item_to_cart;
use local_shopping_cart\local\cartstore;

/**
 * Tests that prices keep their cent amounts, no matter how often they are
 * converted for rendering and which decimal separator the language uses.
 *
 * Regression test: convert_prices_to_number_format() runs twice on checkout.php
 * (once in checkout::prepare_checkout, once right before rendering). For
 * languages with a decimal comma the second pass used to truncate the already
 * localized string "42,50" to 42.00 via its (float) cast.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class price_format_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * Define a custom decimal separator.
     *
     * It is not possible to directly change the result of get_string in a unit
     * test. Instead, we create a language pack for language 'xx' in dataroot
     * and make langconfig.php with the string we need to change (same approach
     * as core's moodlelib_test).
     *
     * @param string $decsep the decimal separator to use.
     */
    protected function define_local_decimal_separator(string $decsep = ','): void {
        global $SESSION, $CFG;

        $SESSION->lang = 'xx';
        $langconfig = "<?php\n\$string['decsep'] = '$decsep';";
        $langfolder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($langfolder);
        file_put_contents($langfolder . '/langconfig.php', $langconfig);

        // Ensure the new value is picked up and not taken from the cache.
        get_string_manager()->reset_caches(true);
    }

    /**
     * Converting prices twice must yield the same result as converting once,
     * also for languages that use a decimal comma.
     *
     * @covers \local_shopping_cart\shopping_cart::convert_prices_to_number_format
     * @covers \local_shopping_cart\shopping_cart::price_to_float
     * @return void
     */
    public function test_convert_prices_is_idempotent_with_decimal_comma(): void {
        $this->define_local_decimal_separator(',');

        $data = [
            'price' => 42.5,
            'initialtotal' => 42.5,
            'credit' => 10.25,
            'items' => [
                ['price' => 42.5, 'price_net' => 35.42],
            ],
            // Machine-formatted string, as produced by shoppingcart_history_list::return_list().
            'historyitems' => [
                ['price' => '42.50'],
            ],
        ];

        // First conversion, as done in checkout::prepare_checkout().
        shopping_cart::convert_prices_to_number_format($data);
        $this->assertSame('42,50', $data['price']);
        $this->assertSame('42,50', $data['initialtotal']);
        $this->assertSame('10,25', $data['credit']);
        $this->assertSame('42,50', $data['items'][0]['price']);
        $this->assertSame('35,42', $data['items'][0]['price_net']);
        $this->assertSame('42,50', $data['historyitems'][0]['price']);

        // Second conversion, as done in checkout.php right before rendering.
        // This used to truncate "42,50" to "42,00".
        shopping_cart::convert_prices_to_number_format($data);
        $this->assertSame('42,50', $data['price']);
        $this->assertSame('42,50', $data['initialtotal']);
        $this->assertSame('10,25', $data['credit']);
        $this->assertSame('42,50', $data['items'][0]['price']);
        $this->assertSame('35,42', $data['items'][0]['price_net']);
        $this->assertSame('42,50', $data['historyitems'][0]['price']);

        // Values that were already localized with format_float elsewhere
        // (e.g. credit in shoppingcart_history_list::return_list or output\cashier).
        $data = ['credit' => format_float(10.25, 2)];
        $this->assertSame('10,25', $data['credit']);
        shopping_cart::convert_prices_to_number_format($data);
        $this->assertSame('10,25', $data['credit']);
    }

    /**
     * Same conversion must also stay correct with the default decimal point.
     *
     * @covers \local_shopping_cart\shopping_cart::convert_prices_to_number_format
     * @covers \local_shopping_cart\shopping_cart::price_to_float
     * @return void
     */
    public function test_convert_prices_is_idempotent_with_decimal_point(): void {
        $data = [
            'price' => 42.5,
            'items' => [
                ['price' => 42.5],
            ],
        ];

        shopping_cart::convert_prices_to_number_format($data);
        shopping_cart::convert_prices_to_number_format($data);

        $this->assertSame('42.50', $data['price']);
        $this->assertSame('42.50', $data['items'][0]['price']);
    }

    /**
     * Full checkout page data flow: prepare_checkout converts the prices once,
     * checkout.php converts them a second time before rendering. The cent
     * amounts must survive both conversions with a decimal comma.
     *
     * @covers \local_shopping_cart\shopping_cart::convert_prices_to_number_format
     * @covers \local_shopping_cart\local\pricemodifier\modifiers\checkout::prepare_checkout
     * @runInSeparateProcess
     * @return void
     */
    public function test_checkout_data_keeps_cents_with_decimal_comma(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->define_local_decimal_separator(',');

        $account = helper::save_payment_account((object)['name' => 'Test 1', 'idnumber' => '']);
        helper::save_payment_gateway(
            (object)['accountid' => $account->get('id'), 'gateway' => 'paypal', 'config' => 'T1']
        );
        set_config('accountid', $account->get('id'), 'local_shopping_cart');

        // Test item 2 costs 20.30 (see mock\mockitems).
        $addresult = add_item_to_cart::execute('local_shopping_cart', 'main', 2, $user->id);
        $this->assertEquals(1, $addresult['success'], 'Item was not successfully added to cart.');

        // Same sequence as on checkout.php.
        $cartstore = cartstore::instance($user->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);
        shopping_cart::convert_prices_to_number_format($data);

        $this->assertSame('20,30', $data['price']);
        $item = reset($data['items']);
        $this->assertSame('20,30', $item['price']);
    }
}
