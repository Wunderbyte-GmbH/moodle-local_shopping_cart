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
 * The legacy checkout rendering.
 *
 * @package    local_shopping_cart
 * @category   test
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\local\checkout_process\checkout_mode;
use local_shopping_cart\local\checkout_process\items\addresses;
use local_shopping_cart\local\checkout_process\items\termsandconditions;
use local_shopping_cart\local\checkout_process\items\vatnrchecker;
use local_shopping_cart\local\checkout_process\steps\addresses_form;
use local_shopping_cart\local\checkout_process\steps\termsandconditions_form;
use local_shopping_cart\local\checkout_process\steps\vatnrchecker_form;

/**
 * The legacy checkout rendering.
 *
 * The manager decides per step which rendering to use: a step without a form
 * classname is rendered through render_body() and validated through
 * check_status(). These tests pin that the setting is what drives that decision
 * and that the legacy path of every migrated step answers.
 *
 * @covers \local_shopping_cart\local\checkout_process\checkout_mode
 */
final class legacy_checkout_test extends advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
    }

    /**
     * Data provider for the migrated steps.
     *
     * @return array
     */
    public static function migrated_steps_provider(): array {
        return [
            'addresses' => [addresses::class, addresses_form::class],
            'terms and conditions' => [termsandconditions::class, termsandconditions_form::class],
            'vat number' => [vatnrchecker::class, vatnrchecker_form::class],
        ];
    }

    /**
     * Without the setting every migrated step names its form class, with it none does.
     *
     * @dataProvider migrated_steps_provider
     * @param string $stepclass
     * @param string $formclass
     */
    public function test_form_classname_follows_the_setting(string $stepclass, string $formclass): void {
        set_config('legacycheckout', 0, 'local_shopping_cart');
        $this->assertFalse(checkout_mode::is_legacy());
        $this->assertSame($formclass, $stepclass::get_form_classname());

        set_config('legacycheckout', 1, 'local_shopping_cart');
        $this->assertTrue(checkout_mode::is_legacy());
        $this->assertSame('', $stepclass::get_form_classname());
    }

    /**
     * An unset setting is the current rendering, not the legacy one.
     */
    public function test_default_is_the_current_rendering(): void {
        unset_config('legacycheckout', 'local_shopping_cart');
        $this->assertFalse(checkout_mode::is_legacy());
    }

    /**
     * The terms step answers with a rendered body in the legacy rendering.
     */
    public function test_legacy_terms_step_renders_a_body(): void {
        set_config('legacycheckout', 1, 'local_shopping_cart');
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('termsandconditions', '<p>Terms</p>', 'local_shopping_cart');

        $body = termsandconditions::render_body([]);

        $this->assertArrayHasKey('template', $body);
        $this->assertNotEmpty($body['template']);
    }

    /**
     * The VAT step answers with a rendered body and offers every country of the shared list.
     */
    public function test_legacy_vat_step_renders_a_body(): void {
        set_config('legacycheckout', 1, 'local_shopping_cart');

        $body = vatnrchecker::render_body([]);
        $countries = vatnrchecker::get_country_code_name();

        $this->assertArrayHasKey('template', $body);
        $this->assertNotEmpty($body['template']);
        $this->assertSameSize(
            \local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper::get_countrycodes_array(),
            $countries
        );
    }

    /**
     * The legacy validation of the terms step is the validation of the form step:
     * every active conditions checkbox has to be ticked.
     */
    public function test_legacy_terms_step_validation(): void {
        set_config('legacycheckout', 1, 'local_shopping_cart');
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('termsandconditions', '<p>Terms</p>', 'local_shopping_cart');

        $step = new termsandconditions();

        $unticked = $step->check_status([], json_encode([['name' => 'accepttermsandconditions', 'value' => false]]));
        $this->assertFalse($unticked['valid']);
        $this->assertTrue($unticked['mandatory']);

        $ticked = $step->check_status([], json_encode([['name' => 'accepttermsandconditions', 'value' => true]]));
        $this->assertTrue($ticked['valid']);
    }

    /**
     * A step that was never submitted is invalid rather than silently accepted.
     */
    public function test_legacy_terms_step_without_input_is_invalid(): void {
        set_config('legacycheckout', 1, 'local_shopping_cart');
        set_config('accepttermsandconditions', 1, 'local_shopping_cart');
        set_config('termsandconditions', '<p>Terms</p>', 'local_shopping_cart');

        $step = new termsandconditions();
        $result = $step->check_status([], json_encode([]));

        $this->assertFalse($result['valid']);
    }
}
