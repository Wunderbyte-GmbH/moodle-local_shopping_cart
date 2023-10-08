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
 * Baseurl for download of cash report.
 * @copyright 2023 Wunderbyte Gmbh <info@wunderbyte.at>
 * @author  David Bogner
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\event\checkout_completed;

require_once("../../config.php");

global $CFG, $PAGE;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/emulate_checkout.php');

$userid = required_param('userid', PARAM_INT);
$identifier = required_param('identifier', PARAM_INT);

if (!empty($userid)) {
    $event = checkout_completed::create([
            'context' => $context,
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                    'identifier' => $identifier,
            ],
    ]);
    $event->trigger();
}
echo "Event triggered";