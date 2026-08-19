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
use local_shopping_cart\external\confirm_cash_payment;
use local_shopping_cart\external\get_history_item;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\create_invoice;
use local_shopping_cart\local\daily_sums_pdf;

/**
 * The template based PDFs (receipts / invoices, daily sums) are PDF/A-2b when the setting
 * local_wunderbyte_table/pdfaenabled is on - and exactly the previous plain TCPDF output when
 * it is off.
 *
 * Structural checks only; the full ISO validation runs when VERAPDF_BIN points to
 * the veraPDF CLI (local check).
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_shopping_cart\local\create_invoice::create_receipt
 * @covers \local_shopping_cart\local\WBPDF
 * @covers \local_shopping_cart\local\daily_sums_pdf
 */
final class pdfa_documents_test extends advanced_testcase {
    /**
     * Fonts that must never appear unembedded (TCPDF core fonts).
     */
    private const COREFONTS = '#/BaseFont\s*/(Helvetica|Times|Courier|Symbol|ZapfDingbats)#';

    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('pdfaenabled', 1, 'local_wunderbyte_table');
    }

    /**
     * Mandatory clean-up after each test.
     */
    public function tearDown(): void {
        parent::tearDown();
        cartstore::reset();
    }

    /**
     * Buys one test item at the cashier and returns the ledger identifier and the buyer.
     *
     * @return array [identifier, user]
     */
    private function buy_test_item(): array {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ärzte', 'lastname' => 'Müller-Lüdenscheidt']);
        $this->setUser($user);
        $result = add_item_to_cart::execute('local_shopping_cart', 'testarea', 1, 0);
        $this->assertEquals(1, $result['success']);

        $this->setAdminUser();
        $purchase = confirm_cash_payment::execute($user->id, 3, 'pdfa test');
        $this->assertEquals(1, $purchase['status']);

        $historyitem = get_history_item::execute('local_shopping_cart', 'testarea', 1, $user->id);
        $identifier = (int)$DB->get_field('local_shopping_cart_history', 'identifier', ['id' => $historyitem['id']]);
        $this->assertGreaterThan(0, $identifier);
        return [$identifier, $user];
    }

    /**
     * Structural PDF/A-2b assertions.
     *
     * @param string $pdf
     */
    private function assert_pdfa2b(string $pdf): void {
        $this->assertStringStartsWith('%PDF-1.7', $pdf);
        $this->assertStringContainsString('<pdfaid:part>2</pdfaid:part>', $pdf);
        $this->assertStringContainsString('<pdfaid:conformance>B</pdfaid:conformance>', $pdf);
        $this->assertStringContainsString('/OutputIntents', $pdf);
        $this->assertDoesNotMatchRegularExpression(self::COREFONTS, $pdf, 'Unembedded core font found');
        $this->assertStringNotContainsString('/DeviceCMYK', $pdf);
        $descriptors = preg_match_all('#/Type\s*/FontDescriptor#', $pdf);
        $this->assertGreaterThan(0, $descriptors);
        $this->assertSame($descriptors, preg_match_all('#/FontFile2\s+\d+ 0 R#', $pdf));
        // Subset fonts: a fully embedded FreeSerif+FreeSans document is > 2.5 MB.
        $this->assertLessThan(400 * 1024, strlen($pdf));
        $this->assert_verapdf($pdf);
    }

    /**
     * Runs veraPDF when VERAPDF_BIN points to the CLI (optional local check, skipped otherwise).
     *
     * @param string $pdf
     */
    private function assert_verapdf(string $pdf): void {
        $bin = getenv('VERAPDF_BIN');
        if (empty($bin) || !is_executable($bin)) {
            return;
        }
        $file = make_request_directory() . '/pdfa.pdf';
        file_put_contents($file, $pdf);
        $output = shell_exec(escapeshellarg($bin) . ' -f 2b --format text -v ' . escapeshellarg($file) . ' 2>&1');
        $this->assertStringStartsWith('PASS', trim((string)$output), "veraPDF: $output");
    }

    /**
     * Small RGBA PNG (logo with transparency).
     *
     * @return string
     */
    private function create_alpha_png(): string {
        $image = imagecreatetruecolor(40, 20);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 5, 5, 35, 15, imagecolorallocatealpha($image, 249, 128, 18, 40));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        return $png;
    }

    /**
     * Receipt with the built-in default template (no receipthtml configured).
     *
     * @runInSeparateProcess
     */
    public function test_receipt_default_template_is_pdfa(): void {
        [$identifier, $user] = $this->buy_test_item();
        set_config('receipthtml', '', 'local_shopping_cart');

        $pdf = create_invoice::create_receipt($identifier, $user->id, '', true);
        $this->assert_pdfa2b($pdf);
        // The default template's <h1> style used the core font "times" before.
        $this->assertStringContainsString('FreeSerif', $pdf);
    }

    /**
     * Receipt with an admin template that uses everything an admin might put in there:
     * header/footer sections, core font names via CSS, a transparent logo, links, umlauts.
     *
     * @runInSeparateProcess
     */
    public function test_receipt_custom_template_is_pdfa(): void {
        [$identifier, $user] = $this->buy_test_item();
        $logo = 'data:image/png;base64,' . base64_encode($this->create_alpha_png());
        $template = '<header><h1 style="font-family: Helvetica, Arial, sans-serif">Wunderbyte GmbH</h1></header>'
            . '<footer><p style="font-family: monospace">UID: ATU12345678 – '
            . '<a href="https://www.wunderbyte.at">wunderbyte.at</a></p></footer>'
            . '<table><tr><td><img src="' . $logo . '" width="80"></td><td>Datum: [[date]] [[datetime]]</td></tr></table>'
            . '<h1>Rechnung [[invoice_number]] für [[firstname]] [[lastname]]</h1>'
            . '<p style="font-family: Times New Roman, serif">Bestellnummer [[order_number]] – [[mail]]</p>'
            . '<table border="1"><tr><th>#</th><th>Name</th><th>Preis</th></tr>'
            . '[[items]]<tr><td>[[pos]]</td><td>[[name]]</td><td>[[price]] EUR</td></tr>[[/items]]'
            . '</table><pre>Summe: [[sum]] EUR</pre>';
        set_config('receipthtml', $template, 'local_shopping_cart');

        $pdf = create_invoice::create_receipt($identifier, $user->id, '', true);
        $this->assert_pdfa2b($pdf);
        // Helvetica/sans-serif -> FreeSans, Times/serif -> FreeSerif, monospace/pre -> FreeMono.
        $this->assertStringContainsString('FreeSans', $pdf);
        $this->assertStringContainsString('FreeSerif', $pdf);
        $this->assertStringContainsString('FreeMono', $pdf);
        // Transparency (soft mask) is allowed in PDF/A-2.
        $this->assertStringContainsString('/SMask', $pdf);
        $this->assertStringContainsString('/URI (https://www.wunderbyte.at)', $pdf);
    }

    /**
     * Daily sums PDF, mustache default and configured HTML template.
     *
     * @runInSeparateProcess
     */
    public function test_daily_sums_pdf_is_pdfa(): void {
        $this->buy_test_item();
        $this->setAdminUser();
        $date = date('Y-m-d');

        set_config('dailysumspdfhtml', '', 'local_shopping_cart');
        $pdf = daily_sums_pdf::create($date)->Output('daily_sums.pdf', 'S');
        $this->assert_pdfa2b($pdf);

        set_config(
            'dailysumspdfhtml',
            '<h1 style="font-family: sans-serif">[[title]] [[date]]</h1><p>Total: [[totalsum]] [[currency]], '
            . 'cash & cards: [[cashandcards]]</p><pre>printed [[printdate]]</pre>',
            'local_shopping_cart'
        );
        $pdf = daily_sums_pdf::create($date)->Output('daily_sums.pdf', 'S');
        $this->assert_pdfa2b($pdf);
        $this->assertStringContainsString('FreeSans', $pdf);
        $this->assertStringContainsString('FreeMono', $pdf);
    }

    /**
     * With the setting off the receipt is generated as before: plain TCPDF, header/footer in
     * unembedded Helvetica, no PDF/A markers.
     *
     * @runInSeparateProcess
     */
    public function test_receipt_without_setting_is_plain_tcpdf(): void {
        set_config('pdfaenabled', 0, 'local_wunderbyte_table');
        [$identifier, $user] = $this->buy_test_item();
        set_config(
            'receipthtml',
            '<header><h1>Wunderbyte GmbH</h1></header><footer><p>Footer</p></footer>'
            . '<h1>Rechnung [[invoice_number]]</h1><table>[[items]]<tr><td>[[name]]</td><td>[[price]]</td></tr>[[/items]]</table>',
            'local_shopping_cart'
        );

        $pdf = create_invoice::create_receipt($identifier, $user->id, '', true);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('pdfaid:part', $pdf);
        $this->assertStringNotContainsString('/OutputIntents', $pdf);
        // WBPDF::Header()/Footer() use the core font helvetica, unembedded as before.
        $this->assertMatchesRegularExpression('#/BaseFont\s*/Helvetica#', $pdf);
        // The inline <style> of create_receipt() requests the core font times for <h1>.
        $this->assertMatchesRegularExpression('#/BaseFont\s*/Times-#', $pdf);
        $this->assertStringNotContainsString('FreeSans', $pdf);
    }

    /**
     * With the setting off the daily sums PDF is plain TCPDF as before.
     *
     * @runInSeparateProcess
     */
    public function test_daily_sums_without_setting_is_plain_tcpdf(): void {
        set_config('pdfaenabled', 0, 'local_wunderbyte_table');
        $this->buy_test_item();
        $this->setAdminUser();
        set_config(
            'dailysumspdfhtml',
            '<h1 style="font-family: sans-serif">[[title]]</h1><p>[[totalsum]]</p>',
            'local_shopping_cart'
        );

        $doc = daily_sums_pdf::create(date('Y-m-d'));
        $this->assertSame(\TCPDF::class, get_class($doc));
        $pdf = $doc->Output('daily_sums.pdf', 'S');
        $this->assertStringNotContainsString('pdfaid:part', $pdf);
        $this->assertMatchesRegularExpression('#/BaseFont\s*/Helvetica#', $pdf);
    }
}
