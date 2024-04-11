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
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use coding_exception;
use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\local\pricemodifier\modifier_info;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_bookingfee;
use local_shopping_cart\shopping_cart_rebookingcredit;
use context_system;
use local_shopping_cart\event\item_added;
use local_shopping_cart\shopping_cart_handler;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/shopping_cart/lib.php');

/**
 * Class cartstore
 *
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cartstore {

    /** @var array */
    protected static $instance = [];

    /** @var int */
    protected $userid = 0;

    /** @var bool */
    protected $usecredit = null;

    /**
     * entities constructor.
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
     * @param string componentname
     * @param string area
     * @param int itemid
     * @return void
     * @throws coding_exception
     */
    public function add_item(string $component, string $area, int $itemid) {
        global $DB, $USER;

        $buyforuser = false;

        $response = shopping_cart::allow_add_item_to_cart($component, $area, $itemid, $this->userid);
        $userid = $this->userid;
        $cacheitemkey = $component . '-' . $area . '-' . $itemid;
        $cartparam = $response['success'];
        $cachedrawdata = $this->get_cache();
        if (!$cachedrawdata) {
            $cachedrawdata = [];
        }
        if ($cartparam == LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS) {
            // If we have nothing in our cart and we are not about...
            // ... to add the booking fee...
            // ... we add the booking fee.
            if ((empty($cachedrawdata['items'])
                || array_reduce($cachedrawdata['items'], fn($a, $b) => $a += $b['price']) == 0)
                && !in_array($area, ['bookingfee', 'rebookingcredit', 'rebookitem'])) {
                // If we buy for user, we need to use -1 as userid.
                // Also we add $userid as second param so we can check if fee was already paid.
                shopping_cart_bookingfee::add_fee_to_cart($buyforuser ? -1 : $userid, $buyforuser ? $userid : 0);
                $cachedrawdata = $this->get_cache();
            }
        }

        $expirationtimestamp = shopping_cart::get_expirationdate();

        switch ($cartparam) {
            case LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS:
                // This gets the data from the component and also triggers reservation.
                // If reservation is not successful, we have to react here.
                $cartitemarray = shopping_cart::load_cartitem($component, $area, $itemid, $userid);
                if (isset($cartitemarray['cartitem'])) {
                    // Get the itemdata as array.
                    $itemdata = $cartitemarray['cartitem']->as_array();
                    $itemdata['price'] = $itemdata['price'];

                    // At this point, we might have added the booking fee to the cart.
                    // This is because we always add the fee first.
                    // But if the price of the item we buy is 0, we don't want to demand a booking fee neither.
                    // Therefore, we need to delete it again from the cart.
                    if (($itemdata['price'] == 0)
                        && count($cachedrawdata['items']) < 2) {

                        $regexkey = '/^local_shopping_cart-bookingfee-/';
                        // Before we add the other forms, we need to add the nosubmit in case of we just deleted an optiondate.
                        $itemstodelete = preg_grep($regexkey, array_keys((array)$cachedrawdata['items']));

                        foreach ($itemstodelete as $todelete) {
                            unset($cachedrawdata['items'][$todelete]);
                        }
                    }

                    // Now we check if we allow installments.
                    // We don't check fo booking fee and other shopping cart related items.
                    if (($component !== 'local_shopping_cart')
                        && shopping_cart_handler::installment_exists($component, $area, $itemid)) {
                        $itemdata['installment'] = true;
                    }
                    if (!$cachedrawdata) {
                        $cachedrawdata = [];
                    }

                    // Then we set item in Cache.
                    $cachedrawdata['items'][$cacheitemkey] = $itemdata;
                    $cachedrawdata['expirationdate'] = $expirationtimestamp;

                    $this->set_cache($cachedrawdata);

                    // If it applies, we add the rebookingcredit.
                    shopping_cart_rebookingcredit::add_rebookingcredit($cachedrawdata, $area, $buyforuser ? -1 : $userid);

                    $itemdata['expirationdate'] = $expirationtimestamp;
                    $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_SUCCESS;
                    $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;

                    // Add or reschedule all delete_item_tasks for all the items in the cart.
                    shopping_cart::add_or_reschedule_addhoc_tasks($expirationtimestamp, $userid);

                    $context = context_system::instance();
                    // Trigger item deleted event.
                    $event = item_added::create([
                        'context' => $context,
                        'userid' => $USER->id,
                        'relateduserid' => $userid,
                        'other' => [
                            'itemid' => $itemid,
                            'component' => $component,
                        ],
                    ]);

                    $event->trigger();
                } else {
                    $itemdata = [];
                    $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_ERROR;
                    $itemdata['expirationdate'] = 0;
                    $itemdata['price'] = 0;
                    $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                }
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_COSTCENTER:
                $itemdata = [];
                $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_COSTCENTER;
                    // Important. In JS we show the modal based on success 2.
                $itemdata['expirationdate'] = 0;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                $itemdata['price'] = 0;
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_ALREADYINCART:
                // This case means that we have the item already in the cart.
                // Normally, this should not happen, because of JS, but it might occure when a user is...
                // Logged in on two different devices.
                $itemdata = [];
                $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_ALREADYINCART;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                $itemdata['expirationdate'] = $expirationtimestamp;
                $itemdata['price'] = 0;
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_CARTISFULL:
                $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_CARTISFULL;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                $itemdata['expirationdate'] = $expirationtimestamp;
                $itemdata['price'] = 0;
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_FULLYBOOKED:
                $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_FULLYBOOKED;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                $itemdata['expirationdate'] = $expirationtimestamp;
                $itemdata['price'] = 0;
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_ALREADYBOOKED:
                $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_ALREADYBOOKED;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                $itemdata['expirationdate'] = $expirationtimestamp;
                $itemdata['price'] = 0;
                break;
            case LOCAL_SHOPPING_CART_CARTPARAM_ERROR:
            default:
                $itemdata['success'] = LOCAL_SHOPPING_CART_CARTPARAM_ERROR;
                $itemdata['buyforuser'] = $USER->id == $userid ? 0 : $userid;
                $itemdata['expirationdate'] = $expirationtimestamp;
                $itemdata['price'] = 0;
                break;
        }
        return $itemdata;
    }

    /**
     * Deletes an item from the shopping cart cache store.
     * @param string componentname
     * @param string area
     * @param int itemid
     * @param bool $unload
     * @return void
     * @throws coding_exception
     */
    public function delete_item(string $component, string $area, int $itemid, $unload) {
        global $USER;
        $cachedrawdata = $this->get_cache();
        $cachekey = $this->get_cachekey();
        if ($cachedrawdata) {
            $cacheitemkey = $component . '-' . $area . '-' . $itemid;
            if (isset($cachedrawdata['items'][$cacheitemkey])) {
                unset($cachedrawdata['items'][$cacheitemkey]);
                $this->set_cache($cachedrawdata);
            }
        }

        if ($unload) {
            // This treats the related component side.

            // This function can return an array of items to unload as well.
            $response = shopping_cart::unload_cartitem($component, $area, $itemid, $this->userid);
            foreach ($response['itemstounload'] as $cartitem) {
                shopping_cart::delete_item_from_cart($component, $cartitem->area, $cartitem->itemid, $this->userid);
            }
        }

        if (isset($response) && isset($response['success']) && $response['success'] == 1) {

            $context = context_system::instance();
            // Trigger item deleted event.
            $event = item_deleted::create([
                'context' => $context,
                'userid' => $USER->id,
                'relateduserid' => $this->userid,
                'other' => [
                    'itemid' => $itemid,
                    'component' => $component,
                ],
            ]);

            $event->trigger();
        }

        // If there are only fees and/or rebookingcredits left, we delete them.
        if (!empty($cachedrawdata['items'])) {

            // At first, check we can delete.
            $letsdelete = true;
            foreach ($cachedrawdata['items'] as $remainingitem) {
                if ($remainingitem['area'] === 'bookingfee' ||
                    $remainingitem['area'] === 'rebookingcredit') {
                    continue;
                } else {
                    // If we still have bookable items, we cannot delete fees and credits from cart.
                    $letsdelete = false;

                    // Also check, if we need to adjust rebookingcredit.
                    shopping_cart_rebookingcredit::add_rebookingcredit($cachedrawdata, $area, $this->userid);
                }
            }

            if ($letsdelete) {
                foreach ($cachedrawdata['items'] as $item) {
                    if (($item['area'] == 'bookingfee' ||
                        $item['area'] == 'rebookingcredit')
                        && $item['componentname'] == 'local_shopping_cart') {
                        shopping_cart::delete_all_items_from_cart($this->userid);
                    }
                }
            }
        }
    }

    /**
     * Check if item can be added to cart.
     * @return bool
     */
    public function allow_add_item(): bool {
        // TODO Run check and return bool
    }

    /**
     * Returns 0|1 fore the saved usecredit state, null if no such state exists.
     *
     * @param int $userid
     * @return ?int
     */
    public function get_saved_usecredit_state(): ?int {
        $data = self::get_cache();

        if ($data && isset($data['usecredit'])) {
            return $data['usecredit'];
        } else if ($this->usecredit !== null) {
            return $this->usecredit;
        } else {
            return null;
        }
    }

    /**
     * Sets the usecredit value in Cache for the user.
     *
     * @param int $userid
     * @param bool $usecredit
     * @return void
     */
    public function save_used_credit_state(bool $usecredit) {
        $data = self::get_cache();
        $this->usecredit = $usecredit;
        if ($data) {
            $data['usecredit'] = $usecredit;
        } else {
            $data = [];
            $data['usecredit'] = $usecredit;
        }
        self::set_cache($data);
    }

    /**
     * Deletes all items from the cache.
     * @return void
     * @throws coding_exception
     */
    public function delete_all_items() {
        $cachedrawdata = self::get_cache();
        if ($cachedrawdata) {

            unset($cachedrawdata['items']);
            unset($cachedrawdata['expirationdate']);

            self::set_cache($cachedrawdata);
        }
    }

    /**
     * Determine wether there are currently items stored in cache.
     * @return bool
     * @throws coding_exception
     */
    public function has_items() {

        if ($items = $this->get_cached_items()) {
            if (count($items) > 0) {
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
    public function get_cache() {

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $this->get_cachekey();

        return $cache->get($cachekey);
    }



    /**
     * Returns data and applies modifiers.
     * @return mixed cachedata
     */
    public function get_data() {
        global $CFG;
        $data = $this->get_cache();

        // If we have cachedrawdata, we need to check the expiration date.
        if ($data && isset($data['items'])) {
            if (isset($data['expirationdate']) && !is_null($data['expirationdate'])
                    && $data['expirationdate'] < time()) {
                self::delete_all_items();
                $data = self::get_cache();
            }
            // Data is false.
        } else {
            $data = [];
            $data['count'] = 0;
            $data['currency'] = null;
            $data['remainingcredit'] = null;
        }
        // General.
        $data['userid'] = $this->userid;
        $data['maxitems'] = get_config('local_shopping_cart', 'maxitems');
        $data['price'] = 0.00;
        $data['initialtotal'] = 0.00;
        $data['deductible'] = 0.00;
        // Default val.
        $data['taxesenabled'] = false;
        // $data['checkboxid'] = bin2hex(random_bytes(3));
        $data['usecredit'] = $this->get_saved_usecredit_state();
        $data['expirationdate'] = time();
        $data['nowdate'] = time();
        $data['checkouturl'] = $CFG->wwwroot . "/local/shopping_cart/checkout.php";
        $data['credit'] = null;
        $data['currency'] = $cachedrawdata['currency'] ?? null;
        $data['remainingcredit'] = $data['credit'];

        modifier_info::apply_modfiers($data);
        return $data;
    }

    /**
     * Gets the current entries of the cache.
     * @param mixed cachedata
     * @return mixed
     * @throws coding_exception
     */
    public function set_cache($cachedata) {
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $this->get_cachekey();

        $cache->set($cachekey, $cachedata);
    }

    /**
     * Gets the currently cached items.
     * @return mixed
     * @throws coding_exception
     */
    private function get_cached_items() {

        $cache = $this->get_cache();

        return $cache['items'] ?? [];
    }


    /**
     * Returns the cachekey for this user as string.
     * @return string
     */
    public function get_cachekey() {
        return $this->userid . '_shopping_cart';
    }
}
