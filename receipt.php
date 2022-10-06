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
 * Generate receipt after cashier has confirmed payment.
 *
 * @package     local_shopping_cart
 * @copyright   2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

// Include the main TCPDF library (search for installation path).
$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$context = context_system::instance();

// Only cashier is allowed to create receipts.
if (has_capability('local/shopping_cart:cashier', $context)) {

    ob_start();

    // Create new PDF document.
    $pdf = new TCPDF('p', 'pt', 'A4', true, 'UTF-8', false);
    // Set some content to print.

    $PAGE->set_context($context);
    $PAGE->set_url('/receipt.php');

    $PAGE->set_title('Receipt');
    $PAGE->set_heading('Receipt');

    $filename = get_config('local_shopping_cart' , 'receiptimage');
    $cfghtml = get_config('local_shopping_cart', 'receipthtml');
    $context = \context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_shopping_cart', 'local_shopping_cart_receiptimage');
    foreach ($files as $file) {
        if ($file->get_filesize() > 0) {
            $filename = $file->get_filename();
            $imgurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                $file->get_itemid(), $file->get_filepath(), $file->get_filename(), true);
        }
    }

    $items = local_shopping_cart\shopping_cart_history::return_data_via_identifier($id, $userid);
    $timecreated = $items[array_key_first($items)]->timecreated;
    $date = date("Y-m-d", $timecreated);
    $userid = $items[array_key_first($items)]->userid;

    global $DB;
    $userdetails = $DB->get_record('user', array('id' => $userid));

    $cfghtml = str_replace("[[date]]", $date, $cfghtml);
    $cfghtml = str_replace("[[firstname]]", $userdetails->firstname, $cfghtml);
    $cfghtml = str_replace("[[lastname]]", $userdetails->lastname, $cfghtml);
    $cfghtml = str_replace("[[mail]]", $userdetails->email, $cfghtml);

    $prehtml = explode('[[items]]', $cfghtml);
    $repeathtml = explode('[[/items]]', $prehtml[1]);
    $posthtml = $repeathtml[1];

    $pos = 1;
    $sum = 0;
    $itemhtml = '';
    foreach ($items as $item) {
        $tmp = str_replace("[[price]]", $item->price, $repeathtml[0]);
        $tmp = str_replace("[[name]]", $item->itemname, $tmp);
        $tmp = str_replace("[[pos]]", $pos, $tmp);
        $sum += $item->price;
        $itemhtml .= $tmp;
        $pos++;
    }
    $posthtml = str_replace("[[sum]]", $sum, $posthtml);
    $html = '
    <style>
        h1 {
            color: black;
            font-family: times;
            font-size: 24pt;
        }
        td {
            border-bottom: 1px solid #c3c3c3;
        }
        tr {
            border: 1px solid #c3c3c3;
            background-color: #ffffee;
        }
    </style>
    '. $prehtml[0] . $itemhtml . $posthtml;
    // Print text using writeHTMLCell().

    // Set document information.
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($userdetails->email);
    $pdf->SetTitle($userid.' '.$date);
    $pdf->SetSubject('');
    $pdf->SetKeywords('');

    // Set header and footer fonts.
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

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

    if (isset($imgurl)) {
        $pdf->Image($imgurl->out(false), 0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), "", "", "",
            true, "300", "", false, false, 0);
    }
    $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);

    ob_end_clean();

    // Close and output PDF document.
    // This method has several options, check the source code documentation for more information.
    $pdf->Output($userdetails->firstname . '_' . $userdetails->lastname . '_' . $date . '.pdf', 'I');
} else {
    global $OUTPUT, $PAGE;
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/shopping_cart/receipt.php', ['id' => $id, 'userid' => $userid]));
    $PAGE->set_title(get_string('error'));
    $PAGE->set_heading(get_string('error'));

    echo $OUTPUT->header();
    echo get_string('error:cachiercapabilitymissing', 'local_shopping_cart');
    echo $OUTPUT->footer();
}
