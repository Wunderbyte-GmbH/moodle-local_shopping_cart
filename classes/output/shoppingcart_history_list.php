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
use local_shopping_cart\local\rebookings;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use local_shopping_cart\shopping_cart_history;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use Throwable;

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
     * Userid
     *
     * @var int
     */
    private $userid = 0;

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
     * Costcenter credits.
     *
     * @var array
     */
    private $costcentercredits = [];

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
     * Bool fromledger.
     * @var bool
     */
    private $fromledger = false;

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
     * @param bool $fromledger
     */
    public function __construct(int $userid, int $identifier = 0, $fromledger = false) {
        global $DB, $PAGE;

        $this->userid = $userid;
        $this->fromledger = $fromledger;

        // We need to have the context set for the format_string below.
        try {
            if (!$context = $page->context ?? null) {
                if (empty($context)) {
                    $PAGE->set_context(context_system::instance());
                }
            }
        } catch (Throwable $e) {
            $PAGE->set_context(context_system::instance());
        }

        // Get currency from config.
        $this->currency = get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR';

        // This might be called from users that are not logged in. we ignore it.
        if (empty($userid)) {
            return;
        }

        // If we provide an identifier, we only get the items from history with this identifier, else, we get all for this user.
        if ($identifier != 0) {
            if (!$fromledger) {
                $items = shopping_cart_history::return_data_via_identifier($identifier);
            } else {
                $items = shopping_cart_history::return_data_from_ledger_via_identifier($identifier);
            }

            // Now we verify that the user is really the correct one.
            $item = reset($items);
            if (!has_capability('local/shopping_cart:cashier', context_system::instance())) {
                $this->userid = $item->userid ?? 0;
            }
        } else {
            $items = shopping_cart_history::get_history_list_for_user($userid);

            $ledgeritems = shopping_cart_history::return_extra_lines_from_ledger($userid);

            if (get_config('local_shopping_cart', 'showextrareceiptstousers')) {
                $items = array_merge($ledgeritems, $items);

                usort($items, function ($a, $b) {
                    return $b->timemodified <=> $a->timemodified;
                });
            }
        }
        $iscashier = false;
        $context = context_system::instance();
        if (has_capability('local/shopping_cart:cashier', $context)) {
            $iscashier = true;
            $this->canpayback = true;
        }

        $this->taxesenabled = get_config('local_shopping_cart', 'enabletax') == 1;

        $now = time();

        // We transform the stdClass from DB to array for template.
        foreach ($items as $item) {
            // We might have an item from ledger.
            if (
                get_config('local_shopping_cart', 'showextrareceiptstousers')
                && empty($item->uniqueid)
                && in_array($item->payment, [
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER,
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH,
                    LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION,
                ])
            ) {
                // Receipt URL for the item.
                $urloptions = [
                    'success' => 1,
                    'idcol' => 'id',
                    'id' => $item->id,
                    'userid' => $item->userid,
                ];
                $item->receipturl = new moodle_url("/local/shopping_cart/receipt.php", $urloptions);

                // We want to show the credits at the place of the price.
                $item->price = $item->credits;
                $item->taxesenabled = false;
                $item->date = date('Y-m-d', $item->timemodified);
                $item->buttonclass = ' hidden ';
                $this->historyitems[] = (array)$item;
                continue;
            }

            // Receipt URL for the item.
            if (!empty($item->identifier) && !empty($item->userid)) {
                $item->receipturl = new moodle_url("/local/shopping_cart/receipt.php", [
                    'id' => $item->identifier,
                    'userid' => $item->userid,
                ]);
            } else {
                // Else we have no receipturl.
                $item->receipturl = null;
            }

            // Improvement: For installments, we need to aggregate all receipts (GH-92).
            $schistoryid = $DB->get_field_sql(
                "SELECT schistoryid
                   FROM {local_shopping_cart_ledger}
                  WHERE identifier = :identifier
                    AND schistoryid IS NOT NULL
                  LIMIT 1",
                ['identifier' => $item->identifier]
            );
            if (!empty($schistoryid)) {
                $additionalidentifiers = $DB->get_fieldset_sql(
                    "SELECT DISTINCT identifier
                                FROM {local_shopping_cart_ledger}
                               WHERE schistoryid = :schistoryid
                                 AND identifier <> :identifier
                                 AND identifier IS NOT NULL
                                 AND paymentstatus = :paymentstatus",
                    [
                        'schistoryid' => $schistoryid,
                        'identifier' => $item->identifier,
                        'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
                    ]
                );
                if (!empty($additionalidentifiers)) {
                    $item->hasinstallments = true;
                    $item->installmentreceipturls[] = [
                        'identifier' => $item->identifier,
                        'installmentreceipturl' => $item->receipturl,
                    ];
                    foreach ($additionalidentifiers as $additionalidentifier) {
                        $item->installmentreceipturls[] = [
                            'identifier' => $additionalidentifier,
                            'installmentreceipturl' => new moodle_url("/local/shopping_cart/receipt.php", [
                                'id' => $additionalidentifier,
                                'userid' => $item->userid,
                            ]),
                        ];
                    }
                }
                if ($item->paymentstatus == LOCAL_SHOPPING_CART_PAYMENT_CANCELED) {
                    // If it was canceled, we might have an identifier for the canceled item.
                    $canceledidentifier = $DB->get_field_sql(
                        "SELECT identifier
                        FROM {local_shopping_cart_ledger}
                        WHERE schistoryid = :schistoryid
                            AND identifier <> :identifier
                            AND identifier IS NOT NULL
                            AND paymentstatus = :paymentstatus
                        LIMIT 1",
                        [
                            'schistoryid' => $schistoryid,
                            'identifier' => $item->identifier,
                            'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
                        ]
                    );
                } else {
                    $canceledidentifier = null;
                }

                if (!empty($canceledidentifier)) {
                    $item->cancelconfirmation = [
                        'identifier' => $canceledidentifier,
                        'cancelconfirmationurl' => new moodle_url(
                            '/local/shopping_cart/receipt.php',
                            [
                                'success' => 1,
                                'id' => $canceledidentifier,
                                'idcol' => 'identifier', // Use the identifier to create the receipt.
                                'userid' => $item->userid,
                                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_CANCELED,
                            ]
                        ),
                    ];
                }
            }

            shopping_cart::add_quota_consumed_to_item($item, $userid);
            self::add_round_config($item);
            if ($this->taxesenabled) {
                self::add_tax_info($item);
            }

            $item->date = date('Y-m-d', $item->timemodified);
            $item->canceled = $item->paymentstatus == LOCAL_SHOPPING_CART_PAYMENT_CANCELED ? true : false;

            // Depending on how is calling this and which status the person has, we display different cancel options.
            if (!$item->canceled) {
                $item->canceluntilstring = date('Y-m-d', $item->canceluntil);

                if (!$iscashier) {
                    // The allowed_to_cancel function only checks if cancelling is disabled, it does not check canceluntil!
                    if (shopping_cart::allowed_to_cancel($item->id, $item->itemid, $item->area ?? "", $item->userid)) {
                        if (empty($item->canceluntil)) {
                            // There is no canceluntil, so we can cancel.
                            $item->buttonclass = 'btn-primary';
                        } else if ($now <= $item->canceluntil) {
                            // There is a canceluntil, but it has not yet passed.
                            $item->canceluntilalert = get_string(
                                'youcancanceluntil',
                                'local_shopping_cart',
                                $item->canceluntilstring
                            );
                            $item->buttonclass = 'btn-primary';
                        } else {
                            $item->canceluntilalert = get_string('youcannotcancelanymore', 'local_shopping_cart');
                            $item->buttonclass = 'disabled hidden';
                        }
                    } else {
                        $item->canceluntilalert = get_string('youcannotcancelanymore', 'local_shopping_cart');
                        $item->buttonclass = 'disabled hidden';
                    }
                } else {
                    $item->buttonclass = $item->paymentstatus == LOCAL_SHOPPING_CART_PAYMENT_CANCELED ?
                        'btn-danger disabled' : 'btn-primary';
                }
            } else {
                // If the item is already canceled, we can just disable the button.
                $item->buttonclass = 'btn-danger disabled';
            }

            $item->serviceperiodstart = $item->serviceperiodstart ?? 0;
            $item->serviceperiodend = $item->serviceperiodend ?? 0;

            // Cost center.
            $item->costcenter = $item->costcenter ?? '';

            // Localize the payment string.
            switch ($item->payment) {
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE:
                    $item->paymentstring = get_string('paymentonline', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER:
                    $item->paymentstring = get_string('paymentcashier', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS:
                    $item->paymentstring = get_string('paymentcredits', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_CASH:
                    $item->paymentstring = get_string('paymentmethodcreditspaidbackcash', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_PAID_BACK_BY_TRANSFER:
                    $item->paymentstring = get_string('paymentmethodcreditspaidbacktransfer', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CREDITS_CORRECTION:
                    $item->paymentstring = get_string('paymentmethodcreditscorrection', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CASH:
                    $item->paymentstring = get_string('paymentcashier:cash', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_CREDITCARD:
                    $item->paymentstring = get_string('paymentcashier:creditcard', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_DEBITCARD:
                    $item->paymentstring = get_string('paymentcashier:debitcard', 'local_shopping_cart');
                    break;
                case LOCAL_SHOPPING_CART_PAYMENT_METHOD_CASHIER_MANUAL:
                    $item->paymentstring = get_string('paymentmethodcashier:manual', 'local_shopping_cart');
                    break;
                default:
                    $item->paymentstring = get_string('unknown', 'local_shopping_cart');
                    break;
            }

            if (get_config('local_shopping_cart', 'allowrebooking')) {
                // Get the marked information.
                $item->rebooking = shopping_cart_history::is_marked_for_rebooking($item->id, (int) $userid);

                if (rebookings::allow_rebooking($item, $userid)) {
                    $item->showrebooking = true; // If it is shown at all.
                } else {
                    $item->showrebooking = null; // So we can hide it in mustache template.
                }
            }

            $item->itemname = format_string($item->itemname);

            $this->historyitems[] = (array)$item;
        }

        if ($cancelationfee = get_config('local_shopping_cart', 'cancelationfee')) {
            if ($cancelationfee >= 0) {
                $this->cancelationfee = $cancelationfee;
            }
        }

        $this->costcentercredits = shopping_cart_credits::get_balance_for_all_costcenters($userid);
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
        $data['taxesenabled'] = $historyarray['taxesenabled'];

        if ($this->fromledger) {
            $initialtotal = 0;
            $discount = 0;
            $price = 0;
            foreach ($data['historyitems'] as $key => $item) {
                $initialtotal += $item['price'] > 0 ? $item['price'] : 0;
                $discount -= $item['price'] < 0 ? $item['price'] : 0;
                $price += $item['price'];

                if ($item['price'] == 0) {
                    $data['historyitems'][$key]['price_net'] = "0.00";
                }
                $receipturl = $item['receipturl'];
            }

            $data['initialtotal'] = $initialtotal;
            $data['discount'] = $discount;
            $data['price'] = $price;
            $data['receipturl'] = $receipturl;
        }

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

        usort($data['historyitems'], function ($a, $b) {
            return $b['price'] <=> $a['price'];
        });
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
                $this->historyitems[$key]['price_gross'] = number_format(round((float) ($item['price_gross'] ?? 0), 2), 2, '.', '');
                $this->historyitems[$key]['price_net'] = number_format(round((float) ($item['price_net'] ?? 0), 2), 2, '.', '');
            }
            if (!empty($item['receipturl'])) {
                $this->historyitems[$key]['receipturl'] = $item['receipturl']->out(false);
            } else {
                $this->historyitems[$key]['receipturl'] = null;
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
            $returnarray['credit'] = number_format(round((float) $this->credit ?? 0, 2), 2, '.', '');
        }

        if (!empty($this->costcentercredits)) {
            foreach ($this->costcentercredits as $key => $value) {
                $this->costcentercredits[$key]['balance'] = number_format(round((float) $value['balance'] ?? 0, 2), 2, '.', '');
            }
            $returnarray['costcentercredits'] = array_values($this->costcentercredits);
        }

        if (!empty($this->currency)) {
            $returnarray['currency'] = $this->currency;
        }

        if ($this->canpayback) {
            $returnarray['canpayback'] = true;
        }

        // The "taxesenabled" array key must exist and contains value.
        $returnarray['taxesenabled'] = $this->taxesenabled ?? false;

        if ($this->userid) {
            $returnarray['userid'] = $this->userid;
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
     * Add round config
     *
     * @param stdClass $item
     *
     * @return [type]
     */
    public static function add_round_config(stdClass &$item) {

        if ($round = get_config('local_shopping_cart', 'rounddiscounts')) {
            $item->round = $round == 1 ? true : false;
        }
    }

    /**
     * Return userid.
     *
     * @return int
     *
     */
    public function return_userid() {
        return $this->userid;
    }

    /**
     * Add tax info
     *
     * @param stdClass $item
     *
     * @return [type]
     */
    private static function add_tax_info(stdClass &$item) {
        // Price is always gross.
        if (isset($item->tax)) {
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
