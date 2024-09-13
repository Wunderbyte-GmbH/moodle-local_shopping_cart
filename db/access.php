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
 * Plugin capabilities are defined here.
 *
 * @package     local_shopping_cart
 * @category    access
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
        'local/shopping_cart:cashier' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
        'local/shopping_cart:cashtransfer' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
        'local/shopping_cart:cashiermanualrebook' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                ],
        ],
        'local/shopping_cart:history' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'user' => CAP_ALLOW,
                        'student' => CAP_ALLOW,
                ],
        ],
        'local/shopping_cart:canbuy' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                        'editingteacher' => CAP_ALLOW,
                        'teacher' => CAP_ALLOW,
                        'user' => CAP_ALLOW,
                        'student' => CAP_ALLOW,
                ],
        ],
        'local/shopping_cart:changepaymentaccount' => [
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => [
                        'manager' => CAP_ALLOW,
                ],
        ],
];
