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
 * Tests for the coupon forms: coupon editing, affected items and the item configuration.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use DOMDocument;
use DOMXPath;
use local_shopping_cart\form\addedit_coupon;
use local_shopping_cart\form\coupon_affected_items;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\coupon;
use MoodleQuickForm;
use ReflectionProperty;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Tests for the coupon forms.
 *
 * @covers \local_shopping_cart\form\addedit_coupon
 * @covers \local_shopping_cart\form\coupon_affected_items
 * @covers \local_shopping_cart\shopping_cart_handler
 */
final class coupon_forms_test extends advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        set_config('couponenabled', 1, 'local_shopping_cart');
        set_config('bookingfee', 0, 'local_shopping_cart');
        set_config('bookingfeevariable', 0, 'local_shopping_cart');
        set_config('rounddiscounts', 0, 'local_shopping_cart');
        set_config('enabletax', '0', 'local_shopping_cart');
    }

    /**
     * Mandatory clean-up after each test.
     */
    protected function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
        \cache_helper::purge_by_definition('local_shopping_cart', 'cacheshopping');
    }

    // Coupon edit form.

    /**
     * A valid percentage coupon is stored with all settings, absolute fields are cleared.
     */
    public function test_addedit_creates_percentage_coupon(): void {
        global $DB;

        $form = $this->submit_addedit([
            'discountabsolute' => 7,
            'maxnumber' => 10,
            'maxnumberperuser' => 2,
            'countmode' => coupon::COUNTMODE_ITEM,
            'coupontype' => 'couponoptin',
        ]);
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $record = $DB->get_record('local_shopping_cart_coupons', ['coupon' => 'FORM10'], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(10.0, (float) $record->discountpercentage, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $record->discountabsolute, 0.001);
        $this->assertSame('', (string) $record->currency);
        $this->assertSame(10, (int) $record->maxnumber);
        $this->assertSame(2, (int) $record->maxnumberperuser);
        $this->assertSame(coupon::COUNTMODE_ITEM, $record->countmode);
        $this->assertSame('couponoptin', $record->coupontype);
        $this->assertSame(1, (int) $record->active);
        $this->assertSame(0, (int) $record->starttime);
        $this->assertSame(0, (int) $record->endtime);
    }

    /**
     * An absolute coupon keeps amount and currency and drops a leftover percentage.
     */
    public function test_addedit_creates_absolute_coupon(): void {
        global $DB;

        $form = $this->submit_addedit([
            'discounttype' => 'absolute',
            'discountpercentage' => 10,
            'discountabsolute' => 7.5,
            'currency' => 'USD',
        ]);
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $record = $DB->get_record('local_shopping_cart_coupons', ['coupon' => 'FORM10'], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(0.0, (float) $record->discountpercentage, 0.001);
        $this->assertEqualsWithDelta(7.5, (float) $record->discountabsolute, 0.001);
        $this->assertSame('USD', $record->currency);
        $this->assertSame(coupon::COUNTMODE_CHECKOUT, $record->countmode);
        $this->assertSame(0, (int) $record->maxnumberperuser);
    }

    /**
     * Editing an existing coupon loads its values and saves the changes in place.
     */
    public function test_addedit_loads_and_updates_existing_coupon(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'EDIT', 0.0, 5.0, 'EUR', 3, 1, 0, 0, 2, 'couponoptout', 1, coupon::COUNTMODE_ITEM);
        $id = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'EDIT']);

        $form = new addedit_coupon(null, null, 'post', '', [], true, ['id' => $id]);
        $form->set_data_for_dynamic_submission();
        $mform = $this->get_mform($form);
        $this->assertSame('absolute', $this->value($mform, 'discounttype'));
        $this->assertSame('3', (string) $this->value($mform, 'maxnumber'));
        $this->assertSame('1', (string) $this->value($mform, 'maxnumberperuser'));
        $this->assertSame(coupon::COUNTMODE_ITEM, $this->value($mform, 'countmode'));

        $form = $this->submit_addedit([
            'id' => $id,
            'coupon' => 'EDIT',
            'maxnumberperuser' => 4,
            'countmode' => coupon::COUNTMODE_CHECKOUT,
        ]);
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $this->assertSame(1, $DB->count_records('local_shopping_cart_coupons'));
        $record = $DB->get_record('local_shopping_cart_coupons', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(4, (int) $record->maxnumberperuser);
        $this->assertSame(coupon::COUNTMODE_CHECKOUT, $record->countmode);
        $this->assertEqualsWithDelta(10.0, (float) $record->discountpercentage, 0.001);
    }

    /**
     * Start and end time are stored when enabled.
     */
    public function test_addedit_stores_validity_period(): void {
        global $DB;
        $form = $this->submit_addedit([
            'starttime' => $this->date(2030, 1, 1),
            'endtime' => $this->date(2030, 12, 31),
        ]);
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();

        $record = $DB->get_record('local_shopping_cart_coupons', ['coupon' => 'FORM10'], '*', MUST_EXIST);
        $this->assertGreaterThan(0, (int) $record->starttime);
        $this->assertGreaterThan((int) $record->starttime, (int) $record->endtime);
    }

    /**
     * Data provider for invalid coupon form input.
     *
     * @return array
     */
    public static function invalid_addedit_provider(): array {
        return [
            'percentage above 100' => [['discountpercentage' => 100.5], 'discountpercentage'],
            'negative percentage' => [['discountpercentage' => -1], 'discountpercentage'],
            'negative absolute' => [['discounttype' => 'absolute', 'discountabsolute' => -1], 'discountabsolute'],
            'negative maxnumber' => [['maxnumber' => -1], 'maxnumber'],
            'negative maxnumberperuser' => [['maxnumberperuser' => -1], 'maxnumberperuser'],
            'end before start' => [
                ['starttime' => [2030, 6, 1], 'endtime' => [2030, 5, 1]],
                'endtime',
            ],
            'code longer than 255 characters' => [['coupon' => str_repeat('A', 256)], 'coupon'],
            'empty code' => [['coupon' => ''], 'coupon'],
        ];
    }

    /**
     * Invalid input is reported on the offending field and nothing is stored.
     *
     * @dataProvider invalid_addedit_provider
     * @param array $overrides
     * @param string $field
     */
    public function test_addedit_rejects_invalid_input(array $overrides, string $field): void {
        global $DB;
        foreach (['starttime', 'endtime'] as $datefield) {
            if (isset($overrides[$datefield])) {
                $overrides[$datefield] = $this->date(...$overrides[$datefield]);
            }
        }

        $form = $this->submit_addedit($overrides);

        $this->assertFalse($form->is_validated());
        $this->assertArrayHasKey($field, $this->get_mform($form)->_errors);
        $this->assertSame(0, $DB->count_records('local_shopping_cart_coupons'));
    }

    /**
     * A second coupon with an existing code is reported as a form error, not as a database error.
     */
    public function test_addedit_rejects_duplicate_code(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'FORM10', 5.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptout');

        $form = $this->submit_addedit([]);

        $this->assertFalse($form->is_validated());
        $this->assertArrayHasKey('coupon', $this->get_mform($form)->_errors);
        $this->assertSame(1, $DB->count_records('local_shopping_cart_coupons'));
    }

    /**
     * Only users who may edit coupons can submit the form.
     */
    public function test_addedit_requires_capability(): void {
        $form = new addedit_coupon(null, null, 'post', '', [], true, ['id' => 0]);
        $this->check_access($form);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        $this->check_access($form);
    }

    // Affected items form.

    /**
     * Opt-in coupons list only the items that opted in, opt-out coupons all configured
     * items except the excluded ones, and a coupon without items says so.
     */
    public function test_affected_items_lists_the_right_items(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'IN', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptin');
        coupon::add_edit_coupon(0, 'OUT', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptout');
        coupon::add_edit_coupon(0, 'NONE', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptin');
        $in = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'IN']);
        $out = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'OUT']);
        $none = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'NONE']);

        $this->save_item_coupons(1, [$in], []);
        $this->save_item_coupons(2, [], [$out]);
        $this->save_item_coupons(3, [], []);

        $this->assertSame([1], $this->affected_itemids($in));
        $this->assertSame([1, 3], $this->affected_itemids($out));
        $this->assertSame([], $this->affected_itemids($none));
    }

    /**
     * The list of affected items is only visible to users who may edit coupons.
     */
    public function test_affected_items_requires_capability(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'IN', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptin');
        $id = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'IN']);
        $form = new coupon_affected_items(null, null, 'post', '', [], true, ['id' => $id]);
        $this->check_access($form);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        $this->check_access($form);
    }

    // Item configuration (shopping_cart_handler).

    /**
     * The item form only offers active coupons of the matching type.
     */
    public function test_item_form_offers_active_coupons_by_type(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'IN', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptin');
        coupon::add_edit_coupon(0, 'INOFF', 10.0, 0.0, 'EUR', 0, 0, 0, 0, 2, 'couponoptin');
        coupon::add_edit_coupon(0, 'OUT', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptout');
        $in = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'IN']);
        $out = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'OUT']);

        $mform = new MoodleQuickForm('itemform', 'post', '');
        (new shopping_cart_handler('local_shopping_cart', 'testitem', 1))->definition($mform, []);

        $this->assertSame([$in], $this->option_values($mform, 'sch_couponoptincodes'));
        $this->assertSame([$out], $this->option_values($mform, 'sch_couponoptoutcodes'));
    }

    /**
     * Without coupons enabled, the item form has no coupon fields.
     */
    public function test_item_form_without_coupons_has_no_coupon_fields(): void {
        set_config('couponenabled', 0, 'local_shopping_cart');
        $mform = new MoodleQuickForm('itemform', 'post', '');
        (new shopping_cart_handler('local_shopping_cart', 'testitem', 1))->definition($mform, []);

        $this->assertFalse($mform->elementExists('sch_couponoptincodes'));
        $this->assertFalse($mform->elementExists('sch_couponoptoutcodes'));
    }

    /**
     * Saved coupon assignments are loaded back into the item form and drive the discount.
     */
    public function test_item_coupon_assignment_round_trip(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'IN', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptin');
        coupon::add_edit_coupon(0, 'OUT', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptout');
        $in = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'IN']);
        $out = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'OUT']);

        $this->save_item_coupons(1, [$in], [$out]);

        $formdata = (object) ['id' => 1];
        (new shopping_cart_handler('local_shopping_cart', 'testitem', 1))->set_data($formdata);
        $this->assertSame([(string) $in], $formdata->sch_couponoptincodes);
        $this->assertSame([(string) $out], $formdata->sch_couponoptoutcodes);

        $userid = (int) $this->getDataGenerator()->create_user()->id;
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 1, $userid);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'testitem', 2, $userid);

        // The opt-in coupon only discounts item 1.
        [$success, $message] = (new coupon($userid))->apply_coupon_code('IN');
        $this->assertTrue($success, $message);
        $this->assertEqualsWithDelta(1.00, (float) cartstore::instance($userid)->get_data()['coupondiscount'], 0.001);

        // The opt-out coupon discounts everything except item 1.
        [$success, $message] = (new coupon($userid))->apply_coupon_code('OUT');
        $this->assertTrue($success, $message);
        $this->assertEqualsWithDelta(2.03, (float) cartstore::instance($userid)->get_data()['coupondiscount'], 0.001);

        // Removing the assignments restores the defaults of both coupon types.
        $this->save_item_coupons(1, [], []);
        (new coupon($userid))->apply_coupon_code('');
        [$success, $message] = (new coupon($userid))->apply_coupon_code('OUT');
        $this->assertTrue($success, $message);
        $this->assertEqualsWithDelta(3.03, (float) cartstore::instance($userid)->get_data()['coupondiscount'], 0.001);
    }

    /**
     * Saving the item form with coupons switched off keeps the stored assignments.
     */
    public function test_item_save_without_coupons_keeps_assignments(): void {
        global $DB;
        coupon::add_edit_coupon(0, 'IN', 10.0, 0.0, 'EUR', 0, 1, 0, 0, 2, 'couponoptin');
        $in = (int) $DB->get_field('local_shopping_cart_coupons', 'id', ['coupon' => 'IN']);
        $this->save_item_coupons(1, [$in], []);

        set_config('couponenabled', 0, 'local_shopping_cart');
        (new shopping_cart_handler('local_shopping_cart', 'testitem', 1))->save_data((object) ['id' => 1], new stdClass());

        $json = json_decode($DB->get_field('local_shopping_cart_iteminfo', 'json', [
            'itemid' => 1,
            'componentname' => 'local_shopping_cart',
            'area' => 'testitem',
        ]));
        $this->assertSame((string) $in, $json->couponoptin);
    }

    // Helpers.

    /**
     * Build a submitted coupon edit form from defaults plus overrides.
     *
     * @param array $overrides
     * @return addedit_coupon
     */
    private function submit_addedit(array $overrides): addedit_coupon {
        $data = array_merge([
            'id' => 0,
            'coupon' => 'FORM10',
            'discounttype' => 'percentage',
            'discountpercentage' => 10,
            'discountabsolute' => 0,
            'currency' => 'EUR',
            'maxnumber' => 1,
            'maxnumberperuser' => 0,
            'countmode' => coupon::COUNTMODE_CHECKOUT,
            'active' => 1,
            'coupontype' => 'couponoptout',
            'starttime' => ['enabled' => 0],
            'endtime' => ['enabled' => 0],
        ], $overrides);
        $data = addedit_coupon::mock_ajax_submit($data);
        $form = new addedit_coupon(null, null, 'post', '', [], true, $data, true);
        $form->set_data_for_dynamic_submission();
        return $form;
    }

    /**
     * Run the access check of a dynamic form.
     *
     * @param \core_form\dynamic_form $form
     */
    private function check_access(\core_form\dynamic_form $form): void {
        $method = new \ReflectionMethod($form, 'check_access_for_dynamic_submission');
        $method->invoke($form);
    }

    /**
     * Submitted value of an optional date time selector.
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @return array
     */
    private function date(int $year, int $month, int $day): array {
        return ['enabled' => 1, 'year' => $year, 'month' => $month, 'day' => $day, 'hour' => 0, 'minute' => 0];
    }

    /**
     * The quickform inside a moodleform.
     *
     * @param \moodleform $form
     * @return MoodleQuickForm
     */
    private function get_mform(\moodleform $form): MoodleQuickForm {
        $property = new ReflectionProperty(\moodleform::class, '_form');
        return $property->getValue($form);
    }

    /**
     * Current value of a single-value form element.
     *
     * @param MoodleQuickForm $mform
     * @param string $name
     * @return mixed
     */
    private function value(MoodleQuickForm $mform, string $name) {
        $value = $mform->getElement($name)->getValue();
        return is_array($value) ? reset($value) : $value;
    }

    /**
     * Option values of a select element as integers.
     *
     * @param MoodleQuickForm $mform
     * @param string $name
     * @return int[]
     */
    private function option_values(MoodleQuickForm $mform, string $name): array {
        $values = [];
        foreach ($mform->getElement($name)->_options as $option) {
            $values[] = (int) $option['attr']['value'];
        }
        sort($values);
        return $values;
    }

    /**
     * Store the coupon assignment of a mock item the way the item form does.
     *
     * @param int $itemid
     * @param int[] $optin
     * @param int[] $optout
     */
    private function save_item_coupons(int $itemid, array $optin, array $optout): void {
        $handler = new shopping_cart_handler('local_shopping_cart', 'testitem', $itemid);
        $handler->save_data((object) [
            'id' => $itemid,
            'sch_couponoptincodes' => array_map('strval', $optin),
            'sch_couponoptoutcodes' => array_map('strval', $optout),
        ], new stdClass());
    }

    /**
     * Item ids listed in the affected items form of a coupon, read from the table structure.
     *
     * @param int $couponid
     * @return int[]
     */
    private function affected_itemids(int $couponid): array {
        $form = new coupon_affected_items(null, null, 'post', '', [], true, ['id' => $couponid]);
        $html = $form->render();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $itemids = [];
        foreach ($xpath->query('//table//tbody/tr') as $row) {
            // Columns: name, component, area, item id, coupon type.
            $itemids[] = (int) trim($xpath->query('td', $row)->item(3)->textContent);
        }
        sort($itemids);
        return $itemids;
    }
}
