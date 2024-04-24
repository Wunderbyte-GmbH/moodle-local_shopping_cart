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
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use local_shopping_cart\table\installments_table;
use moodle_url;
use renderable;
use renderer_base;
use templatable;
use context_system;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\entities\cartitem;

/**
 * viewtable class to display view.php
 * @package local_shopping_cart
 *
 */
class installments implements renderable, templatable {

    /**
     * Data is the array used for output.
     *
     * @var array
     */
    private $items = [];

    /**
     * Installmentstable.
     *
     * @var string
     */
    private $installmentstable = '';

    /**
     * Constructor
     * @param int $userid
     */
    public function __construct(int $userid = 0) {

        global $DB, $OUTPUT;

        $context = context_system::instance();

        // Here we create the data object.
        if (empty($userid) && has_capability('local/shopping_cart:cashier', $context)) {

            // In this case, we create a wb table for the cashier to overview payments.

            $table = new installments_table('installmentstable');

             // Columns.
            $columns = [
                'id' => get_string('id', 'local_shopping_cart'),
                'identifier' => get_string('identifier', 'local_shopping_cart'),
                'timecreated' => get_string('timecreated', 'local_shopping_cart'),
                'timemodified' => get_string('timemodified', 'local_shopping_cart'),
                'price' => get_string('paid', 'local_shopping_cart'),
                'discount' => get_string('discount', 'local_shopping_cart'),
                'credits' => get_string('credit', 'local_shopping_cart'),
                'fee' => get_string('cancelationfee', 'local_shopping_cart'),
                'currency' => get_string('currency', 'local_shopping_cart'),
                'lastname' => get_string('lastname', 'local_shopping_cart'),
                'firstname' => get_string('firstname', 'local_shopping_cart'),
                'email' => get_string('email', 'local_shopping_cart'),
                'itemid' => get_string('itemid', 'local_shopping_cart'),
                'itemname' => get_string('itemname', 'local_shopping_cart'),
                'payment' => get_string('payment', 'local_shopping_cart'),
                'paymentstatus' => get_string('paymentstatus', 'local_shopping_cart'),
                'gateway' => get_string('gateway', 'local_shopping_cart'),
                'orderid' => get_string('orderid', 'local_shopping_cart'),
                'annotation' => get_string('annotation', 'local_shopping_cart'),
                'cashier' => get_string('cashier', 'local_shopping_cart'),
            ];

            $table->define_columns(array_keys($columns));
            $table->define_headers(array_values($columns));

            $fields = '*';
            $from = "{local_shopping_cart_history}";
            $where = "installments IS NOT NULL";
            $params = [];

            $table->set_sql($fields, $from, $where, $params);

            $html = $table->outhtml(20, true);

            $this->installmentstable = $html;

        } else {
            // This is the user view.
            $sql = "SELECT *
                    FROM {local_shopping_cart_history}
                    WHERE installments > 0
                    AND paymentstatus = :paymentstatus";
            $params = [
                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
            ];
            $records = $DB->get_records_sql($sql, $params);

            foreach ($records as $record) {

                // First, we add the down payment.
                $item = new cartitem(
                    $record->itemid,
                    $record->itemname,
                    $record->price,
                    $record->currency,
                    $record->componentname,
                    $record->area,
                    "down payment",
                    '',
                    $record->canceluntil,
                    $record->serviceperiodstart,
                    $record->serviceperiodend,
                    $record->taxcategory,
                    1,
                    $record->costcenter
                );

                $jsonobject = json_decode($record->json);
                $payments = $jsonobject->installments->payments;

                $this->items[] = $item->as_array();

                foreach ($payments as $payment) {

                    // If this is already paid, we don't show the button.
                    if (!empty($payment->paid)) {
                        continue;
                    }

                    $item = new cartitem(
                        $record->id, // We use the historyid.
                        $record->itemname,
                        $payment->price,
                        $payment->currency,
                        'local_shopping_cart',
                        'installments-' . $payment->id,
                        'installment payment, ' . $payment->date,
                        '',
                        null,
                        null,
                        null,
                        null,
                        1,
                        null,
                        $payment->timestamp
                    );

                    $item = $item->as_array();

                    $button = new button($item);
                    $html = $OUTPUT->render_from_template(
                        'local_shopping_cart/addtocartdb',
                        $button->export_for_template($OUTPUT)
                    );

                    $item['button'] = $html;
                    $this->items[] = $item;
                }
            }
        }
    }

    /**
     * Returns the values as array.
     *
     * @return array
     */
    public function returnaslist() {
        return [
            'items' => $this->items,
        ];
    }

    /**
     * Prepare data for use in a template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        return $this->returnaslist();
    }
}
