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
 * @package    local_shopping_cart
 * @copyright  2021 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $modnode The node to add module settings to
 *
 * $settings is unused, but API requires it. Suppress PHPMD warning.
 *
 */
function local_shopping_cart_extend_navigation($navigation) {

}

/**
 * Renders the popup.
 *
 * @param renderer_base $renderer
 * @return string The HTML
 */
function local_shopping_cart_render_navbar_output(\renderer_base $renderer) {
    global $USER, $CFG;

    // Early bail out conditions.
    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $output = '';
    $data = [];
    $cache = local_shopping_cart_get_cache();
    if ($cache) {
        $count = count($cache['item']);
        $data['item'] = array_values($cache['item']);
        $data['count'] = $count;
        $data['price'] = array_sum(array_column($data['item'], 'price'));
    }
    $output .= $renderer->render_from_template('local_shopping_cart/shopping_cart_popover', $data);
    return $output;
}

/**
 *
 * Get saved files for the page
 *
 * @param mixed $course
 * @param mixed $birecordorcm
 * @param mixed $context
 * @param mixed $filearea
 * @param mixed $args
 * @param bool $forcedownload
 * @param array $options
 */
function local_shopping_cart_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    $fs = get_file_storage();

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if ($filearea === 'description') {
        if (!$file = $fs->get_file($context->id, 'local_entities', 'entitycontent', 0, $filepath, $filename) or $file->is_directory()) {
            send_file_not_found();
        }
    } else if ($filearea === 'image') {
        $itemid = array_pop($args);
        $file = $fs->get_file($context->id, 'local_entities', $filearea, $itemid, '/', $filename);
        // Todo: Maybe put in fall back image.
    }

    \core\session\manager::write_close();
    send_stored_file($file, null, 0, $forcedownload, $options);
}

function local_shopping_cart_get_cache() {
    global $USER;
    $userid = $USER->id;
    $cache = \cache::make('local_shopping_cart', 'cacheshopping');
    $cachedrawdata = $cache->get($userid . '_shopping_cart');
    return $cachedrawdata;
}
