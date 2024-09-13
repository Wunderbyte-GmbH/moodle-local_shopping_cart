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

use local_shopping_cart\local\create_invoice;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/pdflib.php');

require_login();

// Include the main TCPDF library (search for installation path).
$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/receipt.php');

$PAGE->set_title('Receipt');
$PAGE->set_heading('Receipt');

create_invoice::create_receipt($id, $userid);
