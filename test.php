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
 * Testfile to see how wunderbyte_table works.
 *
 * @package     local_shopping_cart
 * @copyright   2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\output\button;

require_once(__DIR__ . '/../../config.php');
require_login();

$syscontext = context_system::instance();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/test.php');

$PAGE->set_title('Testing');
$PAGE->set_heading('Testing local_shopping_cart');
$PAGE->navbar->add('Testing local_shopping_cart', new moodle_url('/test.php'));
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_shopping_cart');

$item = new cartitem(1, 'Testitem 1', 10, 'EUR', 'local_shopping_cart', 'My Testitem 1 description');
$data = $item->getitem();
$data = new button($data);
echo $renderer->render_button($data);
$item = new cartitem(2, 'Testitem 2', 20, 'EUR', 'local_shopping_cart', 'My Testitem 2 description');
$data = $item->getitem();
$data = new button($data);
echo $renderer->render_button($data);
echo $OUTPUT->footer();
