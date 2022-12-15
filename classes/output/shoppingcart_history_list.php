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

use context_system;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use mod_booking\booking_option;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
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
     * historyitems is the array used for output.
     *
     * @var float
     */
    private $cancelationfee = 0;

    /**
     * historyitems is the array used for output.
     *
     * @var float
     */
    private $credit = 0;

    /**
     * historyitems is the array used for output.
     *
     * @var string
     */
    private $currency = 'EUR';

    /**
     * historyitems is the array used for output.
     *
     * @var bool
     */
    private $canpayback = false;

    /**
     * Constructor.
     * @param int $userid
     * @param int $identifier
     */
    public function __construct(int $userid, int $identifier = 0) {

        // If we provide an identifier, we only get the items from history with this identifier, else, we get all for this user.
        if ($identifier != 0) {
            $items = shopping_cart_history::return_data_via_identifier($identifier, $userid);
        } else {
            $items = shopping_cart_history::get_history_list_for_user($userid);
        }
        $iscachier = false;
        $context = context_system::instance();
        if (has_capability('local/shopping_cart:cashier', $context)) {
            $iscachier = true;
            $this->canpayback = true;
        }

        // We transform the stdClass from DB to array for template.
        foreach ($items as $item) {

            self::add_quota_consumed($item, $userid);
            self::add_round_config($item);

            $item->date = date('Y-m-d', $item->timemodified);
            $item->canceled = $item->paymentstatus == PAYMENT_CANCELED ? true : false;

            // Depending on how is calling this and which status the person has, we display different cancel options.
            if (!$item->canceled) {

                $item->canceluntilstring = date('Y-m-d', $item->canceluntil);

                if (!$iscachier) {
                    if (shopping_cart::allowed_to_cancel($item->id, $item->itemid, $item->area, $item->userid)) {
                        $item->canceluntilalert = get_string('youcancanceluntil', 'local_shopping_cart',
                            $item->canceluntilstring);
                        $item->buttonclass = 'btn-primary';
                    } else {
                        $item->canceluntilalert = get_string('youcannotcancelanymore', 'local_shopping_cart',
                            $item->canceluntilstring);
                        $item->buttonclass = 'disabled hidden';
                    }

                } else {
                    $item->buttonclass = $item->paymentstatus == PAYMENT_CANCELED ? 'btn-danger disabled' : 'btn-primary';
                }
            } else {
                // If the item is already canceled, we can just disable the button.
                $item->buttonclass = 'btn-danger disabled';
            }

            $item->serviceperiodstart = $item->serviceperiodstart ?? 0;
            $item->serviceperiodend = $item->serviceperiodend ?? 0;

            // Localize the payment string.
            switch ($item->payment) {
                case PAYMENT_METHOD_ONLINE:
                    $item->paymentstring = get_string('paymentonline', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CASHIER:
                    $item->paymentstring = get_string('paymentcashier', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CREDITS:
                    $item->paymentstring = get_string('paymentcredits', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CREDITS_PAID_BACK:
                    $item->paymentstring = get_string('paymentmethodcreditspaidback', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CASHIER_CASH:
                    $item->paymentstring = get_string('paymentcashier:cash', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CASHIER_CREDITCARD:
                    $item->paymentstring = get_string('paymentcashier:creditcard', 'local_shopping_cart');
                    break;
                case PAYMENT_METHOD_CASHIER_DEBITCARD:
                    $item->paymentstring = get_string('paymentcashier:debitcard', 'local_shopping_cart');
                    break;
                default:
                    $item->paymentstring = get_string('unknown', 'local_shopping_cart');
                    break;
            }

            $this->historyitems[] = (array)$item;

        }

        if ($cancelationfee = get_config('local_shopping_cart', 'cancelationfee')) {
            if ($cancelationfee >= 0) {

                $this->cancelationfee = $cancelationfee;
            }
        }

        $data = shopping_cart::local_shopping_cart_get_cache_data($userid);
        $this->credit = $data['credit'];
    }


    /**
     * Insert the values from this function to an existing array.
     *
     * @param array $data
     * @return void
     */
    public function insert_list(array &$data) {

        $historyarray = $this->return_list();
        $data['historyitems'] = $historyarray['historyitems'];

        if (isset($historyarray['cancelationfee'])) {
            $data['cancelationfee'] = $historyarray['cancelationfee'];
        }

        if (!empty($historyarray['historyitems'])) {
            $data['has_historyitems'] = true;
        }

        if (isset($historyarray['canpayback'])) {
            $data['canpayback'] = $historyarray['canpayback'];
        }

        if (!empty($historyarray['currency'])) {
            $data['currency'] = $historyarray["currency"];
        }
    }

    /**
     * Return list of items, when we want to use it separately from the renderer.
     *
     * @return array
     */
    public function return_list() {

        $returnarray = ['historyitems' => $this->historyitems];

        if (!empty($this->cancelationfee)) {
            $returnarray['cancelationfee'] = $this->cancelationfee;
        }

        if (!empty($this->historyitemse)) {
            $returnarray['has_historyitems'] = true;
        }

        if (!empty($this->credit)) {
            $returnarray['credit'] = $this->credit;
        }

        if (!empty($this->currency)) {
            $returnarray['currency'] = $this->currency;
        }

        if ($this->canpayback) {
            $returnarray['canpayback'] = true;
        }

        return $returnarray;
    }



    /**
     * Prepare data for use in a template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        return $this->return_list();
    }

    /**
     * Receive quota consumed via callback to component.
     *
     * @param stdClass $item
     * @param integer $userid
     * @return void
     */
    private static function add_quota_consumed(stdClass &$item, int $userid) {

        if (empty($item->componentname) || empty($item->area)) {
            return;
        }
        // We fetch the consumed quota as well.
        $providerclass = shopping_cart::get_service_provider_classname($item->componentname);
        $item->quotaconsumed = component_class_callback($providerclass, 'quota_consumed',
            [
                'area' => $item->area,
                'itemid' => $item->itemid,
                'userid' => $userid,
        ]);
    }

    private static function add_round_config(stdClass &$item) {

        if ($round = get_config('local_shopping_cart', 'rounddiscounts')) {
            $item->round = $round == 1 ? true : false;
        }
    }
}
