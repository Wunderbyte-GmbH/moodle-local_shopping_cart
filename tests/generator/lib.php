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

use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;

/**
 * Class local_shopping_cart_generator for generation of dummy data
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2023 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_shopping_cart_generator extends testing_module_generator {

    /**
     *
     * @var int keep track of how many booking options have been created.
     */
    protected $paymentgateway = 0;

    /**
     * To be called from data reset code only, do not use in tests.
     *
     * @return void
     */
    public function reset() {
        $this->paymentgateway = 0;

        parent::reset();
    }

    /**
     * Function to create a dummy payment gateway.
     *
     * @param array|stdClass $record
     * @return stdClass the payment gateway object
     */
    public function create_payment_gateway($record = null) {
        global $DB;

        $record = (array) $record;

        if (!isset($record['accountid'])) {
            throw new coding_exception(
                    'accountid must be present in phpunit_util::create_option() $record');
        }

        if (!isset($record['gateway'])) {
            throw new coding_exception(
                    'gateway must be present in phpunit_util::create_option() $record');
        }

        if (!isset($record['enabled'])) {
            throw new coding_exception(
                    'enabled must be present in phpunit_util::create_option() $record');
        }

        if (!isset($record['config'])) {
            throw new coding_exception(
                    'config must be present in phpunit_util::create_option() $record');
        }

        $this->paymentgateway++;

        $record = (object) $record;
        $record->timecreated = time();
        $record->timemodified = time();

        $record->id = $DB->insert_record('payment_gateways', $record);

        return $record;
    }

    /**
     * Function to setup a dummy plugin settings (payment gateway - required).
     *
     * @param array|stdClass $record
     * @return bool status
     */
    public function create_plugin_setup($record = null) {

        $record = (array) $record;
        // Check required params.
        if (!isset($record['accountid'])) {
            throw new coding_exception(
                    'accountid must be present in create_plugin_setup() $record');
        }
        // Set all params.
        $res = true;
        foreach ($record as $label => $value) {
            if (!set_config($label, $value, 'local_shopping_cart')) {
                // Return false if at least 1 param failed to setup.
                $res = false;
            }
        }
        return $res;
    }

    /**
     * Function to create a dummy user credit record.
     *
     * @param array|stdClass $record
     * @return stdClass the booking campaign object
     */
    public function create_user_credit($record = null) {
        global $DB, $USER;

        $record = (object) $record;
        $record->usermodified = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();

        $record->id = $DB->insert_record('local_shopping_cart_credits', $record);

        return $record;
    }

    /**
     * Function to create a dummy user purchas record.
     *
     * @param array|stdClass $record
     * @return array
     */
    public function create_user_purchase($record) {
        // Clean cart.
        shopping_cart::delete_all_items_from_cart($record['userid']);
        // Set user to buy in behalf of.
        shopping_cart::buy_for_user($record['userid']);
        // Get cached data or setup defaults.
        $cartstore = cartstore::instance($record['userid']);
        // Put in a test item with given ID (or default if ID > 4).
        shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $record['testitemid'], -1);
        // Confirm cash payment.
        $res = shopping_cart::confirm_payment($record['userid'], LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH);
        return $res;
    }

    /**
     * Function, to get userid
     * @param string $username
     * @return int
     */
    private function get_user(string $username) {
        global $DB;

        if (!$id = $DB->get_field('user', 'id', ['username' => $username])) {
            throw new Exception('The specified user with username "' . $username . '" does not exist');
        }
        return $id;
    }
}
