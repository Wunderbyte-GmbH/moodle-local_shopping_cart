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

use advanced_testcase;
use local_shopping_cart\external\add_item_to_cart;
use local_shopping_cart\local\cartstore;

/**
 * phpUnit tests for the item-specific booking fee (allowcustombookingfee).
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class custom_bookingfee_test extends advanced_testcase {
    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('bookingfee', 2, 'local_shopping_cart');
        set_config('bookingfeeonlyonce', 0, 'local_shopping_cart');
        set_config('allowcustombookingfee', 1, 'local_shopping_cart');
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
     * An item-specific fee overrides the global fee; with several items the highest fee wins.
     *
     * @covers \local_shopping_cart\shopping_cart\service_provider::load_cartitem
     * @runInSeparateProcess
     * @return void
     */
    public function test_custom_fee_overrides_global_and_max_wins(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->set_iteminfo_fee(1, 'testarea', 5);
        $this->set_iteminfo_fee(2, 'testarea', 3);

        // Ascending order: the fee must be recalculated when the second
        // (more expensive) item is added.
        add_item_to_cart::execute('local_shopping_cart', 'testarea', 2, (int)$user->id);
        $this->assertSame(3.0, $this->get_fee_price((int)$user->id));

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(5.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * A later item with a lower item-specific fee must not reduce the fee.
     *
     * @covers \local_shopping_cart\shopping_cart\service_provider::load_cartitem
     * @runInSeparateProcess
     * @return void
     */
    public function test_lower_fee_item_does_not_reduce_fee(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->set_iteminfo_fee(1, 'testarea', 5);
        $this->set_iteminfo_fee(2, 'testarea', 3);

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(5.0, $this->get_fee_price((int)$user->id));

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 2, (int)$user->id);
        $this->assertSame(5.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * An item-specific fee of 0 means: explicitly no fee.
     *
     * @covers \local_shopping_cart\shopping_cart\service_provider::load_cartitem
     * @runInSeparateProcess
     * @return void
     */
    public function test_custom_fee_zero_means_free(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->set_iteminfo_fee(1, 'testarea', 0);

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(0.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * Items without an own fee fall back to the global fee.
     *
     * @covers \local_shopping_cart\shopping_cart\service_provider::load_cartitem
     * @runInSeparateProcess
     * @return void
     */
    public function test_without_iteminfo_global_fee_applies(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(2.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * An iteminfo fee stored as null (form field left empty) falls back to the global fee.
     *
     * @covers \local_shopping_cart\shopping_cart\service_provider::load_cartitem
     * @runInSeparateProcess
     * @return void
     */
    public function test_null_iteminfo_fee_falls_back_to_global(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->set_iteminfo_fee(1, 'testarea', null);

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(2.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * With the gate disabled, item-specific fees are ignored.
     *
     * @covers \local_shopping_cart\shopping_cart\service_provider::load_cartitem
     * @runInSeparateProcess
     * @return void
     */
    public function test_gate_off_ignores_custom_fee(): void {
        set_config('allowcustombookingfee', 0, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->set_iteminfo_fee(1, 'testarea', 5);

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(2.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * With global fee 0 the fee item is still added when an item carries its own fee.
     *
     * @covers \local_shopping_cart\shopping_cart_bookingfee::add_fee_to_cart
     * @runInSeparateProcess
     * @return void
     */
    public function test_custom_fee_with_global_fee_zero(): void {
        set_config('bookingfee', 0, 'local_shopping_cart');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->set_iteminfo_fee(1, 'testarea', 4);

        add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, (int)$user->id);
        $this->assertSame(4.0, $this->get_fee_price((int)$user->id));
    }

    /**
     * Store an item-specific booking fee like shopping_cart_handler::save_data does.
     *
     * @param int $itemid
     * @param string $area
     * @param float|int|null $fee
     * @return void
     */
    private function set_iteminfo_fee(int $itemid, string $area, $fee): void {
        global $DB;

        $DB->insert_record('local_shopping_cart_iteminfo', (object)[
            'itemid' => $itemid,
            'componentname' => 'local_shopping_cart',
            'area' => $area,
            'allowinstallment' => 0,
            'json' => json_encode(['bookingfee' => $fee]),
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Return the price of the booking fee item currently in the user's cart.
     *
     * @param int $userid
     * @return float|null null when no fee item is in the cart
     */
    private function get_fee_price(int $userid): ?float {
        $cartstore = cartstore::instance($userid);
        foreach ($cartstore->get_all_items() as $item) {
            if ((string)($item['area'] ?? '') === 'bookingfee') {
                return (float)$item['price'];
            }
        }

        return null;
    }
}
