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

namespace local_shopping_cart\local;

/**
 * Resolves display names for cart items by component.
 *
 * Add an entry to COMPONENT_MAP when a new plugin integrates with shopping_cart.
 * Each entry maps a frankenstyle component name to its DB table and name field.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_name_resolver {
    /**
     * Map of componentname => [table, namefield].
     * 'table' is the unprefixed Moodle table name (without curly braces).
     * 'namefield' is the column that holds the human-readable name.
     */
    protected const COMPONENT_MAP = [
        'mod_booking' => ['table' => 'booking_options', 'namefield' => 'text'],
    ];

    /**
     * Return the DB table name for a given component, or null if unknown.
     *
     * @param string $component frankenstyle component name (e.g. 'mod_booking')
     * @return array|null
     */
    public static function get_table_data(string $component): ?array {
        return self::COMPONENT_MAP[$component] ?? null;
    }
}
