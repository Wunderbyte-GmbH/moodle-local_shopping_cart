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

namespace local_shopping_cart;

use admin_setting_configtextarea;

/**
 * The tax categories settings class - show tax categories settings
 *
 * @package    local_shopping_cart
 * @copyright  2022 Maurice Wohlkönig <maurice@whlk.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_taxcategories extends admin_setting_configtextarea {

    /**
     * Validate the contents of the textarea as taxcategories
     * Used to validate a new line separated list of tax categories collected from a textarea control.
     *
     * @param string $data A list of categories separated by new lines
     * @return mixed bool true for success or string:error on failure
     */
    public function validate($data) {
        $valid = taxcategories::is_valid_raw_string($data);
        if (!$valid) {
            return get_string('taxcategories_invalid', 'local_shopping_cart');
        }
        return true;
    }
}
