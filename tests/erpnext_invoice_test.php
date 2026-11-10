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
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::get_receivable_account
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::create_invoice_from_data
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::create_invoice
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::book_payment
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::build_payment_entry
 * @covers     \local_shopping_cart\invoice\erpnext_invoice::validate_response
 */
final class erpnext_invoice_test extends advanced_testcase {
    /** @var array Available ERPNext template names for the tests. */
    private array $templates = ['Austria Tax', 'EU Reverse Charge', 'Export VAT'];

    /**
     * The invoice class reports progress and errors with mtrace() for the task log.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->expectOutputRegex('/.*/s');
    }

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
     * The service period travels in the mandatory Sales Invoice fields from_date / to_date (Wunderbyte-GmbH/Wunderbyte-GmbH#2548).
     *
     * ERPNext rejects the insert with a MandatoryError when either field is missing. The custom
     * fields from / to are still filled for instances that read them.
     */
    public function test_service_period_is_sent_in_from_date_and_to_date(): void {
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
        $this->assertSame(date('Y-m-d', $start), $payload['from_date'] ?? null);
        $this->assertSame(date('Y-m-d', $end), $payload['to_date'] ?? null);
        $this->assertSame(date('Y-m-d', $start), $payload['from'] ?? null);
        $this->assertSame(date('Y-m-d', $end), $payload['to'] ?? null);
    }

    /**
     * Mock an invoice ready for prepare_json_invoice_data() in the given currency.
     *
     * @param string|null $receivableaccount what get_receivable_account() returns
     * @return erpnext_invoice
     */
    private function currency_invoice(?string $receivableaccount): erpnext_invoice {
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['item_exists', 'get_erp_billing_address_name', 'get_receivable_account'])
            ->getMock();
        $invoice->method('item_exists')->willReturn(true);
        $invoice->method('get_erp_billing_address_name')->willReturn('Customer-Billing');
        $invoice->method('get_receivable_account')->with('USD')->willReturn($receivableaccount);
        $item = (object) [
            'itemname' => 'Test item',
            'vatnumber' => '',
            'price' => 400,
            'tax' => 0,
            'serviceperiodstart' => time(),
            'serviceperiodend' => time(),
            'timecreated' => time(),
            'address_billing' => 0,
        ];
        $this->set_private($invoice, 'invoiceitems', [$item]);
        $this->set_private($invoice, 'billingaddress', (object) ['state' => 'ZA']);
        $this->set_private($invoice, 'customername', 'Customer');
        $this->set_private($invoice, 'forcedtaxtemplate', 'Export VAT');
        $this->set_private($invoice, 'currency', 'USD');
        \curl::mock_response(json_encode(['data' => ['taxes' => []]]));
        return $invoice;
    }

    /**
     * A foreign-currency invoice is booked to the receivable account in that currency.
     */
    public function test_foreign_currency_sets_receivable_account(): void {
        $this->resetAfterTest();
        $invoice = $this->currency_invoice('Schuldner WB in USD - WB');

        $this->assertTrue($invoice->prepare_json_invoice_data());

        $payload = json_decode((new \ReflectionProperty(erpnext_invoice::class, 'jsoninvoice'))->getValue($invoice), true);
        $this->assertSame('USD', $payload['currency']);
        $this->assertSame('Schuldner WB in USD - WB', $payload['debit_to']);
    }

    /**
     * Without a receivable account in the invoice currency, nothing is sent to ERPNext.
     */
    public function test_foreign_currency_without_receivable_account(): void {
        $this->resetAfterTest();
        $invoice = $this->currency_invoice(null);

        $this->assertFalse($invoice->prepare_json_invoice_data());
    }

    /**
     * The company currency keeps ERPNext's default receivable account.
     */
    public function test_company_currency_keeps_default_account(): void {
        $this->resetAfterTest();
        $invoice = $this->currency_invoice('');

        $this->assertTrue($invoice->prepare_json_invoice_data());

        $payload = json_decode((new \ReflectionProperty(erpnext_invoice::class, 'jsoninvoice'))->getValue($invoice), true);
        $this->assertArrayNotHasKey('debit_to', $payload);
    }

    /**
     * An injected invoice DTO.
     *
     * @param bool|null $submit the submit flag, null to leave it unset
     * @return \stdClass
     */
    private function invoice_dto(?bool $submit): \stdClass {
        $data = (object) [
            'reference' => '632',
            'currency' => 'USD',
            'user' => (object) ['id' => 4, 'email' => 'buyer@example.org', 'firstname' => 'Jane', 'lastname' => 'Doe'],
            'billing' => (object) ['company' => 'Example Ltd', 'name' => 'Jane Doe', 'state' => 'ZA', 'id' => 4],
            'items' => [(object) ['itemname' => 'Test item', 'net' => 400]],
        ];
        if ($submit !== null) {
            $data->submit = $submit;
        }
        return $data;
    }

    /**
     * Mock the ERPNext calls of create_invoice_from_data().
     *
     * @param array $existing what find_open_invoice_by_reference() returns
     * @param int $submits how often submit_invoice() must be called
     * @return erpnext_invoice
     */
    private function flow_invoice(array $existing, int $submits): erpnext_invoice {
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['find_open_invoice_by_reference', 'prepare_json_invoice_data', 'customer_exists', 'submit_invoice'])
            ->getMock();
        $invoice->method('find_open_invoice_by_reference')->willReturn($existing);
        $invoice->method('prepare_json_invoice_data')->willReturn(true);
        $invoice->method('customer_exists')->willReturn(true);
        $invoice->expects($this->exactly($submits))->method('submit_invoice')->willReturn(true);
        $this->set_private($invoice, 'jsoninvoice', '{}');
        return $invoice;
    }

    /**
     * With submit = false the new invoice stays a draft.
     */
    public function test_invoice_can_stay_draft(): void {
        $this->resetAfterTest();
        $invoice = $this->flow_invoice(['name' => '', 'docstatus' => 0], 0);
        \curl::mock_response(json_encode(['data' => ['name' => 'INV-1', 'grand_total' => 400]]));

        $this->assertTrue($invoice->create_invoice_from_data($this->invoice_dto(false)));
        $this->assertSame('INV-1', $invoice->invoiceid);
    }

    /**
     * Without the flag the invoice is submitted, as before.
     */
    public function test_invoice_is_submitted_by_default(): void {
        $this->resetAfterTest();
        $invoice = $this->flow_invoice(['name' => '', 'docstatus' => 0], 1);
        \curl::mock_response(json_encode(['data' => ['name' => 'INV-1', 'grand_total' => 400]]));

        $this->assertTrue($invoice->create_invoice_from_data($this->invoice_dto(null)));
    }

    /**
     * A draft from an earlier run is reused instead of creating a second invoice, and submitted if wanted.
     */
    public function test_existing_draft_is_reused(): void {
        $this->resetAfterTest();
        $draft = ['name' => 'INV-OLD', 'docstatus' => 0];

        $invoice = $this->flow_invoice($draft, 0);
        $this->assertTrue($invoice->create_invoice_from_data($this->invoice_dto(false)));
        $this->assertSame('INV-OLD', $invoice->invoiceid);

        $invoice = $this->flow_invoice($draft, 1);
        $this->assertTrue($invoice->create_invoice_from_data($this->invoice_dto(true)));
        $this->assertSame('INV-OLD', $invoice->invoiceid);
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

    /**
     * ERPNext builds the Payment Entry; it is booked to the configured account with the provider's reference.
     */
    public function test_payment_entry_goes_to_configured_account(): void {
        $this->resetAfterTest();
        set_config('erpnextpaymentaccount', '2820 - Stripe - WB', 'local_shopping_cart');
        $invoice = new erpnext_invoice();
        $this->set_private($invoice, 'paymentreference', 'in_123');

        // Mocked responses are returned last in, first out.
        \curl::mock_response(json_encode(['data' => ['name' => '2820 - Stripe - WB', 'account_currency' => 'EUR']]));
        \curl::mock_response(json_encode(['message' => [
            'doctype' => 'Payment Entry',
            '__islocal' => 1,
            '__unsaved' => 1,
            'name' => null,
            'paid_from' => '2002 - Lieferforderungen USD - WB',
            'paid_to' => '2800 - Erste Bank - WB',
            'paid_to_account_currency' => 'EUR',
            'bank_account' => 'Erste Bank Konto',
            'bank' => 'Erste Bank',
            'paid_amount' => 400.0,
            'received_amount' => 357.37,
            'source_exchange_rate' => 0.89342,
            'references' => [[
                'reference_doctype' => 'Sales Invoice',
                'reference_name' => 'INV-1',
                'allocated_amount' => 400.0,
                'doctype' => 'Payment Entry Reference',
                '__islocal' => 1,
            ]],
        ]]));

        $entry = $invoice->build_payment_entry('INV-1');

        $this->assertSame('2820 - Stripe - WB', $entry['paid_to']);
        $this->assertNull($entry['bank_account']);
        $this->assertNull($entry['bank']);
        $this->assertSame('in_123', $entry['reference_no']);
        $this->assertSame(date('Y-m-d'), $entry['reference_date']);
        // ERPNext's amounts and exchange rates are kept.
        $this->assertSame(357.37, $entry['received_amount']);
        $this->assertSame(0.89342, $entry['source_exchange_rate']);
        // Client-side markers of the unsaved document are removed, also from its rows.
        $this->assertArrayNotHasKey('__islocal', $entry);
        $this->assertArrayNotHasKey('name', $entry);
        $this->assertArrayNotHasKey('__islocal', $entry['references'][0]);
        $this->assertSame('INV-1', $entry['references'][0]['reference_name']);
    }

    /**
     * Without a saved setting payments go to the Stripe account; an empty setting keeps ERPNext's default.
     */
    public function test_payment_account_setting(): void {
        $this->resetAfterTest();
        unset_config('erpnextpaymentaccount', 'local_shopping_cart');
        $this->assertSame('2820 - Stripe - WB', erpnext_invoice::get_payment_account());
        set_config('erpnextpaymentaccount', '', 'local_shopping_cart');
        $this->assertSame('', erpnext_invoice::get_payment_account());

        // The default account is kept as calculated by ERPNext, without asking for its currency.
        $invoice = new erpnext_invoice();
        \curl::mock_response(json_encode(['message' => [
            'paid_to' => '2800 - Erste Bank - WB',
            'paid_to_account_currency' => 'EUR',
            'bank_account' => 'Erste Bank Konto',
            'references' => [['reference_name' => 'INV-1']],
        ]]));
        $entry = $invoice->build_payment_entry('INV-1');
        $this->assertSame('2800 - Erste Bank - WB', $entry['paid_to']);
        $this->assertSame('Erste Bank Konto', $entry['bank_account']);
        $this->assertSame('INV-1-' . date('Y-m-d'), $entry['reference_no']);
    }

    /**
     * A payment account in another currency than ERPNext calculated with is refused.
     */
    public function test_payment_account_in_other_currency_is_refused(): void {
        $this->resetAfterTest();
        set_config('erpnextpaymentaccount', 'Stripe USD - WB', 'local_shopping_cart');
        $invoice = new erpnext_invoice();
        \curl::mock_response(json_encode(['data' => ['account_currency' => 'USD']]));
        \curl::mock_response(json_encode(['message' => [
            'paid_to' => '2800 - Erste Bank - WB',
            'paid_to_account_currency' => 'EUR',
            'references' => [['reference_name' => 'INV-1']],
        ]]));
        $this->assertNull($invoice->build_payment_entry('INV-1'));
        $this->assertStringContainsString('Stripe USD - WB', $invoice->errormessage);
    }

    /**
     * A paid invoice gets no second payment.
     */
    public function test_paid_invoice_is_not_paid_again(): void {
        $this->resetAfterTest();
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['get_invoice', 'build_payment_entry', 'submit_payment_entry'])
            ->getMock();
        $invoice->method('get_invoice')->willReturn(['docstatus' => 1, 'outstanding_amount' => 0]);
        $invoice->expects($this->never())->method('build_payment_entry');
        $invoice->expects($this->never())->method('submit_payment_entry');

        $this->assertTrue($invoice->book_payment('INV-1'));
    }

    /**
     * A draft Payment Entry left by an earlier run is submitted instead of creating a second one.
     */
    public function test_draft_payment_entry_is_reused(): void {
        $this->resetAfterTest();
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['get_invoice', 'build_payment_entry', 'submit_payment_entry'])
            ->getMock();
        $invoice->method('get_invoice')->willReturn(['docstatus' => 1, 'outstanding_amount' => 400]);
        $invoice->expects($this->never())->method('build_payment_entry');
        $invoice->expects($this->once())->method('submit_payment_entry')->with('ACC-PAY-1')->willReturn(true);
        \curl::mock_response(json_encode(['data' => [['name' => 'ACC-PAY-1']]]));

        $this->assertTrue($invoice->book_payment('INV-1'));
    }

    /**
     * An unpaid invoice gets a new Payment Entry, which is submitted.
     */
    public function test_payment_is_created_and_submitted(): void {
        $this->resetAfterTest();
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['get_invoice', 'build_payment_entry', 'submit_payment_entry'])
            ->getMock();
        $invoice->method('get_invoice')->willReturn(['docstatus' => 1, 'outstanding_amount' => 400]);
        $invoice->method('build_payment_entry')->willReturn(['paid_to' => '2820 - Stripe - WB']);
        $invoice->expects($this->once())->method('submit_payment_entry')->with('ACC-PAY-2')->willReturn(true);
        // Last in, first out: the draft lookup finds nothing, then the insert returns the new entry.
        \curl::mock_response(json_encode(['data' => ['name' => 'ACC-PAY-2', 'docstatus' => 0]]));
        \curl::mock_response(json_encode(['data' => []]));

        $this->assertTrue($invoice->book_payment('INV-1'));
    }

    /**
     * A retried cart task completes the invoice of the first attempt instead of creating a second one.
     */
    public function test_cart_retry_reuses_invoice(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('local_shopping_cart_invoices', (object) [
            'identifier' => 4711,
            'invoiceid' => 'INV-OLD',
            'timecreated' => time(),
        ]);

        foreach ([0 => 1, 1 => 0] as $docstatus => $submits) {
            $invoice = $this->getMockBuilder(erpnext_invoice::class)
                ->onlyMethods(['get_invoice', 'submit_invoice', 'book_payment', 'prepare_json_invoice_data'])
                ->getMock();
            $invoice->method('get_invoice')->willReturn(['docstatus' => $docstatus]);
            $invoice->expects($this->exactly($submits))->method('submit_invoice')->willReturn(true);
            $invoice->expects($this->once())->method('book_payment')->with('INV-OLD')->willReturn(true);
            $invoice->expects($this->never())->method('prepare_json_invoice_data');

            $this->assertTrue($invoice->create_invoice(4711));
            $this->assertSame('INV-OLD', $invoice->invoiceid);
        }
        $this->assertSame(1, $DB->count_records('local_shopping_cart_invoices', ['identifier' => 4711]));
    }

    /**
     * A failed payment is reported, not hidden, and the invoice is kept for the retry.
     */
    public function test_failed_payment_is_reported(): void {
        $this->resetAfterTest();
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['find_open_invoice_by_reference', 'submit_invoice', 'book_payment'])
            ->getMock();
        $invoice->method('find_open_invoice_by_reference')->willReturn(['name' => 'INV-1', 'docstatus' => 1]);
        $invoice->expects($this->never())->method('submit_invoice');
        $invoice->expects($this->once())->method('book_payment')->willReturn(false);

        $data = $this->invoice_dto(true);
        $data->markpaid = true;
        $this->assertFalse($invoice->create_invoice_from_data($data));
        $this->assertTrue($invoice->paymentfailed);
        $this->assertSame('INV-1', $invoice->invoiceid);
    }

    /**
     * Without markpaid an existing submitted invoice is left alone (the marketplace flow).
     */
    public function test_existing_invoice_without_markpaid_is_not_paid(): void {
        $this->resetAfterTest();
        $invoice = $this->getMockBuilder(erpnext_invoice::class)
            ->onlyMethods(['find_open_invoice_by_reference', 'submit_invoice', 'book_payment'])
            ->getMock();
        $invoice->method('find_open_invoice_by_reference')->willReturn(['name' => 'INV-1', 'docstatus' => 1]);
        $invoice->expects($this->never())->method('submit_invoice');
        $invoice->expects($this->never())->method('book_payment');

        $this->assertTrue($invoice->create_invoice_from_data($this->invoice_dto(true)));
    }

    /**
     * When ERPNext cannot be asked for an existing invoice, no second invoice is created.
     */
    public function test_failed_reference_lookup_throws(): void {
        $this->resetAfterTest();
        $invoice = new erpnext_invoice();
        \curl::mock_response('<html><body>502 Bad Gateway</body></html>');

        $this->expectException(\moodle_exception::class);
        $invoice->find_open_invoice_by_reference('in_123');
    }

    /**
     * A missing customer is reported as missing; any other failure throws instead of creating a duplicate.
     */
    public function test_customer_exists_distinguishes_missing_from_failure(): void {
        $this->resetAfterTest();
        $invoice = new erpnext_invoice();
        $this->set_private($invoice, 'customername', 'Jane Doe - 4');

        \curl::mock_response(json_encode(['exc_type' => 'DoesNotExistError']));
        $this->assertFalse($invoice->customer_exists());

        \curl::mock_response('');
        $this->expectException(\moodle_exception::class);
        $invoice->customer_exists();
    }

    /**
     * Unexpected responses keep their content in the error message, not only "unknown issue".
     */
    public function test_validate_response_keeps_unexpected_body(): void {
        $this->resetAfterTest();
        $invoice = new erpnext_invoice();

        $this->assertFalse($invoice->validate_response('<html><h1>502 Bad Gateway</h1></html>', 'https://erp/x'));
        $this->assertStringContainsString('unexpected response: 502 Bad Gateway', $invoice->errormessage);
        $this->assertStringContainsString('https://erp/x', $invoice->errormessage);

        $error = json_encode([
            'exc_type' => 'LinkValidationError',
            '_server_messages' => json_encode([json_encode(['message' => 'Could not find Paid To: Erste Bank - WB'])]),
        ]);
        $this->assertFalse($invoice->validate_response($error, 'https://erp/pe'));
        $this->assertStringContainsString('LinkValidationError: Could not find Paid To: Erste Bank - WB', $invoice->errormessage);

        $this->assertTrue($invoice->validate_response(json_encode(['data' => ['name' => 'X']]), 'https://erp/ok'));
    }
}
