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
     * @param cartitem $item
     * @return void
     * @throws coding_exception
     */
    public function add_item(cartitem $item) {

    }

    /**
     * Returns 0|1 fore the saved usecredit state, null if no such state exists.
     *
     * @param int $userid
     * @return ?int
     */
    public static function get_saved_usecredit_state(): ?int {
        $data = self::get_cache();

        if ($data && isset($data['usecredit'])) {
            return $data['usecredit'];
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
        $data['usecredit'] = $usecredit;
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

            sef::set_cache($cachedrawdata);
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
        $data = self::get_cache();

        // If we have cachedrawdata, we need to check the expiration date.
        if ($data) {
            if (isset($cachedrawdata['expirationdate']) && !is_null($cachedrawdata['expirationdate'])
                    && $cachedrawdata['expirationdate'] < time()) {
                self::delete_all_items();
                $data = self::get_cache();
            }
        }

        modifier_info::apply_modfiers($data);
        return $data;
    }

    /**
     * Gets the current entries of the cache.
     * @param mixed cachedata
     * @return mixed
     * @throws coding_exception
     */
    private function set_cache($cachedata) {
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
    private function get_cachekey() {
        return $this->userid . '_shopping_cart';
    }
}
