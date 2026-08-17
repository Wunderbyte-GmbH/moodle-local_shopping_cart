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
 * The document itself is built by {@see \local_shopping_cart\local\daily_sums_pdf}
 * (PDF/A-2b); this script only handles access control and output.
 *
 * @package     local_shopping_cart
 * @copyright   2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer-Sengseis
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\local\daily_sums_pdf;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

$date = required_param('date', PARAM_TEXT);

$context = context_system::instance();

if (!has_capability('local/shopping_cart:cashier', $context)) {
    throw new moodle_exception('cashiercapabilitymissing');
}

$PAGE->set_context($context);
$PAGE->set_url('/daily_sums_pdf.php');

$PAGE->set_title('Daily sums');
$PAGE->set_heading('Daily sums');

// Discard any stray output, so the PDF stream stays intact.
ob_start();
$pdf = daily_sums_pdf::create($date);
ob_end_clean();

// Close and output PDF document.
$pdf->Output('daily_sums_' . $date . '.pdf', 'I');
