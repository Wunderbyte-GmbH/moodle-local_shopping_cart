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
 * Installments page.
 *
 * @package         local_shopping_cart
 * @author          Georg Maißer
 * @copyright       2024 Wunderbyte GmbH
 * @license         http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use local_shopping_cart\output\installments;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

require_login();

global $USER, $PAGE, $OUTPUT, $CFG, $ME;

// Get the id of the page to be displayed.
$userid = optional_param('userid', 0, PARAM_INT);

if (empty($userid)) {
    $userid = $USER->id;
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/installments.php");
$PAGE->set_title(get_string('installments', 'local_shopping_cart'));
$PAGE->set_heading(get_string('installments', 'local_shopping_cart'));
// Set the page layout.
$PAGE->set_pagelayout('base');

echo $OUTPUT->header();
// Convert numbers to strings with 2 fixed decimals right before rendering.
$installments = new installments($userid);
$data = $installments->returnaslist();

echo $OUTPUT->render_from_template('local_shopping_cart/pages/installments', $data);
// Now output the footer.
echo $OUTPUT->footer();
