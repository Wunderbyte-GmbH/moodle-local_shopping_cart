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
 * Mobile cache definitions.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$definitions = [
    'cacheshopping' => [ // This is the store which is used for nomrmal shopping, see cartstore class.
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
    'cashier' => [
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
    'schistory' => [ // Say: Shopping Cart history. Only used during the actual checkout.
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
    'cachedcashreport' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
        'invalidationevents' => ['setbackcachedcashreport'],
    ],
    'cacherebooking' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
];
