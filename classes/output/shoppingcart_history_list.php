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
     * Historyitems is the array used for output.
     *
     * @var array
     */
    private $historyitems = [];

    /**
     * Cancelation fee..
     *
     * @var float
     */
    private $cancelationfee = 0;

    /**
     * Credit.
     *
     * @var float
     */
    private $credit = 0;

    /**
     * Currency.
     * @var string
     */
    private $currency = 'EUR';

    /**
     * Bool canpayback.
     * @var bool
     */
    private $canpayback = false;

    /**
     * if taxes are enabled for this module
     *
     * @var bool
     */
    private $taxesenabled;

    /**
     * Constructor.
     *
     * @param int $userid
     * @param int $identifier
     */
    public function __construct(int $userid, int $identifier = 0) {

        // Get currency from config.
        $this->currency = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';

        // This might be called from users that are not logged in. we ignore it.
        if (empty($userid)) {
            return;
        }

        // If we provide an identifier, we only get the items from history with this identifier, else, we get all for this user.
        if ($identifier != 0) {
            $items = shopping_cart_history::return_data_via_identifier($identifier);
        } else {
            $items = shopping_cart_history::get_history_list_for_user($userid);
        }
        $iscashier = false;
        $context = context_system::instance();
        if (has_capability('local/shopping_cart:cashier', $context)) {
            $iscashier = true;
            $this->canpayback = true;
        }

        $this->taxesenabled = get_config('local_shopping_cart', 'enabletax') == 1;

        // We transform the stdClass from DB to array for template.
        foreach ($items as $item) {

            self::add_quota_consumed($item, $userid);
            self::add_round_config($item);
            if ($this->taxesenabled) {
                self::add_tax_info($item);
            }

            $item->date = date('Y-m-d', $item->timemodified);
            $item->canceled = $item->paymentstatus == PAYMENT_CANCELED ? true : false;

            // Depending on how is calling this and which status the person has, we display different cancel options.
            if (!$item->canceled) {

                $item->canceluntilstring = date('Y-m-d', $item->canceluntil);

                if (!$iscashier) {
                    if (shopping_cart::allowed_to_cancel($item->id, $item->itemid, $item->area ?: "", $item->userid)) {
                        if (!empty($item->canceluntil)) {
                            $item->canceluntilalert = get_string('youcancanceluntil', 'local_shopping_cart',
                                $item->canceluntilstring);
                        }
                        $item->buttonclass = 'btn-primary';
                    } else {
                        $item->canceluntilalert = get_string('youcannotcancelanymore', 'local_shopping_cart');
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
                case PAYMENT_METHOD_CASHIER_MANUAL:
                    $item->paymentstring = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
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

        // Preprocess all items to force prices to 2 decimal digits always visible.
        foreach ($this->historyitems as $key => $item) {
            $this->historyitems[$key]['price'] = number_format(round((float) $item['price'], 2), 2, '.', '');

            if ($this->taxesenabled) {
                $this->historyitems[$key]['price_gross'] = number_format(round((float) $item['price_gross'] ?? 0, 2), 2, '.', '');
                $this->historyitems[$key]['price_net'] = number_format(round((float) $item['price_net'] ?? 0, 2), 2, '.', '');
            }
        }

        $returnarray = ['historyitems' => $this->historyitems];

        if (!empty($this->cancelationfee)) {
            $returnarray['cancelationfee'] = $this->cancelationfee;
        }

        if (!empty($this->historyitems)) {
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

        if ($this->taxesenabled) {
            $returnarray['taxesenabled'] = true;
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
     * @param int $userid
     * @return [type]
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

    /**
     * Add round config
     *
     * @param stdClass $item
     *
     * @return [type]
     */
    private static function add_round_config(stdClass &$item) {

        if ($round = get_config('local_shopping_cart', 'rounddiscounts')) {
            $item->round = $round == 1 ? true : false;
        }
    }

    /**
     * Add tax info
     *
     * @param stdClass $item
     *
     * @return [type]
     */
    private static function add_tax_info(stdClass &$item) {
        $itemisnet = get_config('local_shopping_cart', 'itempriceisnet');
        if (isset($item->tax) && $itemisnet) {
            $item->price_gross = $item->price + $item->tax;
            $item->price_net = $item->price;
            $item->taxpercentage_visual = round($item->taxpercentage * 100, 2);
        } else if (isset($item->tax) && !$itemisnet) {
            $item->price_gross = $item->price;
            $item->price_net = $item->price - $item->tax;
            $item->taxpercentage_visual = round($item->taxpercentage * 100, 2);
        } else {
            $item->price_gross = $item->price;
            $item->price_net = $item->price;
            $item->taxpercentage_visual = 0;
        }
    }
}
