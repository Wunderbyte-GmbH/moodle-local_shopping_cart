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
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\pricemodifier;

use core_component;

/**
 * Class modifier_base
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modifier_info {


    public static function apply_modfiers(array &$data) {

        $modifiers = core_component::get_component_classes_in_namespace(
            'local_shopping_cart',
            'local\pricemodifier\modifiers'
        );

        $modifiers = array_keys($modifiers);
        usort($modifiers, fn($a, $b) => ($a::$id > $b::$id ? 1 : -1 ));
        foreach ($modifiers as $modifier) {
            // $class = new $modifier();
            // get ids from modifier and sort 
            $modifier::apply($data);
        }
    }

}
