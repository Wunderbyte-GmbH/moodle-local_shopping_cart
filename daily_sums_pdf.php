<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Generate PDF of daily sums from shopping cart for a specific day.
 *
 * @package     local_shopping_cart
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer-Sengseis
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\shopping_cart;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

// Include the main TCPDF library (search for installation path).
$date = required_param('date', PARAM_TEXT);

$context = context_system::instance();

if (!has_capability('local/shopping_cart:cashier', $context)) {
    throw new moodle_exception('cashiercapabilitymissing');
}

$commaseparator = current_language() == 'de' ? ',' : '.';

ob_start();

// Create new PDF document.
$pdf = new TCPDF('p', 'pt', 'A4', true, 'UTF-8', false);
// Set some content to print.

$PAGE->set_context($context);
$PAGE->set_url('/daily_sums_pdf.php');

$PAGE->set_title('Daily sums');
$PAGE->set_heading('Daily sums');

// Get the daily sums data.
$data = shopping_cart::get_daily_sums_data($date);

// Calculate the sum of all not-online payments.
$creditpart = (float) $data['creditcard'] ?? 0.0;
$debitpart = (float) $data['debitcard'] ?? 0.0;
$cashpart = (float) $data['cash'] ?? 0.0;
$cashandcards = number_format($creditpart + $debitpart + $cashpart, 2, $commaseparator, '');

$html = get_config('local_shopping_cart', 'dailysumspdfhtml');
if (empty($html)) {
    // No template defined, so use default mustache template.
    $html = $OUTPUT->render_from_template('local_shopping_cart/report_daily_sums_pdf', $data);
} else {
    // Only if HTML template is defined in settings, we use it.
    // At first, replace all placeholders.
    $html = str_replace("[[title]]", $data['title'] ?? '', $html);
    $html = str_replace("[[date]]", $data['date'] ?? '', $html);
    $html = str_replace("[[printdate]]", $data['printdate'] ?? '', $html);
    $html = str_replace("[[totalsum]]", $data['totalsum'] ?? '0.00', $html);
    $html = str_replace("[[currency]]", $data['currency'] ?? '', $html);
    $html = str_replace("[[online]]", $data['online'] ?? '0.00', $html);
    $html = str_replace("[[cash]]", $data['cash'] ?? '0.00', $html);
    $html = str_replace("[[creditcard]]", $data['creditcard'] ?? '0.00', $html);
    $html = str_replace("[[debitcard]]", $data['debitcard'] ?? '0.00', $html);
    $html = str_replace("[[manual]]", $data['manual'] ?? '0.00', $html);
    $html = str_replace("[[creditspaidbackcash]]", $data['creditspaidbackcash'] ?? '0.00', $html);
    $html = str_replace("[[creditspaidbacktransfer]]", $data['creditspaidbacktransfer'] ?? '0.00', $html);
    $html = str_replace("[[cashandcards]]", $cashandcards ?? '', $html);
}


// Set document information.
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor("$USER->firstname $USER->lastname");
$pdf->SetTitle('daily_sums_' . $date);
$pdf->SetSubject('Daily sums');
$pdf->SetKeywords('daily sums');

// Set header and footer fonts.
$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

// Set default monospaced font.
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set auto page breaks.
$pdf->SetAutoPageBreak(false, PDF_MARGIN_BOTTOM);

// Set image scale factor.
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set default font subsetting mode.
$pdf->setFontSubsetting(true);

$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Remove default footer.
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->AddPage();

// Print text using writeHTMLCell().
$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

ob_end_clean();

// Close and output PDF document.
// This method has several options, check the source code documentation for more information.
$pdf->Output('daily_sums_' . $date . '.pdf', 'I');
