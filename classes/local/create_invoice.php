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
 * Functions for SAP text files (daily SAP sums for M:USI).
 *
 * @package local_shopping_cart
 * @since Moodle 4.0
 * @copyright 2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author Bernhard Fischer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use context_system;
use core_payment\account;
use core_user;
use Exception;
use html_writer;
use local_entities\entitiesrelation_handler;
use local_shopping_cart\addresses;
use local_shopping_cart\invoice\invoicenumber;
use local_shopping_cart\shopping_cart_history;
use moodle_exception;
use moodle_url;
use stdClass;
use stored_file;
use TCPDF;

/**
 * Deals with local_shortcodes regarding booking.
 */
class create_invoice {
    /**
     * Create sap files from date.
     *
     * @param int $identifier
     * @param int $userid
     *
     * @return void
     * @throws \file_exception
     * @throws \stored_file_creation_exception
     * @throws moodle_exception
     */
    public static function create_invoice_files_from_identifier(int $identifier, int $userid): void {
        global $DB, $USER;

        // 1. Have a look if we have the invoice already.
        // 2. If not, get the content of the file.
        // 3. Save it to the moodle filesystem
        // 4. Save it to the data root

        $contextid = context_system::instance()->id;
        $component = 'local_shopping_cart';
        $filearea = 'invoices';
        $itemid = $identifier;
        $filepath = "/";
        $filename = self::return_invoice_name($identifier, $userid);

        $fs = get_file_storage();
        $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
        if (!$file) {
            $fileinfo = [
                    'contextid' => $contextid,
                    'component' => $component,
                    'filearea' => $filearea,
                    'itemid' => $itemid,
                    'filepath' => $filepath,
                    'filename' => $filename,
            ];

            $content = self::create_receipt($identifier, $userid, '', true);

            $file = $fs->create_file_from_string($fileinfo, $content);

            // Collect all files in single directory.
            self::copy_file_to_dir($file, $filename);
        }
    }

    /**
     * Collect all files without errors in a single directory.
     *
     * @param stored_file $file
     * @param string $filename
     * @return void
     */
    public static function copy_file_to_dir(stored_file $file, string $filename) {
        global $CFG;
        // Copy the file to a directory in moodleroot, create dir if it does not.
        try {
            $dataroot = $CFG->dataroot;
            $datadir = $dataroot . '/'
                . (empty(get_config('local_shopping_cart', 'pathtoinvoices'))
                    ? 'local_shopping_cart_invoices'
                    : get_config('local_shopping_cart', 'pathtoinvoices'));
            $filepath = $datadir . "/" . $filename;

            // Make sure to escape double "/".
            $filepath = str_replace("//", "/", $filepath);
            if (!is_dir($datadir)) {
                // Create the directory if it doesn't exist.
                if (!make_writable_directory($datadir)) {
                    // Handle directory creation error (e.g., display an error message).
                    throw new moodle_exception('errorcreatingdirectory', 'local_shopping_cart');
                }
            }
            // Copy the file to the sapfiles directory.
            if (file_exists($filepath)) {
                return;
            }
            if (!$file->copy_content_to($filepath)) {
                throw new moodle_exception('errorcopyingfiles', 'local_shopping_cart');
            }
        } catch (Exception $e) {
            // Exception handling.
            $message = $e->getMessage();
            $code = $e->getCode();
            // Get detailed information about $file.
            $fileinfo = [
                    'filename' => $file->get_filename(),
                    'filepath' => $file->get_filepath(),
                    'filesize' => $file->get_filesize(),
                    'filearea' => $file->get_filearea(),
                    'timecreated' => $file->get_timecreated(),

            ];
            debugging("Moodle Exception: $message (Code: $code). File Info: " . var_dump($fileinfo, true));
        }
    }

    /**
     * Return invoice name including pdf suffix. Userid might allow adding firstname, lastname in the future.
     *
     * @param int $identifier
     * @param int $userid
     *
     * @return string
     *
     */
    private static function return_invoice_name(int $identifier, int $userid = 0) {

        $name = invoicenumber::get_invoicenumber_by_identifier($identifier);

        if (empty($name)) {
            $name = "$identifier";
        }

        return $name . '.pdf';
    }

