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
use local_shopping_cart\local\item_name_resolver;
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
        $mform->addElement('html', '<style>.modal-footer [data-action="save"]{display:none!important}</style>');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $records = $DB->get_records_select(
            'local_shopping_cart_iteminfo',
            "json LIKE :optin OR json LIKE :optout",
            ['optin' => '%"couponoptin"%', 'optout' => '%"couponoptout"%'],
            '',
            'id, itemid, componentname, area, json'
        );

        // First pass: filter records and collect itemids grouped by component.
        $filteredrows = [];
        $bycomponent  = [];
        foreach ($records as $record) {
            $json    = json_decode($record->json ?? '');
            $optins  = !empty($json->couponoptin) ? explode(',', $json->couponoptin) : [];
            $optouts = !empty($json->couponoptout) ? explode(',', $json->couponoptout) : [];

            if (in_array((string)$couponid, $optins)) {
                $type = get_string('couponoptin', 'local_shopping_cart');
            } else if (!in_array((string)$couponid, $optouts)) {
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
            $bycomponent[$record->componentname][] = (int)$record->itemid;
        }

        // Batch-fetch names: one query per unique component (mod_booking → {booking}).
        $names = [];
        $dbman = $DB->get_manager();
        foreach ($bycomponent as $componentname => $itemids) {
            $tabledata = item_name_resolver::get_table_data($componentname);
            if (empty($tabledata)) {
                continue;
            }
            if (!$dbman->table_exists($tabledata['table'])) {
                continue;
            }
            [$insql, $inparams] = $DB->get_in_or_equal(array_unique($itemids), SQL_PARAMS_NAMED);
            $namerecords = $DB->get_records_select(
                $tabledata['table'],
                "id $insql",
                $inparams,
                '',
                'id, ' . $tabledata['namefield']
            );
            foreach ($namerecords as $rec) {
                $names["$componentname-{$rec->id}"] = $rec->{$tabledata['namefield']};
            }
        }

        $rows = '';
        foreach ($filteredrows as $row) {
            $namekey = $row['componentname'] . '-' . $row['itemid'];
            $itemname = $names[$namekey] ?? ($row['componentname'] . ' #' . $row['itemid']);

            $rows .= html_writer::tag(
                'tr',
                html_writer::tag('td', s($itemname)) .
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
