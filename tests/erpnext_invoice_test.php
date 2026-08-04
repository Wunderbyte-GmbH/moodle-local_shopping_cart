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
use local_shopping_cart\invoice\erpnext_invoice;

/**
 * Tests for the ERPNext tax-template selection reused by other plugins.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::select_tax_template
 */
final class erpnext_invoice_test extends advanced_testcase {
    /** @var array Available ERPNext template names for the tests. */
    private array $templates = ['Austria Tax', 'EU Reverse Charge', 'Export VAT'];

    /**
     * Own country (AT) with a VAT id uses the domestic tax template.
     */
    public function test_own_country_with_vatid(): void {
        $this->resetAfterTest();
        set_config('owncountrycode', 'AT', 'local_shopping_cart');
        $this->assertSame('Austria Tax', erpnext_invoice::select_tax_template('AT', true, $this->templates));
    }

    /**
     * Cross-border EU B2B with a VAT id uses reverse charge.
     */
    public function test_eu_crossborder_with_vatid(): void {
        $this->resetAfterTest();
        set_config('owncountrycode', 'AT', 'local_shopping_cart');
        $this->assertSame('EU Reverse Charge', erpnext_invoice::select_tax_template('DE', true, $this->templates));
    }

    /**
     * EU consumer without a VAT id is taxed with the domestic template.
     */
    public function test_eu_without_vatid(): void {
        $this->resetAfterTest();
        set_config('owncountrycode', 'AT', 'local_shopping_cart');
        $this->assertSame('Austria Tax', erpnext_invoice::select_tax_template('DE', false, $this->templates));
    }

    /**
     * Non-EU sales use the export template regardless of VAT id.
     */
    public function test_non_eu_export(): void {
        $this->resetAfterTest();
        set_config('owncountrycode', 'AT', 'local_shopping_cart');
        $this->assertSame('Export VAT', erpnext_invoice::select_tax_template('US', true, $this->templates));
        $this->assertSame('Export VAT', erpnext_invoice::select_tax_template('US', false, $this->templates));
    }
}
