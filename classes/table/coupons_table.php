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

namespace local_shopping_cart\table;
use html_writer;
use local_wunderbyte_table\output\table;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir . '/tablelib.php');

use dml_exception;
use local_wunderbyte_table\wunderbyte_table;

/**
 * Report table to show the cash report.
 *
 * @package     local_shopping_cart
 * @copyright   2025 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Georg Maißer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupons_table extends wunderbyte_table {
    /**
     * This function is called for each data row to allow processing of the
     * 'price' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered price.
     * @throws dml_exception
     */
    public function col_discount(object $values): string {

        if (empty($values->discountpercentage)) {
            return format_float(0, 2) . ' %';
        } else {
            return format_float((float)$values->discountabsolute, 2)
                    . ' ' . $values->currency;
        }
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'timecreated' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_timecreated(object $values): string {
        $rendereddate = '';

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d H:i:s', $values->timecreated);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y H:i:s', $values->timecreated);
        } else {
            $rendereddate = date('Y-m-d H:i:s', $values->timecreated);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'timemodified' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_timemodified(object $values): string {
        $rendereddate = '';

        if (empty($values->timemodified)) {
            $values->timemodified = $values->timecreated;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d H:i:s', $values->timemodified);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y H:i:s', $values->timemodified);
        } else {
            $rendereddate = date('Y-m-d H:i:s', $values->timemodified);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'serviceperiodstart' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_starttime(object $values): string {
        $rendereddate = '';

        if (empty($values->starttime)) {
            return $rendereddate;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d', $values->starttime);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y', $values->starttime);
        } else {
            $rendereddate = date('Y-m-d', $values->starttime);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'serviceperiodend' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_endtime(object $values): string {
        $rendereddate = '';

        if (empty($values->endtime)) {
            return $rendereddate;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d', $values->endtime);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y', $values->endtime);
        } else {
            $rendereddate = date('Y-m-d', $values->endtime);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'coupontype' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_coupontype(object $values): string {
        if (empty($values->coupontype)) {
            return '';
        }
        $key = $values->coupontype;
        if (get_string($key, 'local_shopping_cart')) {
            return get_string($key, 'local_shopping_cart');
        }
        return $key;
    }


    /**
     * Column with action buttons.
     *
     * @param stdClass $values
     *
     * @return string
     *
     */
    public function col_action(stdClass $values): string {

        global $OUTPUT;

        $actionbuttons[] = [
            'label' => get_string('editcoupon', 'local_shopping_cart'),
            'class' => 'btn btn-primary',
            'href' => '#',
            'formname' => 'local_shopping_cart\\form\\addedit_coupon',
            'nomodal' => false,
            'selectionmandatory' => false,
            'id' => $values->id,
            'data' => [
                'id' => $values->id,
                'titlestring' => 'editcoupon',
                'bodystring' => 'editcoupon',
                'submitbuttonstring' => 'editcoupon',
                'component' => 'local_shopping_cart',
                'labelcolumn' => 'coupon',
                'noselectionbodystring' => 'editcoupon',
            ],
        ];

        $actionbuttons[] = [
            'label' => get_string('showwhereactive', 'local_shopping_cart'),
            'class' => 'btn btn-secondary',
            'href' => '#',
            'formname' => 'local_shopping_cart\\form\\coupon_affected_items',
            'nomodal' => false,
            'selectionmandatory' => false,
            'id' => $values->id,
            'data' => [
                'id' => $values->id,
                'titlestring' => 'showwhereactive',
                'component' => 'local_shopping_cart',
                'labelcolumn' => 'coupon',
            ],
        ];

        table::transform_actionbuttons_array($actionbuttons);

        return $OUTPUT->render_from_template('local_wunderbyte_table/component_actionbutton', ['showactionbuttons' => $actionbuttons]);
    }

    /**
     * Add coupon.
     *
     * @param int $id
     * @param string $data
     * @return array
     */
    public function action_addcoupon(int $id, string $data): array {

        return [
            'success' => 1,
            'message' => 'Did work',
        ];
    }
}
