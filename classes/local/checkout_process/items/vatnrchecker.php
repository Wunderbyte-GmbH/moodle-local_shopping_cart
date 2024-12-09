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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\items;

use local_shopping_cart\form\dynamicvatnrchecker;
use local_shopping_cart\local\checkout_process\checkout_base_item;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnrchecker extends checkout_base_item {
    /**
     * VATNRCHECKERURL
     * @var string
     */
    const VATNRCHECKERURL = 'https://ec.europa.eu/taxation_customs/vies/rest-api//check-vat-number';

    /**
     * Renders checkout item.
     * @return bool
     */
    public static function is_active() {
        if (
            get_config('local_shopping_cart', 'showvatnrchecker')
            && !empty(get_config('local_shopping_cart', 'owncountrycode'))
            && !empty(get_config('local_shopping_cart', 'ownvatnrnumber'))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Checks status of checkout item.
     * @return string
     */
    public static function get_icon_progress_bar() {
        return 'fa-solid fa-file-invoice';
    }

    /**
     * Checks status of checkout item.
     * @return array
     */
    public static function render_body() {
        global $PAGE;
        $vatnrchecker = new dynamicvatnrchecker();
        $vatnrchecker->set_data_for_dynamic_submission();
        $template = $vatnrchecker->render();
        return [
            'template' => $template,
        ];
    }
}
