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

use local_shopping_cart\shopping_cart_history;
use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * viewtable class to display view.php
 * @package local_shopping_cart
 *
 */
class shoppingcart_history_list implements renderable, templatable {

    /**
     * historyitems is the array used for output.
     *
     * @var array
     */
    private $historyitems = [];

    /**
     * Constructor.
     * @param integer $userid
     * @param integer $identifier
     */
    public function __construct(int $userid, int $identifier = 0) {

        // If we provide an identifier, we only get the items from history with this identifier, else, we get all for this user.
        if ($identifier != 0) {
            $items = shopping_cart_history::return_data_via_identifier($identifier);
        } else {
            $items = shopping_cart_history::get_history_list_for_user($userid);
        }

        // We transform the stdClass from DB to array for template.
        foreach ($items as $item) {

            $item->date = date('Y-m-d', $item->timemodified);
            $item->canceled = $item->paymentstatus == PAYMENT_CANCELED ? true : false;
            $item->buttonclass = $item->paymentstatus == PAYMENT_CANCELED ? 'btn-danger disabled' : 'btn-primary';

            // Localize the payment string.
            switch ($item->payment) {
                case PAYMENT_METHOD_ONLINE:
                    $item->paymentstring = get_string('paymentonline', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CASHIER:
                    $item->paymentstring = get_string('paymentcashier', 'local_shopping_cart');
                    break;
                default:
                    $item->paymentstring = get_string('unknown', 'local_shopping_cart');
                    break;
            }

            $this->historyitems = (array)$item;
        }
    }

    /**
     * Return list of items.
     *
     * @return array
     */
    public function return_list() {

        return $this->historyitems;
    }



    /**
     * Prepare data for use in a template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        return $this->historyitems;
    }
}
