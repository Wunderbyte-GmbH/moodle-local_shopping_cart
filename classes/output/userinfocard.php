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
 * This class prepares data for displaying user information.
 *
 * @package   local_shopping_cart
 * @copyright 2025 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\output;

use renderer_base;
use renderable;
use stdClass;
use templatable;

/**
 * This class prepares data for displaying user information.
 *
 * @package   local_shopping_cart
 * @copyright 2025 Wunderbyte GmbH {@link http://www.wunderbyte.at}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userinfocard implements renderable, templatable {
    /** @var stdClass $data */
    public $data = [];

    /**
     * Constructor.
     *
     * @param integer $userid
     */
    public function __construct(int $userid, string $fields) {

        global $CFG, $DB, $OUTPUT;

        if (empty($fields)) {
            return 'You can add fields like this in the shortcode \'fields="firstname,lastname"\'';
        }

        require_once("$CFG->dirroot/user/profile/lib.php");

        $user = $DB->get_record("user", ["id" => $userid]);
        if (empty($user)) {
            return;
        }
        profile_load_data($user);

        $fields = explode(',', $fields);
        $this->data['firstname'] = $user->firstname;
        $this->data['lastname'] = $user->lastname;
        $this->data['email'] = $user->email;
        $this->data['id'] = $user->id;

        $options = [
            'visibletoscreenreaders' => false,
            'size' => 150,
            'link' => true, // Make image clickable - the link leads to user profile.
            'popup' => true, // Open in popup.
        ];

        $this->data['picture'] = $OUTPUT->user_picture($user, $options);

        foreach ($user as $key => $value) {
            if (!in_array($key, $fields)) {
                continue;
            }

            $additionaldata[] = [
                'key' => get_string($key, 'core'),
                'value' => format_string($value), // So we can use mlang filters.
            ];
        }

        profile_load_custom_fields($user);

        foreach ($user->profile as $key => $value) {
            if (!in_array($key, $fields)) {
                continue;
            }
            $localized = $DB->get_field('user_info_field', 'name', ['shortname' => $key]);
            $localized = format_string($localized);

            // Convert unix timestamps to rendered dates.
            if (is_numeric($value)) {
                if (strlen((string)$value) > 8 && strlen((string)$value) < 12) {
                    // Localized time format.
                    switch (current_language()) {
                        case 'de':
                            $format = "d.m.Y";
                            break;
                        default:
                            $format = "Y-m-d";
                            break;
                    }
                    $value = date($format, $value);
                }
            }

            $additionaldata[] = [
                'key' => $localized,
                'value' => format_string($value), // So we can use mlang filters.
            ];
        }

        $this->data['additionaldata'] = $additionaldata ?? [];
    }

    /**
     * Export for template.
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        $returnarray = [
                'user' => (array)$this->data,
        ];

        return $returnarray;
    }
}
