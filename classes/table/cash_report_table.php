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

namespace local_shopping_cart\table;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../../lib.php');
require_once($CFG->libdir . '/tablelib.php');

use dml_exception;
use local_wunderbyte_table\wunderbyte_table;

/**
 * Report table to show the cash report.
 *
 * @package     local_shopping_cart
 * @copyright   2022 Wunderbyte GmbH <info@wunderbyte.at>
 * @author      Bernhard Fischer
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cash_report_table extends wunderbyte_table {
    /**
     * This function is called for each data row to allow processing of the
     * 'price' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered price.
     * @throws dml_exception
     */
    public function col_price(object $values): string {
        $commaseparator = current_language() == 'de' ? ',' : '.';
        return number_format((float)$values->price, 2, $commaseparator, '');
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'timecreated' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_timecreated(object $values): string {
        $rendereddate = '';

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d H:i:s', $values->timecreated);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y H:i:s', $values->timecreated);
        } else {
            $rendereddate = date('Y-m-d H:i:s', $values->timecreated);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'timemodified' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_timemodified(object $values): string {
        $rendereddate = '';

        if (empty($values->timemodified)) {
            $values->timemodified = $values->timecreated;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d H:i:s', $values->timemodified);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y H:i:s', $values->timemodified);
        } else {
            $rendereddate = date('Y-m-d H:i:s', $values->timemodified);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'payment' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string payment method
     * @throws dml_exception
     */
    public function col_payment(object $values): string {
        $paymentstring = '';

        switch ($values->payment) {
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE:
                $paymentstring = get_string('paymentmethodonline', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER:
                $paymentstring = get_string('paymentmethodcashier', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS:
                $paymentstring = get_string('paymentmethodcredits', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH:
                $paymentstring = get_string('paymentmethodcreditspaidbackcash', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER:
                $paymentstring = get_string('paymentmethodcreditspaidbacktransfer', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION:
                $paymentstring = get_string('paymentmethodcreditscorrection', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH:
                $paymentstring = get_string('paymentmethodcashier:cash', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CREDITCARD:
                $paymentstring = get_string('paymentmethodcashier:creditcard', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_DEBITCARD:
                $paymentstring = get_string('paymentmethodcashier:debitcard', 'local_shopping_cart');
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL:
                $paymentstring = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
                break;
        }

        return $paymentstring;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'paymentstatus' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string payment status
     * @throws dml_exception
     */
    public function col_paymentstatus(object $values): string {
        $status = '';

        switch ($values->paymentstatus) {
            case LOCAL_SHOPPING_CART_PAYMENT_PENDING:
                $status = get_string('paymentpending', 'local_shopping_cart');
                $classes = "text-danger";
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_ABORTED:
                $status = get_string('paymentaborted', 'local_shopping_cart');
                $classes = "text-danger";
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_SUCCESS:
                $status = get_string('paymentsuccess', 'local_shopping_cart');
                $classes = "text-success";
                break;
            case LOCAL_SHOPPING_CART_PAYMENT_CANCELED:
                $status = get_string('paymentcanceled', 'local_shopping_cart');
                $classes = "text-danger";
                break;
        }

        if ($this->is_downloading()) {
            return $status;
        }

        return "<div class='$classes'>$status</div>";
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'receipt' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string payment status
     * @throws dml_exception
     */
    public function col_receipt(object $values): string {

        if (
            !in_array(
                $values->payment,
                [
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION,
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH,
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER,
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_REBOOKING_CREDITS_CORRECTION,
                ]
            )
        ) {
            $url = new moodle_url(
                '/local/shopping_cart/receipt.php',
                [
                    'success' => 1,
                    'id' => $values->identifier,
                    'idcol' => 'identifier', // Use the identifier to create the receipt.
                    'userid' => $values->userid,
                    'paymentstatus' => $values->paymentstatus,
                ]
            );
            $labelstring = $values->paymentstatus == LOCAL_SHOPPING_CART_PAYMENT_CANCELED ?
                'cancelconfirmation' :
                'receipt';
            $out = html_writer::tag('a', get_string($labelstring, 'local_shopping_cart'), [
                'href' => $url->out(false),
                'target' => '_blank',
            ]);
        } else {
            /* Special receipt - for example for credits paid back
            (there is no identifier in this case but the id in the ledger table).
            We only create it, if the setting extrareceipts is checked. */
            if (get_config('local_shopping_cart', 'extrareceipts')) {
                $url = new moodle_url(
                    '/local/shopping_cart/receipt.php',
                    [
                        'success' => 1,
                        'id' => $values->id,
                        'idcol' => 'id',
                        'userid' => $values->userid,
                    ]
                );
                $out = html_writer::tag('a', get_string('extrareceipt', 'local_shopping_cart'), [
                    'href' => $url->out(false),
                    'target' => '_blank',
                ]);
            } else {
                // If the setting is off, we return an empty string.
                return '';
            }
        }

        return $out ?? '';
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'serviceperiodstart' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_serviceperiodstart(object $values): string {
        $rendereddate = '';

        if (empty($values->serviceperiodstart)) {
            return $rendereddate;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d', $values->serviceperiodstart);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y', $values->serviceperiodstart);
        } else {
            $rendereddate = date('Y-m-d', $values->serviceperiodstart);
        }

        return $rendereddate;
    }

    /**
     * This function is called for each data row to allow processing of the
     * 'serviceperiodend' value.
     *
     * @param object $values Contains object with all the values of record.
     * @return string Rendered date.
     * @throws dml_exception
     */
    public function col_serviceperiodend(object $values): string {
        $rendereddate = '';

        if (empty($values->serviceperiodstart)) {
            return $rendereddate;
        }

        if ($this->is_downloading()) {
            $rendereddate = date('Y-m-d', $values->serviceperiodend);
        } else if (current_language() === 'de') {
            $rendereddate = date('d.m.Y', $values->serviceperiodend);
        } else {
            $rendereddate = date('Y-m-d', $values->serviceperiodend);
        }

        return $rendereddate;
    }
}
