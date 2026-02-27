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
use dml_exception;
use local_shopping_cart\context_helper;
use local_shopping_cart\local\rebookings;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_credits;
use local_shopping_cart\shopping_cart_history;
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
     * Bool showserviceperiod.
     * @var bool
     */
    private $showserviceperiod = false;

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
        context_helper::fix_page_context($PAGE);

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

        if (get_config('local_shopping_cart', 'schistoryshowserviceperiod')) {
            $this->showserviceperiod = true;
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
                $item->timecreatedrendered = !empty($item->timecreated) ?
                    userdate($item->timecreated, get_string('strftimedatetime', 'langconfig')) : null;
                $item->timemodifiedrendered = !empty($item->timemodified) ?
                    userdate($item->timemodified, get_string('strftimedatetime', 'langconfig')) : null;
                $item->serviceperiodstartrendered = !empty($item->serviceperiodstart) ?
                    userdate($item->serviceperiodstart, get_string('strftimedatetime', 'langconfig')) : null;
                $item->serviceperiodendrendered = !empty($item->serviceperiodend) ?
                    userdate($item->serviceperiodend, get_string('strftimedatetime', 'langconfig')) : null;
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
                                 AND paymentstatus = :paymentstatus
                            ORDER BY identifier DESC",
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
            // Format the Items for output at the last moment.
            $item->timecreatedrendered = !empty($item->timecreated) ?
                userdate($item->timecreated, get_string('strftimedatetime', 'langconfig')) : null;
            $item->timemodifiedrendered = !empty($item->timemodified) ?
                userdate($item->timemodified, get_string('strftimedatetime', 'langconfig')) : null;
            $item->serviceperiodstartrendered = !empty($item->serviceperiodstart) ?
                userdate($item->serviceperiodstart, get_string('strftimedatetime', 'langconfig')) : null;
            $item->serviceperiodendrendered = !empty($item->serviceperiodend) ?
                userdate($item->serviceperiodend, get_string('strftimedatetime', 'langconfig')) : null;

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

        if (isset($historyarray['showserviceperiod'])) {
            $data['showserviceperiod'] = $historyarray['showserviceperiod'];
        }

        if (!empty($historyarray['currency'])) {
            $data['currency'] = $historyarray["currency"];
        }

        usort($data['historyitems'], function ($a, $b) {
            return $b['price'] <=> $a['price'];
        });
    }

    /**
     * Return gateway name.
     *
     * @param array $item historyitem.
     * @return string
     */
    private function return_gatewayname(array $item): string {
        if (isset($item['gateway'])) {
            try {
                return get_string('pluginname', 'paygw_' . $item['gateway']);
            } catch (dml_exception $e) {
                return $item['gateway'];
            }
        } else {
            return '';
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
                $this->historyitems[$key]['price_gross'] = number_format(round((float) ($item['price_gross'] ?? 0), 2), 2, '.', '');
                $this->historyitems[$key]['price_net'] = number_format(round((float) ($item['price_net'] ?? 0), 2), 2, '.', '');
            }
            if (!empty($item['receipturl'])) {
                $this->historyitems[$key]['receipturl'] = $item['receipturl']->out(false);
            } else {
                $this->historyitems[$key]['receipturl'] = null;
            }
            $this->historyitems[$key]['gateway'] = $this->return_gatewayname($item);
        }

        $returnarray = ['historyitems' => $this->historyitems];

        if (!empty($this->cancelationfee)) {
            $returnarray['cancelationfee'] = $this->cancelationfee;
        }

        if (!empty($this->historyitems)) {
            $returnarray['has_historyitems'] = true;
        }

        if (!empty($this->credit)) {
            $returnarray['credit'] = format_float(round((float)$this->credit ?? 0, 2), 2);
        }

        if (!empty($this->costcentercredits)) {
            foreach ($this->costcentercredits as $key => $value) {
                $this->costcentercredits[$key]['balance'] = format_float(round((float)$value['balance'] ?? 0, 2), 2);
            }
            $returnarray['costcentercredits'] = array_values($this->costcentercredits);
        }

        if (!empty($this->currency)) {
            $returnarray['currency'] = $this->currency;
        }

        if ($this->canpayback) {
            $returnarray['canpayback'] = true;
        }

        if ($this->showserviceperiod) {
            $returnarray['showserviceperiod'] = true;
        }

        // The "taxesenabled" array key must exist and contains value.
        $returnarray['taxesenabled'] = $this->taxesenabled ?? false;

        if ($this->userid) {
            $returnarray['userid'] = $this->userid;
        }

        return $returnarray;
    }

    /**
     * Helper function to organize history items into collapsible sections.
     * Depending on the according settings.
     *
     * @param array $returnarray reference to the returnarray
     * @return void
     */
    public static function organize_returnarray_into_collapsible_sections(array &$returnarray): void {
        global $DB;
        // If no history items exist, we have nothing to do here.
        if (empty($returnarray['historyitems'])) {
            return;
        }
        /* First, we want to add sorting dates to each item.
        If the item is somehow connected to a Booking option of the mod_booking plugin,
        then we want to use the start of this booking option as sorting date.
        In all other cases, we just use timemodified (or timecreated if timemodified is not set). */
        foreach ($returnarray['historyitems'] as $key => $value) {
            // Default behavior.
            if (
                empty($returnarray['historyitems'][$key]['timemodified'])
                && !empty($returnarray['historyitems'][$key]['timecreated'])
            ) {
                $returnarray['historyitems'][$key]['sortingdate'] = (int) $returnarray['historyitems'][$key]['timecreated'];
            } else if (!empty($returnarray['historyitems'][$key]['timemodified'])) {
                $returnarray['historyitems'][$key]['sortingdate'] = (int) $returnarray['historyitems'][$key]['timemodified'];
            } else {
                // Fallback. Should never happen.
                $returnarray['historyitems'][$key]['sortingdate'] = time();
            }

            // Check if the Booking plugin is installed.
            if (
                class_exists('mod_booking\booking')
                && get_config('local_shopping_cart', 'schistorysectionssortbybookingcoursestarttime')
            ) {
                // Check if the history item corresponds to a booking option.
                if (
                    $returnarray['historyitems'][$key]['componentname'] == 'mod_booking'
                    && $returnarray['historyitems'][$key]['area'] == 'option'
                ) {
                    $settings = \mod_booking\singleton_service::get_instance_of_booking_option_settings(
                        $returnarray['historyitems'][$key]['itemid']
                    );
                    if (!empty($settings->coursestarttime)) {
                        $returnarray['historyitems'][$key]['sortingdate'] = (int)$settings->coursestarttime;
                        $returnarray['historyitems'][$key]['bocoursestarttime'] =
                            userdate((int)$settings->coursestarttime, get_string('strftimedatetime', 'langconfig'));
                    }
                }
            }
        }

        self::structure_historyitems_into_date_intervals($returnarray);
    }

    /**
     * Helper function to generate an array of interval strings.
     * @param array $returnarray reference to the returnarray
     */
    public static function structure_historyitems_into_date_intervals(
        array &$returnarray
    ): void {
        // Start one year earlier and end one year later, just to make sure, we don't lose anything.
        $lowestyear = (int) date('Y', min(array_column($returnarray['historyitems'], 'sortingdate'))) - 1;
        $highestyear = (int) date('Y', max(array_column($returnarray['historyitems'], 'sortingdate'))) + 1;

        $startingmonth = (int) get_config('local_shopping_cart', 'schistorysectionsstartingmonth') ?? 1;
        $interval = (int) get_config('local_shopping_cart', 'schistorysectionsinterval') ?? 1;

        $historyitems = $returnarray['historyitems'];
        $returnarray['structuredhistoryitems'] = [];

        $monthsperinterval = 12 / $interval;

        for ($year = $lowestyear; $year <= $highestyear; $year++) {
            $currentyear = $year;
            $currentmonth = $startingmonth;

            for ($i = 0; $i < $interval; $i++) {
                $endmonth = $currentmonth + $monthsperinterval - 1;
                $endyear = $currentyear;

                if ($endmonth > 12) {
                    $endmonth -= 12;
                    $endyear += 1;
                }

                if ($interval == 12) {
                    $pattern = "%02d/%d"; // When using months, we do not want them twice.
                } else {
                    $pattern = "%02d/%d - %02d/%d";
                }
                $dateintervalsection = sprintf($pattern, $currentmonth, $currentyear, $endmonth, $endyear);

                $intervalstarttime = strtotime("{$currentyear}-{$currentmonth}-01 00:00:00");
                $monthafterendmonth = $endmonth + 1;
                if ($monthafterendmonth > 12) {
                    $monthafterendmonth -= 12;
                    $endyear += 1;
                }
                $intervalendtime = strtotime("{$endyear}-{$monthafterendmonth}-01 00:00:00");
                $intervalendtime -= 1; // So we'll get the last second (23:59:59) of the month before.

                $sectionhistoryitems = array_values(array_filter(
                    $historyitems,
                    fn($item) =>
                        $item['sortingdate'] >= $intervalstarttime
                        && $item['sortingdate'] <= $intervalendtime
                ));
                usort($sectionhistoryitems, function ($a, $b) {
                    return (int)$b['timecreated'] <=> (int)$a['timecreated'];
                });

                // Now we add all matching items to the section.
                $returnarray['structuredhistoryitems'][] = [
                    // The dateintervalkey will be used in the mustache template for the accordion to work.
                    'dateintervalkey' => preg_replace('/\_+/', '_', preg_replace('/\/|-|\s/', '_', $dateintervalsection)),
                    'dateinterval' => $dateintervalsection,
                    'sectionhistoryitems' => $sectionhistoryitems,
                ];

                $currentmonth += $monthsperinterval;
                if ($currentmonth > 12) {
                    $currentmonth -= 12;
                    $currentyear += 1;
                }
            }
        }
        // Clean up: Remove all empty sections.
        foreach ($returnarray['structuredhistoryitems'] as $key => $value) {
            if (empty($returnarray['structuredhistoryitems'][$key]['sectionhistoryitems'])) {
                unset($returnarray['structuredhistoryitems'][$key]);
            }
        }
        // Removing the keys is necessary, so mustache will work!
        if (!empty($returnarray['structuredhistoryitems'])) {
            $returnarray['structuredhistoryitems'] = array_values($returnarray['structuredhistoryitems']);
        }
        // Reverse the order, so newest sections come first.
        $returnarray['structuredhistoryitems'] = array_reverse($returnarray['structuredhistoryitems']);

        // Now, let's mark the first one, so we can always expand it.
        // We explicitly set firstsection=false on all other items to prevent Mustache context
        // inheritance: the accordion is wrapped in {{#structuredhistoryitems.0}}, which puts
        // the first item (firstsection=true) on the context stack. Without an explicit false,
        // Mustache falls back to that parent context for every iteration, making all items show.
        foreach ($returnarray['structuredhistoryitems'] as $key => $value) {
            $returnarray['structuredhistoryitems'][$key]['firstsection'] = ($key === 0);
        }
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
     * Add round config.
     * Round and roundrefundamount properties of $item
     * are set to int values.
     *
     * @param stdClass $item
     *
     * @return void
     */
    public static function add_round_config(stdClass &$item): void {
        $item->round = (int) get_config('local_shopping_cart', 'rounddiscounts');
        $item->roundrefundamount = (int) get_config('local_shopping_cart', 'roundrefundamount');
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
     * @return void
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
