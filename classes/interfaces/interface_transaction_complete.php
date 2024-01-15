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
 * Interface for transaction_complete class used in several payment gateways.
 *
 * @package    local_shopping_cart
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     Bernhard Fischer-Sengseis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\interfaces;

use external_function_parameters;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Interface for transaction_complete class used in several payment gateways.
 *
 * @package    local_shopping_cart
 * @copyright  2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     Bernhard Fischer-Sengseis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface interface_transaction_complete {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters;

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea payment area
     * @param int $itemid An internal identifier that is used by the component
     * @param string $tid unique transaction id
     * @param string $token
     * @param string $customer
     * @param bool $ischeckstatus
     * @param string $resourcepath
     * @param int $userid
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid, string $tid, string $token = '0',
     string $customer = '0', bool $ischeckstatus = false, string $resourcepath = '', int $userid = 0): array;

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns(): external_function_parameters;
}
