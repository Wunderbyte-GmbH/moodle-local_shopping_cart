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
use local_shopping_cart\shopping_cart\service_provider;
use local_shopping_cart\shopping_cart_history;

require_once(__DIR__ . '/../../config.php');
require_login();

global $PAGE, $OUTPUT, $CFG;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url("{$CFG->wwwroot}/local/shopping_cart/demo.php");

$PAGE->set_title(get_string('testing:title', 'local_shopping_cart'));
$PAGE->set_heading(get_string('testing:title', 'local_shopping_cart'));
$PAGE->navbar->add(get_string('testing:title', 'local_shopping_cart'), new moodle_url('/local/shopping_cart/demo.php'));

$renderer = $PAGE->get_renderer('local_shopping_cart');

echo $OUTPUT->header();

echo html_writer::div(get_string('testing:description', 'local_shopping_cart'), 'alert alert-info',
        ['style' => 'width: 500px;']);

// This cartitem data is not really used (except for itemid), because data is fetched from service_provider.
// See \local_shopping_cart\shopping_cart\service_provider for real values.
$now = time();
$canceluntil = strtotime('+14 days', $now);
$serviceperiodestart = $now;
$serviceperiodeend = strtotime('+100 days', $now);
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
        'A',
        0,
        '');
$button = new button($item->as_array());

echo html_writer::div(get_string('testing:description', 'local_shopping_cart'), 'alert alert-info',
        ['style' => 'width: 500px;']);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div(get_string('testing:item', 'local_shopping_cart') . ' 1', 'h4');
echo html_writer::div("10.00 " . get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: dummy item description', 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

// Cartitem's demo data is fetched from service_provider.
// See \local_shopping_cart\shopping_cart\service_provider for values.
$item = service_provider::load_cartitem('main', 2);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

$item = service_provider::load_cartitem('main', 3);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

$item = service_provider::load_cartitem('main', 4);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

$item = service_provider::load_cartitem('main', 5);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

$item = service_provider::load_cartitem('main', 6);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

$item = service_provider::load_cartitem('main', 7);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

$item = service_provider::load_cartitem('main', 8);
$item = $item['cartitem']->as_array();
$button = new button($item);

echo '<div class="testitem-container shadow bg-light rounded border border-primary p-3 mb-5">';
echo html_writer::div($item['itemname'], 'h4');
echo html_writer::div('Price: ' . $item['price'] . ' '. get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR', 'h5');
echo html_writer::div('Description: ' . $item['description'], 'h6');
echo html_writer::div($renderer->render_button($button), 'testbutton-container mt-1',
        ['style' => 'width: 300px;']);
echo '</div>';

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
