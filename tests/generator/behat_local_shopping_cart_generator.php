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
 * Behat data generator for mod_assign.
 *
 * @package   local_shopping_cart
 * @category  test
 * @copyright 2023 Andrii Semenets
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_shopping_cart_generator extends behat_generator_base {

    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'payment gateways' => [
                'singular' => 'payment gateway',
                'datagenerator' => 'payment_gateway',
                'required' => ['account', 'gateway', 'enabled', 'config'],
                'switchids' => ['account' => 'accountid'],
            ],
            'plugin setup' => [
                'datagenerator' => 'plugin_setup',
                'required' => ['account'],
                'switchids' => ['account' => 'accountid'],
            ],
            'user credits' => [
                'singular' => 'user credit',
                'datagenerator' => 'user_credit',
                'required' => ['user', 'credits', 'currency', 'balance'],
                'switchids' => ['user' => 'userid'],
            ],
            'user purchases' => [
                'singular' => 'user purchase',
                'datagenerator' => 'user_purchase',
                'required' => ['user', 'testitemid'],
                'switchids' => ['user' => 'userid'],
            ],
            'user addresses' => [
                'singular' => 'user address',
                'datagenerator' => 'user_address',
                'required' => ['user', 'name', 'state', 'address', 'city', 'zip'],
                'switchids' => ['user' => 'userid'],
            ],
        ];
    }

    /**
     * Get the payment account ID using an activity idnumber.
     *
     * @param string $accountname
     * @return int The payment account id
     */
    protected function get_account_id(string $accountname): int {
        global $DB;

        if (!$id = $DB->get_field('payment_accounts', 'id', ['name' => $accountname])) {
            throw new Exception('The specified payment account with name "' . $accountname . '" does not exist');
        }
        return $id;
    }
}
