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
 * Moodle hooks for local_shopping_cart
 * @package    local_shopping_cart
 * @copyright  2021 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\shopping_cart;

/**
 * Adds module specific settings to the settings block
 *
 * @param navigation_node $navigation The node to add module settings to
 * @return void
 */
function local_shopping_cart_extend_navigation(navigation_node $navigation) {
    $context = context_system::instance();
    if (has_capability('local/shopping_cart:cachier', $context)) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)) {
            $nodehome = $navigation;
        }
        $pluginname = get_string('pluginname', 'local_shopping_cart');
        $link = new moodle_url('/local/shopping_cart/cashier.php', array());
        $icon = new pix_icon('i/shopping_cart', $pluginname, 'local_shopping_cart');
        $nodecreatecourse = $nodehome->add($pluginname, $link, navigation_node::NODETYPE_LEAF, $pluginname,
            'shopping_cart_cashier', $icon);
        $nodecreatecourse->showinflatnavigation = true;
    }
}

/**
 * Function local_shopping_cart_extend_settings_navigation
 *
 * @param settings_navigation $settingsnav
 * @param context $context
 * @return void
 */
function local_shopping_cart_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;

    if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
        $strfoo = get_string('foo', 'local_shopping_cart');
        $url = new moodle_url('/local/myplugin/foo.php', array('id' => $PAGE->course->id));
        $foonode = navigation_node::create(
            $strfoo,
            $url,
            navigation_node::NODETYPE_LEAF,
            'myplugin',
            'myplugin',
            new pix_icon('t/addcontact', $strfoo)
        );
        if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
            $foonode->make_active();
        }
        $settingnode->add_node($foonode);
    }
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
    $cache = shopping_cart::local_shopping_cart_get_cache_data($USER->id);
    $output .= $renderer->render_from_template('local_shopping_cart/shopping_cart_popover', $cache);
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
function local_shopping_cart_pluginfile($course,
                                        $birecordorcm,
                                        $context,
                                        $filearea,
                                        $args,
                                        $forcedownload,
                                        array $options = array()) {
    $fs = get_file_storage();

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if ($filearea === 'description') {
        if (!$file = $fs->get_file($context->id,
                                    'local_entities',
                                    'entitycontent',
                                    0,
                                    $filepath,
                                    $filename) or $file->is_directory()) {
            send_file_not_found();
        }
    } else if ($filearea === 'image') {
        $itemid = array_pop($args);
        $file = $fs->get_file($context->id, 'local_shopping_cart', $filearea, $itemid, '/', $filename);
        // Todo: Maybe put in fall back image.
    }

    \core\session\manager::write_close();
    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Get icon mapping for font-awesome.
 *
 * @return  array
 */
function local_shopping_cart_get_fontawesome_icon_map() {
    return [
        'local_shopping_cart:i/shopping_cart' => 'fa-shopping-cart',
        'local_shopping_cart:t/selected' => 'fa-check',
        'local_shopping_cart:t/subscribed' => 'fa-envelope-o',
        'local_shopping_cart:t/unsubscribed' => 'fa-envelope-open-o',
        'local_shopping_cart:t/star' => 'fa-star',
    ];
}
