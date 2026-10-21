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
 * Moodle form to select the period for the daily sums in the cash report.
 *
 * @package   local_shopping_cart
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Bernhard Fischer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_shopping_cart\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use core_date;
use moodleform;

/**
 * Moodle form to select the period (from - to, with time of day) for daily sums in cash report.
 *
 * @package   local_shopping_cart
 * @copyright 2022 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @author    Bernhard Fischer
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class daily_sums_date_selector_form extends moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {

        $mform = $this->_form;

        // Default: the current day, from midnight to 23:59. The daily sums always use the
        // timezone of the server, no matter which timezone the user has set.
        [$defaultfrom, $defaultto] = self::default_period();
        $timezone = core_date::get_server_timezone();

        $mform->addElement(
            'date_time_selector',
            'dailysumsfrom',
            get_string('dailysums:from', 'local_shopping_cart'),
            ['step' => 1, 'timezone' => $timezone]
        );
        $mform->setType('dailysumsfrom', PARAM_INT);
        $mform->setDefault('dailysumsfrom', $defaultfrom);

        $mform->addElement(
            'date_time_selector',
            'dailysumsto',
            get_string('dailysums:to', 'local_shopping_cart'),
            ['step' => 1, 'timezone' => $timezone]
        );
        $mform->setType('dailysumsto', PARAM_INT);
        $mform->setDefault('dailysumsto', $defaultto);

        $mform->addElement('hidden', 'wbtfilter', ''); // We don't really need this here, values will be appended later.
        $mform->setType('wbtfilter', PARAM_RAW);

        $this->add_action_buttons(false);
    }

    /**
     * The default period: today from 00:00 to 23:59 in the timezone of the server.
     *
     * @return int[] [from, to] as timestamps
     */
    public static function default_period(): array {
        $timezone = core_date::get_server_timezone();
        $today = usergetdate(time(), $timezone);
        $from = make_timestamp($today['year'], $today['mon'], $today['mday'], 0, 0, 0, $timezone);
        $to = make_timestamp($today['year'], $today['mon'], $today['mday'], 23, 59, 0, $timezone);
        return [$from, $to];
    }

    /**
     * The end of the period must not lie before its start.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];
        if (!empty($data['dailysumsfrom']) && !empty($data['dailysumsto']) && $data['dailysumsto'] < $data['dailysumsfrom']) {
            $errors['dailysumsto'] = get_string('error:dailysums:rangeinvalid', 'local_shopping_cart');
        }
        return $errors;
    }
}
