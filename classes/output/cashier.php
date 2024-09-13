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
 * Contains class mod_questionnaire\output\indexpage
 *
 * @package    local_shopping_cart
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace local_shopping_cart\output;

use core_user;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart_credits;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * viewtable class to display view.php
 * @package local_shopping_cart
 *
 */
class cashier implements renderable, templatable {

    /**
     * data is the array used for output.
     *
     * @var array
     */
    private $data = [];

    /**
     * Constructor
     * @param int|null $userid
     * @param int|null $usecredit
     */
    public function __construct(?int $userid = null, ?int $usecredit = 0) {

        if (!empty($userid)) {
            $cartstore = cartstore::instance($userid);
            $data = $cartstore->get_data();
            $data['buyforuserid'] = $userid;
            $user = core_user::get_user($userid, 'id, lastname, firstname, email');
            $data['userid'] = $user->id;
            $data['userlastname'] = $user->lastname;
            $data['userfirstname'] = $user->firstname;
            $data['useremail'] = $user->email;

            // The cart items should have the supplementary discount flag, to add the discount button.

            foreach ($data['items'] as $key => $value) {
                $data['items'][$key]['iscashier'] = true;
            }

            // We use the template class, but not the renderer here.
            $historylist = new shoppingcart_history_list($userid);

            $historylist->insert_list($data);
            $data['costcentercredits'] =
                array_values(shopping_cart_credits::get_balance_for_all_costcenters($userid));

            $this->data = $data;
        }
    }

    /**
     * Returns the values as array.
     *
     * @return array
     */
    public function returnaslist() {
        return $this->data;
    }

    /**
     * Prepare data for use in a template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        return $this->data;
    }
}