    /**
     * This function creates the content of the pdf.
     *
     * @param int $identifier
     * @param int $userid
     * @param string $filename
     * @param bool $asstring
     * @param string $idcol can be 'identifier' for normal receipts, or 'id' (ledger id)
     *                      for special receipts like for credits paid back for example
     * @param int $paymentstatus the payment status from ledger table
     *
     * @return string
     */
    public static function create_receipt(
        int $identifier,
        int $userid,
        string $filename = '',
        bool $asstring = false,
        string $idcol = 'identifier',
        int $paymentstatus = 2 // 2 means LOCAL_SHOPPING_CART_PAYMENT_SUCCESS.
    ): string {

        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        require_login();

        $context = context_system::instance();

        $commaseparator = current_language() == 'de' ? ',' : '.';

        // Localized date format.
        switch (current_language()) {
            case 'de':
                $dateformat = "d.m.Y";
                $datetimeformat = "d.m.Y, H:i";
                break;
            default:
                $dateformat = "Y-m-d";
                $datetimeformat = "Y-m-d, g:i a";
                break;
        }

        ob_start();

        // Create new PDF document.
        $pdf = new TCPDF('p', 'pt', 'A4', true, 'UTF-8', false);
        // Set some content to print.

        // HTML templates.
        $cfghtml = get_config('local_shopping_cart', 'receipthtml'); // Default HTML template.
        $extrareceiptshtml = get_config('local_shopping_cart', 'extrareceiptshtml'); // For extra receipts like credits correction.
        $cancelconfirmationshtml = get_config('local_shopping_cart', 'cancelconfirmationshtml'); // For cancel confirmations.

        switch ($idcol) {
            case 'id':
                // In this case $identifier stores the ledger id.
                $items = shopping_cart_history::return_data_from_ledger_via_id($identifier);
                $newidentifier = $items[array_key_first($items)]->identifier;
                if (!empty($newidentifier)) {
                    $identifier = $newidentifier;
                }
                break;
            case 'identifier':
            default:
                $items = shopping_cart_history::return_data_from_ledger_via_identifier($identifier);
                break;
        }

        $timecreated = $items[array_key_first($items)]->timecreated;
        $payment = $items[array_key_first($items)]->payment;

        // Check if there as a separate HTML template for special rows without identifier.
        if (in_array($payment, [8, 9])) {
            if (!empty(trim(strip_tags($extrareceiptshtml)))) {
                // If it's not empty, we use it instead of the default HTML template.
                $cfghtml = $extrareceiptshtml;
            }
        } else if ($paymentstatus == 3) { // 3 means LOCAL_SHOPPING_CART_PAYMENT_CANCELED.
            // For cancel confirmations we have an identifier and a payment status of 3.
            if (!empty(trim(strip_tags($cancelconfirmationshtml)))) {
                $cfghtml = $cancelconfirmationshtml;
            } else if (!empty(trim(strip_tags($extrareceiptshtml)))) {
                // Fallback.
                $cfghtml = $extrareceiptshtml;
            }
        }

        // Fallback, if no HTML is set.
        if (empty(trim(strip_tags($cfghtml)))) {
            $cfghtml =
                '<table cellpadding="5" cellspacing="0" style="width: 100%; ">
                    <tr>
                        <td><!--<img src="url-to-your-logo"--></td>
                        <td style="text-align: right">
                        Date: [[date]]<br><br>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:1.3em; font-weight: bold;">
                        <br><br>
                        Booking confirmation<br>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: left;">Transaction number: [[id]]</td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align: left;">
                        [[firstname]] [[lastname]]<br>
                        [[mail]]
                        </td>
                    </tr>
                </table>
                <br><br><br>
                <table cellpadding="5" cellspacing="0" style="width: 100%;" border="0">
                    <tr style="background-color: #cccccc; padding:5px;">
                        <td style="text-align: center; width: 10%;"><b>#</b></td>
                        <td style="text-align: left; width: 30%;"><b>Name</b></td>
                        <td style="text-align: left; width: 15%;"><b>Location</b></td>
                        <td style="text-align: left; width: 10%;"><b>Day & Time</b></td>
                        <td style="text-align: center; width: 10%;"><b>Total</b></td>
                        <td style="text-align: center; width: 10%;"><b>Outstanding</b></td>
                        <td style="text-align: center; width: 15%;"><b>Paid</b></td>
                    </tr>
                    [[items]]
                    <tr>
                        <td style="text-align: center;">[[pos]]</td>
                        <td style="text-align: left;">[[name]]</td>
                        <td style="text-align: left;">[[location]]</td>
                        <td style="text-align: left;">[[dayofweektime]]</td>
                        <td style="text-align: right;">[[originalprice]] EUR</td>
                        <td style="text-align: right;">[[outstandingprice]] EUR</td>
                        <td style="text-align: right;">[[price]] EUR</td>
                    </tr>
                    [[/items]]
                </table>
                <hr>
                <table cellpadding="5" cellspacing="0" style="width: 100%;" border="0">
                    <tr>
                        <td colspan="3"><b>Total sum: </b></td>
                        <td style="text-align: right;"><b>[[sum]] EUR</b></td>
                    </tr>
                </table>';
        }

        // Make sure items are sorted from the most expensive on top to a credit (negative).
        usort($items, function ($a, $b) {
            return $b->price <=> $a->price; // Spaceship operator for comparison.
        });

        foreach ($items as $item) {
            if (empty($addressbilling)) {
                $addressbilling = $item->address_billing ?? 0;
            }
            if (empty($addressshipping)) {
                $addressshipping = $item->address_shipping ?? 0;
            }
        }

        $date = date($dateformat, $timecreated);
        $userid = $items[array_key_first($items)]->userid;

        global $DB;
        $user = core_user::get_user($userid);

        /*
        * TODO: We'll have to add the user profile picture in the future.
        * But it does not work with HTML. So we'll need a config setting
        * in shopping cart and have to add it using TCPDF.
        */
        // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
        /*$picturefile = null;
        if ($usercontext = context_user::instance($userid, IGNORE_MISSING)) {
            $fs = get_file_storage();
            $files = $fs->get_area_files($usercontext->id, 'user', 'icon');
            foreach ($files as $file) {
                $filenamewithoutextension = explode('.', $file->get_filename())[0];
                if ($filenamewithoutextension === 'f1') {
                    $picturefile = $file;
                    // We found it, so break the loop.
                    break;
                }
            }
        }
        if (!empty($picturefile)) {
            // Retrieve the image contents and encode them as base64.
            $picturedata = $picturefile->get_content();
            $picturebase64 = base64_encode($picturedata);
            // Now load the HTML of the image into the profilepicture param.
            $userpic = '<img src="data:image/image;base64,' . $picturebase64 . '" />';
        } else {
            $userpic = '';
        }*/

        $address = '';
        if (!empty($addressbilling)) {
            $billingaddress = addresses::get_address_string_for_user($userid, $addressbilling);
            $address .= $billingaddress;
        }
        if (!empty($addressshipping)) {
            $shippingaddress = addresses::get_address_string_for_user($userid, $addressshipping);
            $address .= "<br>" . html_writer::tag('b', get_string('addresses:shipping', 'local_shopping_cart'));
            $address .= ': <br>' .  $shippingaddress;
        }

        switch ($idcol) {
            case 'id':
                $invoicenumber = "{$identifier}"; // Special invoices start with X and have the ledger id as invoice number.
                $cfghtml = str_replace("[[id]]", "{$identifier}", $cfghtml);
                $cfghtml = str_replace("[[order_number]]", "{$identifier}", $cfghtml);
                break;
            case 'identifier':
            default:
                $invoicenumber = invoicenumber::get_invoicenumber_by_identifier($identifier);
                $cfghtml = str_replace("[[id]]", $identifier, $cfghtml);
                $cfghtml = str_replace("[[order_number]]", $identifier, $cfghtml);
                break;
        }

        $cfghtml = str_replace("[[date]]", $date, $cfghtml);
        $cfghtml = str_replace("[[username]]", $user->username, $cfghtml);
        $cfghtml = str_replace("[[firstname]]", $user->firstname, $cfghtml);
        $cfghtml = str_replace("[[lastname]]", $user->lastname, $cfghtml);
        $cfghtml = str_replace("[[mail]]", $user->email, $cfghtml);
        $cfghtml = str_replace("[[email]]", $user->email, $cfghtml);
        $cfghtml = str_replace("[[institution]]", $user->institution, $cfghtml);
        $cfghtml = str_replace("[[department]]", $user->department, $cfghtml);
        $cfghtml = str_replace("[[address]]", $address, $cfghtml);
        $cfghtml = str_replace("[[invoice_number]]", $invoicenumber ?: '', $cfghtml);

        // We also add the possibility to use any custom user profile field as param.
        if (empty($user->profile)) {
            $user->profile = [];
            profile_load_data($user);
            foreach ($user as $userkey => $uservalue) {
                if (substr($userkey, 0, 14) == "profile_field_") {
                    $profilefieldkey = str_replace('profile_field_', '', $userkey);
                    $user->profile[$profilefieldkey] = $uservalue;
                }
            }
        }
        foreach ($user->profile as $profilefieldkey => $profilefieldvalue) {
            if (!isset($user->{$profilefieldkey})) {
                // Convert unix timestamps to rendered dates.
                if (is_numeric($profilefieldvalue)) {
                    if (strlen((string)$profilefieldvalue) > 8 && strlen((string)$profilefieldvalue) < 12) {
                        $profilefieldvalue = date($dateformat, $profilefieldvalue);
                    }
                }
                if (is_array($profilefieldvalue)) {
                    $profilefieldvalue = $profilefieldvalue['text'] ?? json_encode($profilefieldvalue);
                }
                $cfghtml = str_replace("[[" . $profilefieldkey . "]]", "$profilefieldvalue", $cfghtml);
            }
        }

        $prehtml = explode('[[items]]', $cfghtml);
        $repeathtml = explode('[[/items]]', $prehtml[1]);
        $posthtml = $repeathtml[1];

        $pos = 1;
        $sum = 0.0;
        $itemhtml = '';
        foreach ($items as $item) {
            if (isset($item->schistoryid)) {
                $shistoryitem = $DB->get_record('local_shopping_cart_history', ['id' => $item->schistoryid]);
                $installmentdata = shopping_cart_history::get_installmentdata($shistoryitem);
            }
            if (empty($installmentdata)) {
                $price = $item->price;
                $tmp = str_replace(
                    "[[price]]",
                    number_format((float) $item->price, 2, $commaseparator, ''),
                    $repeathtml[0]
                );
                $tmp = str_replace(
                    "[[originalprice]]",
                    number_format((float) $item->price, 2, $commaseparator, ''),
                    $tmp
                );
                $tmp = str_replace(
                    "[[outstandingprice]]",
                    number_format(0.0, 2, $commaseparator, ''),
                    $tmp
                );
            } else {
                // In this case, price is what was really paid.
                $price = $shistoryitem->price;
                $tmp = str_replace(
                    "[[price]]",
                    number_format((float) $price, 2, $commaseparator, ''),
                    $repeathtml[0]
                );
                $tmp = str_replace(
                    "[[originalprice]]",
                    number_format((float) $installmentdata['originalprice'], 2, $commaseparator, ''),
                    $tmp
                );
                // Make sure to display the price that was actually already payed as price.
                $outstanding = 0;
                foreach ($installmentdata['payments'] as $payment) {
                    if (empty($payment->paid)) {
                        $outstanding += $payment->price;
                    }
                }
                $tmp = str_replace(
                    "[[outstandingprice]]",
                    number_format((float) $outstanding, 2, $commaseparator, ''),
                    $tmp
                );
            }
            $tmp = str_replace("[[name]]", $item->itemname, $tmp);
            $tmp = str_replace("[[pos]]", $pos, $tmp);
            $tmp = str_replace("[[credits]]", $item->credits ?? 0, $tmp);
            $tmp = str_replace("[[fee]]", $item->fee ?? 0, $tmp);
            $tmp = str_replace("[[discount]]", $item->discount ?? 0, $tmp);

            // If it's a booking option, we add option-specific data.
            if ($item->area == "option" && class_exists('mod_booking\singleton_service')) {
                $optionid = $item->itemid;
                $optionsettings = \mod_booking\singleton_service::get_instance_of_booking_option_settings($optionid);
                $bookingsettings = \mod_booking\singleton_service::get_instance_of_booking_settings_by_cmid($optionsettings->cmid);

                // If option has no semester id, then use semester id from instance.
                $semesterid = $optionsettings->semesterid ?? $bookingsettings->semesterid ?? 0;

                if (
                    empty($optionsettings->location) &&
                    !empty($optionsettings->sessions) &&
                    class_exists('local_entities\entitiesrelation_handler')
                ) {
                    // If no global location is given, use first entity of sessions.
                    $entitieshandler = new entitiesrelation_handler('mod_booking', 'optiondate');
                    foreach ($optionsettings->sessions as $id => $session) {
                        $entity = $entitieshandler->get_instance_data($id);
                        if (empty($entity)) {
                            continue;
                        }
                        $optionsettings->location = $entity->name ?? '';
                        break;
                    }
                }
                $tmp = str_replace("[[location]]", $optionsettings->location ?? '', $tmp); // Add location.
                $tmp = str_replace("[[dayofweektime]]", $optionsettings->dayofweektime ?? '', $tmp); // E.g. "Mo, 10:00 - 12:00".
                $coursestarttime = !empty($optionsettings->coursestarttime)
                    ? date($datetimeformat, $optionsettings->coursestarttime) . get_string('h', 'mod_booking') : $date;
                $tmp = str_replace("[[coursestarttime]]", $coursestarttime ?? '', $tmp); // E.g. "Mo, 10:00 - 12:00".
            } else {
                // Placeholders should be replaced with an empty string in case it's no booking option.
                $tmp = str_replace("[[location]]", '', $tmp);
                $tmp = str_replace("[[dayofweektime]]", '', $tmp);
                $tmp = str_replace("[[coursestarttime]]", '', $tmp);
            }

            $sum += $price;
            $itemhtml .= $tmp;
            $pos++;
        }

        $sumstring = number_format((float) $sum, 2, $commaseparator, '');
        $posthtml = str_replace("[[sum]]", $sumstring, $posthtml);
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
            }
        </style>
        ' . $prehtml[0] . $itemhtml . $posthtml;

