<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin event observers are registered here.
 *
 * @package     local_shopping_cart
 * @category    event
 * @copyright   2021 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\local_shopping_cart\event\item_added',
        'callback' => 'local_shopping_cart_observer::item_added',
    ),
    array(
        'eventname' => '\local_shopping_cart\event\item_deleted',
        'callback' => 'local_shopping_cart_observer::item_deleted',
    ),
    array(
        'eventname' => '\local_shopping_cart\event\item_expired',
        'callback' => 'local_shopping_cart_observer::item_expired',
    ),
    array(
        'eventname' => '\local_shopping_cart\event\item_bought',
        'callback' => 'local_shopping_cart_observer::item_bought',
    ),
    array(
        'eventname' => '*',
        'callback' => 'local_shopping_cart_observer::payment_error',
    ),
);
