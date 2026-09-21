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
 * Which rendering the checkout uses.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process;

/**
 * Which rendering the checkout uses.
 *
 * The checkout knows two renderings: the current one, where the steps are
 * dynamic forms inside a card and tab layout, and the legacy one, which is the
 * checkout of the USI release line. The legacy rendering is a frozen state, so
 * everything that was added after it (guest checkout, the coupon field in the
 * checkout, the live cart badge) is absent there.
 *
 * Every place that has to tell the two apart asks this class, so the setting is
 * read in one place only.
 */
class checkout_mode {
    /**
     * Whether the checkout renders as it does on the USI release line.
     *
     * @return bool
     */
    public static function is_legacy(): bool {
        return !empty(get_config('local_shopping_cart', 'legacycheckout'));
    }
}
