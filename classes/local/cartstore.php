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
 * The cartstore class handles the in and out of the cache.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use coding_exception;
use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\local\pricemodifier\modifier_info;
use local_shopping_cart\shopping_cart;
use moodle_exception;
use context_system;
use local_shopping_cart\addresses;
use local_shopping_cart\local\pricemodifier\modifiers\checkout;
use local_shopping_cart\shopping_cart_credits;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Class cartstore
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cartstore {

    /** @var array */
    protected static $instance = [];

    /** @var int */
    protected $userid = 0;

    /** @var mixed */
    private $cachedata = null;

    /**
     * Cartstore constructor.
     * @param int $userid
     * @return void
     */
    private function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Singleton provider.
     * @param int $userid
     * @return cartstore
     */
    public static function instance(int $userid) {
        if (empty(self::$instance[$userid])) {
            self::$instance[$userid] = new cartstore($userid);
        }
        return self::$instance[$userid];
    }

    /**
     * Adds an item to the shopping cart cache store.
     * @param cartitem $item
     * @return array
     * @throws coding_exception
     */
    public function add_item(cartitem $item) {

        $data = $this->get_cache();
        $expirationtime = shopping_cart::get_expirationtime();

        $itemdata = $item->as_array();
        $itemdata['expirationtime'] = $expirationtime;
        $itemdata['userid'] = $this->userid;

        $cacheitemkey = $item->itemkey();
        $data['items'][$cacheitemkey] = $itemdata;
        $data['expirationtime'] = $expirationtime;

        $data['costcenter'] = empty($data['costcenter']) ? $item->costcenter : '';

        // When we add the first item, we need to reset credit...
        // ... because we can only use the one from the correct cost center.

        if (
            get_config('local_shopping_cart', 'samecostcenterforcredits')
            && !empty($data['costcenter'])
        ) {
            [$credit, $currency] = shopping_cart_credits::get_balance($this->userid, $data['costcenter']);
            $data['credit'] = $credit;
            $data['remainingcredit'] = $credit;
            $data['currency'] = $currency;
        }

        $this->set_cache($data);

        return $itemdata;
    }

    /**
     * Delete items.
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return void
     * @throws coding_exception
     */
    public function delete_item(
        string $component,
        string $area,
        int $itemid) {

        $data = $this->get_cache();

        if ($data) {
            $cacheitemkey = $component . '-' . $area . '-' . $itemid;
            if (isset($data['items'][$cacheitemkey])) {
                unset($data['items'][$cacheitemkey]);
                unset($data['openinstallments']);

                if (empty($data['items'])) {
                    $data['expirationtime'] = 0;
                    unset($data['paymentaccountid']);
                }
                $this->set_cache($data);
            }
        }
    }

    /**
     *
     * Add discount to item.
     * - First we check if the item is here.
     * - Now we add the discount to the cart.
     * - For any fail, we return success 0.
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @param float $percent
     * @param float $absolute
     * @return array
     */
    public function add_discount_to_item(
        string $component,
        string $area,
        int $itemid,
        float $percent,
        float $absolute): array {

        $context = context_system::instance();
        if (!has_capability('local/shopping_cart:cashier', $context)) {
            throw new moodle_exception('norighttoaccess', 'local_shopping_cart');
        }

        $item = $this->get_item($component, $area, $itemid);

        // Item has to be there.
        if (empty($item)) {
            throw new moodle_exception('itemnotfound', 'local_shopping_cart');
        }

        // The undiscounted price of the item is price + discount.
        $initialdiscount = $item['discount'] ?? 0;

        // If setting to round discounts is turned on, we round to full int.
        $discountprecision = get_config('local_shopping_cart', 'rounddiscounts') ? 0 : 2;
        $initialdiscount = round($initialdiscount, $discountprecision);

        $initialprice = $item['price'] + $initialdiscount;

        if (!empty($percent)) {

            // Validation of percent value.
            if ($percent < 0 || $percent > 100) {
                throw new moodle_exception('absolutevalueinvalid', 'local_shopping_cart');
            }
            $item['discount'] = $initialprice / 100 * $percent;

            // If setting to round discounts is turned on, we round to full int.
            $item['discount'] = round($item['discount'],
                    $discountprecision);

            $item['price'] =
                    $initialprice - $item['discount'];
        } else if (!empty($absolute)) {
            // Validation of absolute value.
            if ($absolute < 0 || $absolute > $initialprice) {
                throw new moodle_exception('absolutevalueinvalid', 'local_shopping_cart');
            }
            $item['discount'] = $absolute;
            // If setting to round discounts is turned on, we round to full int.
            $item['discount'] = round($item['discount'],
                    $discountprecision);
            $item['price'] =
                    $initialprice - $item['discount'];
        } else {
            // If both are empty, we unset discount.
            $item['price'] = $initialprice;
            unset($item['discount']);
        }

        $this->save_item($item);

        return ['success' => 1];
    }

    /**
     * Returns one specific item.
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return array
     * @throws coding_exception
     */
    public function get_item(
        string $component,
        string $area,
        int $itemid) {

        $data = $this->get_cache();

        if ($data) {
            $cacheitemkey = $component . '-' . $area . '-' . $itemid;
            if (isset($data['items'][$cacheitemkey])) {
                return $data['items'][$cacheitemkey];
            }
        }
        return [];
    }

    /**
     * Returns all items.
     * @return array
     * @throws coding_exception
     */
    public function get_all_items() {

        $data = $this->get_cache();

        if ($data) {
            if (isset($data['items'])) {
                return $data['items'];
            }
        }
        return [];
    }

    /**
     * Saves one specific item (not add!).
     * @param array $item
     * @return bool
     * @throws coding_exception
     */
    public function save_item(
        array $item) {

        $data = $this->get_cache();

        if ($data) {
            $cacheitemkey = $item['componentname'] . '-' . $item['area'] . '-' . $item['itemid'];
            if (isset($data['items'][$cacheitemkey])) {
                $data['items'][$cacheitemkey] = $item;
                $this->set_cache($data);
                return true;
            }
        }
        return false;
    }

    /**
     * Delete items.
     * @return void
     * @throws coding_exception
     */
    public function delete_all_items() {

        // We check if we were already on checkout page.
        $cache = \cache::make('local_shopping_cart', 'schistory');
        $cache->delete('schistorycache');

        $data = $this->get_cache();

        if ($data) {
            if (isset($data['items'])) {
                $data['items'] = [];
                unset($data['openinstallments']);
                // When there are no items anymore, there is no expiration date.
                $data['expirationtime'] = 0;
                $this->set_cache($data);
            }
        }
    }

    /**
     * Set new balance in cache.
     * @param float $newbalance
     * @param string $currency
     * @param string $costcenter
     * @return void
     * @throws coding_exception
     */
    public function set_credit(float $newbalance, string $currency, string $costcenter = "") {

        $data = $this->get_cache();

        $data['credit'] = round($newbalance, 2);
        $data['remainingcredit'] = round($newbalance, 2);
        $data['currency'] = $currency;
        $data['costcenter'] = $costcenter;

        $this->set_cache($data);
    }

    /**
     * Set new paymentaccountid.
     * @param int $paymentaccountid
     * @return bool
     * @throws coding_exception
     */
    public function set_paymentaccountid(int $paymentaccountid): bool {

        $data = $this->get_cache();

        // If the paymentaccountid is not set yet, we just use the one we transmitted here.
        $storedpaymentaccountid = $data['paymentaccountid'] ?? $paymentaccountid;
        if ($storedpaymentaccountid != $paymentaccountid) {
            return false;
        }
        $data['paymentaccountid'] = $paymentaccountid;

        $this->set_cache($data);
        return true;
    }

    /**
     * Expirationtime.
     * @param int $expirationtime
     * @return void
     * @throws coding_exception
     */
    public function set_expiration(int $expirationtime) {

        $data = $this->get_cache();

        $data['expirationtime'] = $expirationtime;

        $this->set_cache($data);
    }

    /**
     * Gets the currently cached items.
     * @return void
     * @throws coding_exception
     */
    public function delete_bookingfee() {

        $data = $this->get_cache();

        $regexkey = '/^local_shopping_cart-bookingfee-/';

        // Before we add the other forms, we need to add the nosubmit in case of we just deleted an optiondate.
        $itemstodelete = preg_grep($regexkey, array_keys((array)$data['items']));
        foreach ($itemstodelete as $todelete) {
            unset($data['items'][$todelete]);
        }
        $this->set_cache($data);
    }

    /**
     * Gets the currently cached items.
     * @return void
     * @throws coding_exception
     */
    public function delete_rebookingfee() {

        $data = $this->get_cache();

        $regexkey = '/^local_shopping_cart-rebookingfee-/';

        // Before we add the other forms, we need to add the nosubmit in case of we just deleted an optiondate.
        $itemstodelete = preg_grep($regexkey, array_keys((array)$data['items']));
        foreach ($itemstodelete as $todelete) {
            unset($data['items'][$todelete]);
        }
        $this->set_cache($data);
    }

    /**
     * Saves the current use credit state.
     * @param int $usecredit
     * @return void
     * @throws coding_exception
     */
    public function save_usecredit_state(int $usecredit) {
        $data = self::get_cache();
        $data['usecredit'] = $usecredit;
        $this->set_cache($data);
    }

    /**
     * Saves the current use credit state.
     * @param int $useinstallments
     * @return void
     * @throws coding_exception
     */
    public function save_useinstallments_state(int $useinstallments) {
        $data = self::get_cache();
        $data['useinstallments'] = $useinstallments;
        $this->set_cache($data);
    }

    /**
     * This function checks if there is a schistory cache. If so, we replace it with newly calculated values.
     * We need this after eg. having called set_cache.
     * @return void
     */
    private function renew_schistory_cache_if_necessary() {
        // We check if we were already on checkout page.
        $cache = \cache::make('local_shopping_cart', 'schistory');
        // If there is a schistory cache...
        if ($data = $cache->get('schistorycache')) {

            $identifier = $data['identifier'];
            // We need to replace it.
            $data = $this->get_data();

            // In prepare checkout, schistory cache is set.
            checkout::prepare_checkout($data, $identifier);
        }
    }

    /**
     * Gets the current entries of the cache.
     * @param mixed $cachedata
     * @return void
     * @throws coding_exception
     */
    private function set_cache($cachedata) {

        $this->cachedata = $cachedata;

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $this->get_cachekey();

        $cache->set($cachekey, $cachedata);

        $this->renew_schistory_cache_if_necessary();
    }

    /**
     * Returns data and applies modifiers.
     * @return mixed cachedata
     */
    public function get_data() {
        $data = self::get_cache();

        // If we have cachedrawdata, we need to check the expiration date.
        if (isset($data['expirationtime']) && !is_null($data['expirationtime'])
                    && $data['expirationtime'] < time()) {
                self::delete_all_items();
                $data = self::get_cache();
        }

        $data['nowdate'] = time();

        modifier_info::apply_modfiers($data);
        return $data;
    }


    /**
     * Reset the singleton to force new build from cache and price modifiers.
     * @param int $userid
     *
     * @return void
     */
    public function reset_instance(int $userid) {

        self::$instance[$userid] = null;
    }

    /**
     * Determine wether there are currently items stored in cache.
     * @return bool
     * @throws coding_exception
     */
    public function has_items() {

        if ($items = $this->get_items()) {
            if (count($items) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if this item is already booked.
     * @param string $component
     * @param string $area
     * @param int $itemid
     * @return bool
     * @throws coding_exception
     */
    public function already_in_cart(
        string $component,
        string $area,
        int $itemid) {

        $data = $this->get_cache();

        if ($data) {
            $cacheitemkey = $component . '-' . $area . '-' . $itemid;
            if (isset($data['items'][$cacheitemkey])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the total price of all currently stored items.
     * @return int
     * @throws coding_exception
     */
    public function get_total_price_of_items() {

        $items = $this->get_items();

        if (count($items) == 0) {
            return 0;
        }

        return array_reduce($items, fn($a, $b) => $a += $b['price']);
    }

    /**
     * Returns true when all the booked items have the same constcenter.
     * @param string $currentcostcenter
     * @return bool
     * @throws coding_exception
     */
    public function same_costcenter(string $currentcostcenter) {
        $costcenterincart = '';

        $items = $this->get_items();
        foreach ($items as $itemincart) {
            if ($itemincart['area'] == 'bookingfee' || $itemincart['area'] == 'rebookingcredit') {
                // We only need to check for "real" items, booking fee does not apply.
                continue;
            } else {
                $costcenterincart = $itemincart['costcenter'] ?? '';
                if ($currentcostcenter != $costcenterincart) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns true when all the booked items have the same constcenter.
     *
     * @return $string
     * @throws coding_exception
     */
    public function get_costcenter(): string {
        $costcenterincart = '';

        $items = $this->get_items();
        foreach ($items as $itemincart) {
            if ($itemincart['area'] == 'bookingfee' || $itemincart['area'] == 'rebookingcredit') {
                // We only need to check for "real" items, booking fee does not apply.
                continue;
            } else {
                $costcenterincart = $itemincart['costcenter'] ?? '';
                break;
            }
        }
        return $costcenterincart;
    }

    /**
     * Returns the value for use credit from cache.
     * @return mixed
     * @throws coding_exception
     */
    public function get_usecredit_state() {

        $data = $this->get_cache();

        if ($data && isset($data['usecredit'])) {
            return $data['usecredit'];
        } else {
            return null;
        }
    }

    /**
     * Check if there is a rebookingitem currently in the cart.
     * @return bool
     * @throws coding_exception
     */
    public function is_rebooking() {

        $items = $this->get_items();
        foreach ($items as $item) {
            if (($item['area'] === 'rebookitem')
                && ($item['componentname'] === 'local_shopping_cart') ) {
                return true;
            }
        }
        return false;

    }

    /**
     * Gets the current entries of the cache.
     * @return mixed
     * @throws coding_exception
     */
    private function get_cache() {

        global $CFG;

        // To improve performance we keep the cache alive.
        if ($this->cachedata !== null) {
            return $this->cachedata;
        }
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $this->get_cachekey();

        $cachedata = $cache->get($cachekey);

        if (empty($cachedata)) {
            $taxesenabled = get_config('local_shopping_cart', 'enabletax') == 1;
            $usecredit = 1;

            [$credit, $currency] = shopping_cart_credits::get_balance($this->userid);

            $cachedata = [
                'userid' => $this->userid,
                'credit' => $credit,
                'remainingcredit' => $credit,
                'currency' => $currency,
                'count' => 0,
                'maxitems' => get_config('local_shopping_cart', 'maxitems'),
                'items' => [],
                'price' => 0.00,
                'taxesenabled' => $taxesenabled,
                'initialtotal' => 0.00,
                'deductible' => 0.00,
                'checkboxid' => bin2hex(random_bytes(3)),
                'usecredit' => $usecredit,
                'useinstallments' => 0,
                'expirationtime' => 0,
                'nowdate' => time(),
                'checkouturl' => $CFG->wwwroot . "/local/shopping_cart/checkout.php",
            ];
            $this->set_cache($cachedata);

        }
        $this->cachedata = $cachedata;
        return $cachedata;
    }

    /**
     * Gets the currently cached items.
     * @return array
     * @throws coding_exception
     */
    public function get_items(): array {

        $data = $this->get_cache();

        return $data['items'] ?? [];
    }

    /**
     * Gets the openinstallments.
     * @return array
     * @throws coding_exception
     */
    public function get_open_installments() {

        $data = $this->get_cache();

        return $data['openinstallments'] ?? [];
    }

    /**
     * Gets the openinstallments.
     * @return array
     * @throws coding_exception
     */
    public function get_due_installments() {

        $openinstallements = $this->get_open_installments();

        $returnarray = [];
        if (!empty($openinstallements)) {
            $now = time();

            foreach ($openinstallements as $openinstallment) {

                if (strpos($openinstallment['area'], 'installment') === false) {
                    continue;
                }

                $duedate = $openinstallment['installment'];
                $warningperiod = get_config('local_shopping_cart', 'reminderdaysbefore') ?: 3;

                // If the duedate minus warning period is bigger than time, we do nothing.
                if (($duedate - $warningperiod * 86400) < $now) {
                    $returnarray[] = $openinstallment;
                }
            }
        }
        return $returnarray;
    }



    /**
     * Gets the openinstallments.
     * @param array|bool $openinstallments
     * @return bool
     * @throws coding_exception
     */
    public function set_open_installments($openinstallments) {

        $data = $this->get_cache();

        $data['openinstallments'] = $openinstallments;
        $this->set_cache($data);

        return true;
    }


    /**
     * Items can be linked via a key which is part of the cartitem class.
     * The linkage is used eg to calculate the installpayments with subbookings.
     *
     * @param int $itemid
     * @param string $component
     * @param string $area
     *
     * @return array
     *
     */
    public function get_linked_items(int $itemid, string $component, string $area): array {

        $returnarray = [];
        $data = $this->get_cache();

        foreach ($data['items'] as $item) {

            if ($item['componentname'] !== $component) {
                continue;
            }

            $identifierarray = explode('_', $item['linkeditem'] ?? '');

            if (($area != $identifierarray[0] ?? '')
                || ($itemid != $identifierarray[1] ?? 0)) {

                continue;
            }
            $returnarray[] = $item;
        }

        return $returnarray;
    }

    /**
     * Gets the openinstallments.
     * @param string $country
     * @param string $vatnrnumber
     * @param string $companyname
     * @param string $street
     * @param string $place
     * @return bool
     * @throws coding_exception
     */
    public function set_vatnr_data($country, $vatnrnumber, $companyname, $street, $place) {

        $data = $this->get_cache();

        $data['vatnrcountry'] = $country;
        $data['vatnrnumber'] = $vatnrnumber;
        $data['companyname'] = $companyname;
        $data['street'] = $street;
        $data['place'] = $place;

        $this->set_cache($data);

        return true;
    }

    /**
     * Returns cached data only if vatnr is set.
     * VATNR data has the keys vatnrcountry, vatnrnumber, companyname, street & place.
     * @return array
     * @throws coding_exception
     */
    public function get_vatnr_data() {

        $data = $this->get_cache();

        if (!$this->has_vatnr_data()) {
            return [];
        }

        return $data;
    }

    /**
     * Check if vatnr data is there.
     * @return bool
     * @throws coding_exception
     */
    public function has_vatnr_data() {

        $data = $this->get_cache();

        if (!empty($data['vatnrnumber'])) {
            return true;
        }

        return false;
    }

    /**
     * Gets the openinstallments.
     * @return bool
     * @throws coding_exception
     */
    public function delete_vatnr_data() {

        $data = $this->get_cache();

        unset($data['vatnrcountry']);
        unset($data['vatnrnumber']);
        unset($data['companyname']);
        unset($data['street']);
        unset($data['place']);

        $this->set_cache($data);

        return true;
    }

    /**
     * Saves the selected addres ids ($selectedaddressesdbids) in the shopping cart cache.
     *
     * @param array $selectedaddressesdbids the addresses the user selected for this shopping cart
     * @return void
     */
    public function local_shopping_cart_save_address_in_cache(array $selectedaddressesdbids) {

        $data = $this->get_cache();

        $taxcountrycode = null; // Most probable tax country.
        $billingaddressid = null; // Most probable billing address.

        foreach ($selectedaddressesdbids as $addreskey => $addressdbid) {
            $data["address_" . $addreskey] = intval($addressdbid);
        }
        if (isset($data["address_billing"])) {
            // Override guessed billing address id if there is a dedicated billing address set.
            $billingaddressid = $data["address_billing"];
        }

        if ($billingaddressid != null) {
            $billingaddress = addresses::get_address_for_user($this->userid, $billingaddressid);
            $taxcountrycode = $billingaddress->state;
        }
        $data["taxcountrycode"] = $taxcountrycode;

        $this->set_cache($data);
    }

    /**
     * Returns the cachekey for this user as string.
     * @return string
     */
    private function get_cachekey() {
        return $this->userid . '_shopping_cart';
    }

    /**
     * Returns cached countrycode.
     * @return string
     * @throws coding_exception
     */
    public function get_countrycode() {
        $data = $this->get_cache();

        return $data['taxcountrycode'] ?? $data['vatnrcountry'] ?? null;
    }
}
