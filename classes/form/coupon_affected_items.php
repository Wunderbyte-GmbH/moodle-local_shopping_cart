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

namespace local_shopping_cart\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

use context;
use context_system;
use core_form\dynamic_form;
use html_writer;
use local_shopping_cart\shopping_cart;
use moodle_url;

/**
 * Dynamic add edit coupon form.
 * @copyright Wunderbyte GmbH <info@wunderbyte.at>
 * @package   local_shopping_cart
 * @author Jacob Viertel
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupon_affected_items extends dynamic_form {
    /**
     * Define the form fields.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $couponid = $this->optional_param('id', 0, PARAM_INT);
        $coupon = $DB->get_record(
            'local_shopping_cart_coupons',
            ['id' => $couponid],
            'id, coupontype',
            MUST_EXIST
        );
        $mform->addElement('html', '<style>.modal-footer [data-action="save"]{display:none!important}</style>');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($coupon->coupontype === 'couponoptout') {
            $mform->addElement(
                'html',
                '<div class="alert alert-info">' . get_string('couponoptoutitemsnotice', 'local_shopping_cart') . '</div>'
            );
        }

        $records = $DB->get_records_select(
            'local_shopping_cart_iteminfo',
            "json LIKE :optin OR json LIKE :optout",
            ['optin' => '%"couponoptin"%', 'optout' => '%"couponoptout"%'],
            '',
            'id, itemid, componentname, area, json'
        );

        // First pass: filter records and collect itemids grouped by component and area.
        $filteredrows = [];
        $bycomponentarea = [];
        foreach ($records as $record) {
            $json    = json_decode($record->json ?? '');
            $optins  = !empty($json->couponoptin) ? explode(',', $json->couponoptin) : [];
            $optouts = !empty($json->couponoptout) ? explode(',', $json->couponoptout) : [];

            if (
                $coupon->coupontype === 'couponoptin' &&
                in_array((string)$couponid, $optins)
            ) {
                $type = get_string('couponoptin', 'local_shopping_cart');
            } else if (
                $coupon->coupontype === 'couponoptout' &&
                !in_array((string)$couponid, $optouts)
            ) {
                $type = get_string('couponoptout', 'local_shopping_cart');
            } else {
                continue;
            }

            $filteredrows[] = [
                'componentname' => $record->componentname,
                'area'          => $record->area,
                'itemid'        => $record->itemid,
                'type'          => $type,
            ];
            if (empty($bycomponentarea[$record->componentname])) {
                $bycomponentarea[$record->componentname] = [];
            }
            if (empty($bycomponentarea[$record->componentname][$record->area])) {
                $bycomponentarea[$record->componentname][$record->area] = [];
            }
            $bycomponentarea[$record->componentname][$record->area][] = (int)$record->itemid;
        }

        // Resolve names and, optionally, links via component adapter callbacks.
        $names = [];
        $links = [];
        foreach ($bycomponentarea as $componentname => $byarea) {
            foreach ($byarea as $area => $itemids) {
                $itemids = array_values(array_unique($itemids));
                if (empty($itemids)) {
                    continue;
                }

                // New adapter path: ask component service_provider for names.
                $providerclass = null;
                try {
                    $providerclass = shopping_cart::get_service_provider_classname($componentname);
                } catch (\coding_exception $e) {
                    $providerclass = null;
                }

                if (!empty($providerclass)) {
                    $resolved = component_class_callback(
                        $providerclass,
                        'resolve_item_names',
                        [$itemids, $area],
                        []
                    );
                    foreach ((array)$resolved as $id => $name) {
                        $names["$componentname-$id"] = (string)$name;
                    }

                    // Optional: components may provide a link to view the item.
                    $resolvedlinks = component_class_callback(
                        $providerclass,
                        'resolve_item_links',
                        [$itemids, $area],
                        []
                    );
                    foreach ((array)$resolvedlinks as $id => $url) {
                        if (!empty($url)) {
                            $links["$componentname-$id"] = (string)$url;
                        }
                    }
                }
            }
        }

        $rows = '';
        foreach ($filteredrows as $row) {
            $namekey = $row['componentname'] . '-' . $row['itemid'];
            $itemname = $names[$namekey] ?? ($row['componentname'] . ' #' . $row['itemid']);

            if (!empty($links[$namekey])) {
                $itemnamehtml = html_writer::link($links[$namekey], s($itemname), ['target' => '_blank']);
            } else {
                $itemnamehtml = s($itemname);
            }

            $rows .= html_writer::tag(
                'tr',
                html_writer::tag('td', $itemnamehtml) .
                html_writer::tag('td', s($row['componentname'])) .
                html_writer::tag('td', s($row['area'])) .
                html_writer::tag('td', $row['itemid']) .
                html_writer::tag('td', $row['type'])
            );
        }

        if (empty($rows)) {
            $html = html_writer::tag('p', get_string('noitemsaffected', 'local_shopping_cart'));
        } else {
            $header = html_writer::tag(
                'tr',
                html_writer::tag('th', get_string('itemname', 'local_shopping_cart')) .
                html_writer::tag('th', get_string('component', 'local_shopping_cart')) .
                html_writer::tag('th', get_string('area', 'local_shopping_cart')) .
                html_writer::tag('th', get_string('id', 'local_shopping_cart')) .
                html_writer::tag('th', get_string('coupontype', 'local_shopping_cart'))
            );
            $html = html_writer::tag(
                'table',
                html_writer::tag('thead', $header) . html_writer::tag('tbody', $rows),
                ['class' => 'table table-sm']
            );
        }

        $mform->addElement('html', $html);
    }

    /**
     * Set data for dynamic submission.
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(['id' => $this->optional_param('id', 0, PARAM_INT)]);
    }

    /**
     * Process the dynamic submission.
     *
     * @return array
     */
    public function process_dynamic_submission(): array {
        return [];
    }

    /**
     * Get context for dynamic submission.
     *
     * @return context
     */
    public function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Check access for dynamic submission.
     *
     * @return void
     */
    public function check_access_for_dynamic_submission(): void {
        require_login();
    }

    /**
     * Get page URL for dynamic submission.
     *
     * @return moodle_url
     */
    public function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/local/shopping_cart/coupons.php');
    }
}
