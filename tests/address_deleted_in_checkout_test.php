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
 * Deleting the address selected in the checkout must not break the checkout.
 *
 * Scenario reported from a live site: a user selected an address in the
 * addresses step, deleted exactly that address via the delete button and
 * got a DB error on the checkout page. Purging the caches made the page work
 * again, because the deleted address id lives on in two caches:
 *
 *  - the cartstore cache (address_billing / taxcountrycode) and
 *  - the checkout manager cache (steps.addresses.data.selectedaddress_*
 *    together with the step's "valid" flag).
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use local_shopping_cart\external\delete_user_address;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\checkout_manager;
use local_shopping_cart\local\checkout_process\checkout_page_data;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;

/**
 * Deleting the address selected in the checkout must not break the checkout.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\local\checkout_process\items_helper\address_operations
 * @covers \local_shopping_cart\local\checkout_process\items\addresses
 * @covers \local_shopping_cart\local\checkout_process\checkout_page_data
 */
final class address_deleted_in_checkout_test extends \advanced_testcase {
    /** @var \core_payment\account account */
    protected $account;

    /**
     * Setup function.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('country', 'AT');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $this->account = $generator->create_payment_account(['name' => 'Account1']);
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
        \cache_helper::purge_by_definition('local_shopping_cart', 'cachebookingpreprocess');
    }

    /**
     * Creates three Austrian addresses for the user.
     *
     * @param \stdClass $user
     * @return array address ids
     */
    private function generate_fake_addresses(\stdClass $user): array {
        global $DB;
        $addressids = [];
        for ($i = 0; $i < 3; $i++) {
            $record = new \stdClass();
            $record->userid = $user->id;
            $record->name = $user->firstname . ' ' . $user->lastname;
            $record->state = 'AT';
            $record->address = 'Fakestreet ' . $i;
            $record->city = 'Fakecity ' . $i;
            $record->zip = '12345' . $i;
            $record->phone = '0043 93453234' . $i;
            $addressids[] = $DB->insert_record('local_shopping_cart_address', $record);
        }
        return $addressids;
    }

    /**
     * Puts items in the cart of a fresh user, creates two addresses and submits
     * the addresses step with the first one selected.
     *
     * @return array [user, selected address id, other address id]
     */
    private function start_checkout_with_selected_address(): array {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');
        set_config('addresses_required', 'billing', 'local_shopping_cart');

        for ($id = 1; $id <= 2; $id++) {
            shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $id, $user->id);
        }

        [$selectedid, $otherid] = $this->generate_fake_addresses($user);

        $cartstore = cartstore::instance($user->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);

        $checkoutmanager = new checkout_manager($data, ['currentstep' => 0, 'action' => null]);
        $checkoutmanager->render_overview();
        $managercache = $checkoutmanager->submit_step(
            json_encode([['name' => 'selectedaddress_billing', 'value' => (string) $selectedid]])
        );

        // Sanity: the selection reached both caches.
        $this->assertTrue($managercache['steps']['addresses']['valid']);
        $this->assertEquals($selectedid, $managercache['steps']['addresses']['data']['selectedaddress_billing']);
        $this->assertEquals($selectedid, $cartstore->get_cache()['address_billing']);
        $this->assertEquals('AT', $cartstore->get_countrycode());

        return [$user, (int) $selectedid, (int) $otherid];
    }

    /**
     * Deletes an address the way the checkout delete button does (webservice).
     *
     * @param int $addressid
     * @return void
     */
    private function delete_address(int $addressid): void {
        global $DB;
        $result = delete_user_address::execute($addressid);
        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('local_shopping_cart_address', ['id' => $addressid]));
    }

    /**
     * The live bug: reloading the checkout page after the selected address was
     * deleted must neither throw (old code: MUST_EXIST lookup of the cached id)
     * nor present the deleted address as selected (request-level static cache
     * in address_operations still returns the deleted record).
     *
     * @return void
     * @runInSeparateProcess
     */
    public function test_checkout_page_after_deleting_selected_address(): void {
        [$user, $selectedid] = $this->start_checkout_with_selected_address();

        $this->delete_address($selectedid);

        // The lookup of the dangling id must not throw and must not find anything.
        $this->assertFalse(address_operations::get_specific_user_address($selectedid));
        $this->assertArrayNotHasKey($selectedid, address_operations::get_all_user_addresses($user->id));

        // This is what checkout.php does on reload.
        $pagedata = checkout_page_data::build_cart_checkout($user->id);
        $this->assertArrayNotHasKey(
            'show_selected_addresses',
            $pagedata,
            'A deleted address must not be rendered as the selected address.'
        );
    }

    /**
     * Deleting the selected address has to invalidate the addresses step and
     * remove the id from the cartstore, so the user cannot proceed to payment
     * with a dangling address id (and the history/ledger never store it).
     *
     * @return void
     * @runInSeparateProcess
     */
    public function test_deleting_selected_address_invalidates_address_step(): void {
        [$user, $selectedid] = $this->start_checkout_with_selected_address();

        $this->delete_address($selectedid);

        $managercache = checkout_manager::get_cache($user->id);
        $this->assertFalse(
            $managercache['steps']['addresses']['valid'] ?? false,
            'The addresses step must not stay valid after its selected address was deleted.'
        );
        $this->assertFalse($managercache['checkout_validation'] ?? false);
        $this->assertEmpty(
            $managercache['steps']['addresses']['data']['selectedaddress_billing'] ?? null,
            'The checkout manager cache must not keep the deleted address id.'
        );

        $cartstore = cartstore::instance($user->id);
        $this->assertEmpty(
            $cartstore->get_cache()['address_billing'] ?? null,
            'The cartstore cache must not keep the deleted address id.'
        );
        $this->assertSame('', $cartstore->get_countrycode());
    }

    /**
     * Deleting another, unselected address leaves the selection intact.
     *
     * @return void
     * @runInSeparateProcess
     */
    public function test_deleting_unselected_address_keeps_selection(): void {
        [$user, $selectedid, $otherid] = $this->start_checkout_with_selected_address();

        $this->delete_address($otherid);

        $managercache = checkout_manager::get_cache($user->id);
        $this->assertTrue($managercache['steps']['addresses']['valid']);
        $this->assertEquals($selectedid, $managercache['steps']['addresses']['data']['selectedaddress_billing']);

        $cartstore = cartstore::instance($user->id);
        $this->assertEquals($selectedid, $cartstore->get_cache()['address_billing']);
        $this->assertEquals('AT', $cartstore->get_countrycode());

        $pagedata = checkout_page_data::build_cart_checkout($user->id);
        $this->assertTrue($pagedata['show_selected_addresses'] ?? false);
        $this->assertEquals($selectedid, $pagedata['selected_addresses'][0]['id']);
    }
}
