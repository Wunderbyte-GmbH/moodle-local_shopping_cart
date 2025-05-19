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
 * This class contains a list of webservice functions related to the Shopping Cart Module by Wunderbyte.
 *
 * @package    local_shopping_cart
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_shopping_cart\external;

use context_system;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\checkout_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External service to check if adding an item to cart is allowed.
 *
 * @package    mod_booking
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @author     Bernhard Fischer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class control_checkout_process extends external_api {
    /**
     * Describes the parameters for bookit.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_TEXT, 'direction', VALUE_DEFAULT, ''),
            'currentstep' => new external_value(PARAM_INT, 'currentstep'),
            'identifier' => new external_value(PARAM_TEXT, 'identifier', VALUE_DEFAULT, ''),
            'changedinput' => new external_value(PARAM_RAW, 'changedinput', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Webservice.
     *
     * @param string $action
     * @param int $currentstep
     * @param string $identifier
     * @param string $changedinput
     * @return array
     */
    public static function execute(
        string $action,
        int $currentstep,
        string $identifier = '',
        string $changedinput = ''
    ): array {
        global $PAGE, $OUTPUT, $USER;
        $PAGE->requires->css('/local/shopping_cart/styles.css');

        require_login();
        $PAGE->set_context(context_system::instance());

        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'currentstep' => $currentstep,
            'identifier' => $identifier,
            'changedinput' => $changedinput,
        ]);
        $cartstore = cartstore::instance((int)$USER->id);
        $data = $cartstore->get_localized_data();

        if (!empty($identifier)) {
            $data['identifier'] = $identifier;
        }

        $checkoutmanager = new checkout_manager(
            $data,
            $params
        );
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();

        $reloadbody = $changedinput ? false : true;
        $managerdata = null;
        if (!$reloadbody) {
            $managerdata = $checkoutmanager->check_preprocess($changedinput);
        }
        $checkoutmanagerdata = $checkoutmanager->render_overview();
        $jsfooter = $PAGE->requires->get_end_code();

        $data = array_merge($data, $checkoutmanagerdata);
        return [
            'data' => json_encode($data),
            'jsscript' => $jsfooter,
            'reloadbody' => $reloadbody,
            'managerdata' => json_encode($managerdata),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'data' => new external_value(PARAM_RAW, 'item name'),
            'jsscript' => new external_value(PARAM_RAW, 'jsscript'),
            'reloadbody' => new external_value(PARAM_BOOL, 'reloadbody'),
            'managerdata' => new external_value(PARAM_RAW, 'manager data validation'),
        ]);
    }
}
