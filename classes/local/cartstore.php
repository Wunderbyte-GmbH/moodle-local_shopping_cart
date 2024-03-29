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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Class cartstore
 *
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cartstore {

    /** @var array */
    protected $instance = [];

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
    private function get_cache() {

        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $this->get_cachekey();

        return $cache->get($cachekey);
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
