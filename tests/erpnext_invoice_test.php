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
 * Tests for the ERPNext tax-template selection reused by other plugins and for the invoice payload.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::select_tax_template
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::prepare_json_invoice_data
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::extract_error_details
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

    /**
     * UK customers are export customers since Brexit, with and without a VAT id (GH-199).
     */
    public function test_gb_is_export(): void {
        $this->resetAfterTest();
        set_config('owncountrycode', 'AT', 'local_shopping_cart');
        $this->assertSame('Export VAT', erpnext_invoice::select_tax_template('GB', true, $this->templates));
        $this->assertSame('Export VAT', erpnext_invoice::select_tax_template('GB', false, $this->templates));
    }

    /**
     * The service period travels in the mandatory Sales Invoice fields from / to (Wunderbyte-GmbH/Wunderbyte-GmbH#2548).
     *
     * ERPNext rejects the insert with a MandatoryError when either field is missing.
     */
    public function test_service_period_is_sent_in_from_and_to(): void {
        $this->resetAfterTest();
        $start = strtotime('2026-10-01 12:00:00');
        $end = strtotime('2026-12-31 12:00:00');

        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['item_exists', 'get_erp_billing_address_name'])
            ->getMock();
        $invoice->method('item_exists')->willReturn(true);
        $invoice->method('get_erp_billing_address_name')->willReturn('Customer-Billing');

        $item = (object) [
            'itemname' => 'Test item',
            'vatnumber' => '',
            'price' => 120,
            'tax' => 20,
            'serviceperiodstart' => $start,
            'serviceperiodend' => $end,
            'timecreated' => $start,
            'address_billing' => 0,
        ];
        $this->set_private($invoice, 'invoiceitems', [$item]);
        $this->set_private($invoice, 'billingaddress', (object) ['state' => 'AT']);
        $this->set_private($invoice, 'customername', 'Customer');
        $this->set_private($invoice, 'forcedtaxtemplate', 'Austria Tax');
        // The tax template lookup is the only HTTP request that is not mocked away.
        \curl::mock_response(json_encode(['data' => ['taxes' => []]]));

        $this->assertTrue($invoice->prepare_json_invoice_data());

        $payload = json_decode((new \ReflectionProperty(erpnext_invoice::class, 'jsoninvoice'))->getValue($invoice), true);
        $this->assertSame(date('Y-m-d', $start), $payload['from'] ?? null);
        $this->assertSame(date('Y-m-d', $end), $payload['to'] ?? null);
    }

    /**
     * Set a private property of the invoice.
     *
     * @param erpnext_invoice $invoice
     * @param string $name
     * @param mixed $value
     */
    private function set_private(erpnext_invoice $invoice, string $name, $value): void {
        (new \ReflectionProperty(erpnext_invoice::class, $name))->setValue($invoice, $value);
    }

    /**
     * The validation message of an ERPNext error response is kept, not only its type.
     */
    public function test_extract_error_details(): void {
        $response = [
            'exc_type' => 'ValidationError',
            'exception' => 'frappe.exceptions.ValidationError: Exchange Rate is mandatory',
            '_server_messages' => json_encode([
                json_encode(['message' => 'Row #1: <strong>Exchange Rate</strong> is mandatory', 'indicator' => 'red']),
            ]),
        ];
        $this->assertSame('Row #1: Exchange Rate is mandatory', erpnext_invoice::extract_error_details($response));

        // Without server messages the exception line is used.
        unset($response['_server_messages']);
        $this->assertSame(
            'frappe.exceptions.ValidationError: Exchange Rate is mandatory',
            erpnext_invoice::extract_error_details($response)
        );
        $this->assertSame('', erpnext_invoice::extract_error_details(['exc_type' => 'ValidationError']));
    }
}
