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
 * Class local_shopping_cart_generator for generation of dummy data
 *
 * @package local_shopping_cart
 * @category test
 * @copyright 2023 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_shopping_cart\shopping_cart;

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
     * @param int $testitemid
     * @return array
     */
    public function create_user_purchase($record) {
        global $USER;

        // Clean cart.
        shopping_cart::delete_all_items_from_cart($record['userid']);
        // Set user to buy in behalf of.
        shopping_cart::buy_for_user($record['userid']);
        shopping_cart::local_shopping_cart_get_cache_data($record['userid']);
        // Put in 2 items.
        shopping_cart::add_item_to_cart('local_shopping_cart', 'behattest', 1, -1);
        shopping_cart::add_item_to_cart('local_shopping_cart', 'behattest', 2, -1);
        $res = shopping_cart::confirm_payment($record['userid'], $USER->id);
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
