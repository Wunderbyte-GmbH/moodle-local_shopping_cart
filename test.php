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
 * Testfile to simulate adding items to shopping cart
 *
 * @package     local_shopping_cart
 * @copyright   2021 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\output\button;
use local_shopping_cart\shopping_cart_history;

require_once(__DIR__ . '/../../config.php');
require_login();

$syscontext = context_system::instance();
global $PAGE, $OUTPUT, $CFG;
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/test.php');

$PAGE->set_title('Testing');
$PAGE->set_heading('Testing local_shopping_cart');
$PAGE->navbar->add('Testing local_shopping_cart', new moodle_url('/test.php'));
echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('local_shopping_cart');

$now = time();
$canceluntil = strtotime('+14 days', $now);
$serviceperiodestart = $now;
$serviceperiodeend = strtotime('+100 days', $now);

// This cartitem data is not really used (except for itemid), because data is fetched from service_provider.
// See \local_shopping_cart\shopping_cart\service_provider for real values.
$item = new cartitem(
        1,
        '1',
        10.00,
        get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
        'local_shopping_cart',
        'main',
        '',
        '',
        $canceluntil,
        $serviceperiodestart,
        $serviceperiodeend,
        'A');
$button = new button($item->as_array());
echo $renderer->render_button($button);

$item = new cartitem(
        2,
        '2',
        20.30,
        get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
        'local_shopping_cart',
        'main',
        '',
        '',
        $canceluntil,
        $serviceperiodestart,
        $serviceperiodeend,
        'B');
$button = new button($item->as_array());
echo $renderer->render_button($button);

$item = new cartitem(
        3,
        '3',
        13.8,
        get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
        'local_shopping_cart',
        'main',
        '',
        '',
        $canceluntil,
        $serviceperiodestart,
        $serviceperiodeend,
        'C');
$button = new button($item->as_array());
echo $renderer->render_button($button);

$item = new cartitem(
    4,
    '4',
    12.12,
    get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
    'local_shopping_cart',
    'main',
    '',
    '',
    $canceluntil,
    $serviceperiodestart,
    $serviceperiodeend,
    '');
$button = new button($item->as_array());
echo $renderer->render_button($button);

echo '<div style="width: 300px" class="mt-3">';
$data = [
        'checkouturl' => $CFG->wwwroot . "/local/shopping_cart/checkout.php",
        'count' => 2,
];
echo $OUTPUT->render_from_template('local_shopping_cart/checkout_button', $data);
echo '</div>';

$history = new shopping_cart_history();
// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
/* $data = $history->prepare_data_from_cache($USER->id);*/

echo format_text("[shoppingcarthistory]");

echo $OUTPUT->footer();