        // Special handling for semester placeholder.
        if (empty($semesterid) && class_exists('mod_booking\booking')) {
            $semesterid = $DB->get_field_sql(
                "SELECT id
                FROM {booking_semesters}
                WHERE startdate <= :date1
                AND enddate >= :date2
                LIMIT 1",
                [
                    'date1' => $timecreated,
                    'date2' => $timecreated,
                ]
            );
        }
        if (!empty($semesterid) && class_exists('mod_booking\booking')) {
            $record = $DB->get_record('booking_semesters', ['id' => $semesterid]);
            $semestername = $record->name;
            $semestershort = $record->identifier;
            $semester = $semestername . " ($semestershort)";
            $html = str_replace("[[semestername]]", $semestername ?? '', $html);
            $html = str_replace("[[semestershort]]", $semestershort ?? '', $html);
            $html = str_replace("[[semester]]", $semester ?? '', $html);
        } else {
            $html = str_replace("[[semester]]", '', $html);
            $html = str_replace("[[semestername]]", '', $html);
            $html = str_replace("[[semestershort]]", '', $html);
        }

        // Set document information.
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($user->email);
        $pdf->SetTitle('bookingreceipt_' . $invoicenumber . '_' . $userid . '_' . $date);
        $pdf->SetSubject('');
        $pdf->SetKeywords('');

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
        // Now, we write the HTML into a TCPDF cell.
        $pdf->writeHTMLCell(0, 0, null, null, $html, 0, 1, 0, true, '', true);
        ob_end_clean();

        $filename = $user->firstname . '_' . $user->lastname . '_' . $date . '.pdf';
        $returnstring = $pdf->Output($filename, $asstring ? 'S' : 'I');

        // Close and output PDF document.
        // This method has several options, check the source code documentation for more information.
        return $returnstring;
    }
}
