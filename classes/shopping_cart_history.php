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
 * Shopping_cart_history class for local shopping cart.
 * @package     local_shopping_cart
 * @author      Thomas Winkler
 * @copyright   2021 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart;

use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class shopping_cart_history.
 * @author      Thomas Winkler
 * @copyright   2021 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shopping_cart_history {

    /**
     * @var int
     */
    private $id;

    /**
     *
     * @var int
     */
    private $uid;

    /**
     *
     * @var int
     */
    private $itemid;

    /**
     *
     * @var string
     */
    private $itemname;

    /**
     * @var int
     */
    private $componentname;

    /**
     * pending,online or cash.
     * @var string
     */
    private $paymenttype;




    /**
     * History constructor
     */
    public function __construct(stdClass $data = null) {
        if ($data) {
            $this->uid = $data->uid;
            $this->itemid = $data->itemid;
            $this->itemname = $data->itemname;
            $this->componentname = $data->componentname;
            $this->paymenttype = $data->paymenttype;
        }  
    }

    /**
     * Prepare submitted form data for writing to db.
     *
     * @param int $userid
     * @return stdClass
     */
    public static function get_history_list_for_user(int $userid): stdClass {
        $data = new stdClass();
        return $data;
    }

    public function create_history($userid) {
        $prepareddata = $this->prepare_data_from_cache($userid);
        $this->write_to_db($prepareddata);
    }
    /**
     * write_to_db.
     *
     * @access private
     * @param int $userid
     * @param stdClass $data
     * @return integer
     */
    private function write_to_db($data): array {
        global $DB;
        return $DB->insert_records('local_shopping_cart_history', $data);
    }

    public function prepare_data_from_cache($userid): array {
        global $USER;
        $identifier = $this->create_unique_cart_identifier($userid);
        $userfromid = $USER->id;
        $userid = $USER->id;
        $cache = \cache::make('local_shopping_cart', 'cacheshopping');
        $cachekey = $userid . '_shopping_cart';
        $cachedrawdata = $cache->get($cachekey);
        echo $this->create_unique_cart_identifier($userid);
        $dataarr = [];
        foreach ($cachedrawdata["items"] as $item) {
            $data = $item;
            $data['expirationtime'] = $cachedrawdata["expirationdate"];
            $data['identifier'] = $identifier;
            $data['uid'] = $userfromid;
            $data['userfromid'] = $userid;
            $data['paymenttype'] = 'bar';
            $dataarr[] = $data;
        }
        return $dataarr;
    }
    
    private function create_unique_cart_identifier($userid): string {
        return $userid.'_'.time();
    }


    private function validate_data() {
        if (!isset($this->uid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['assignid'])) {
            throw new \coding_exception('The \'assignid\' value must be set in other.');
        }
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['assignid'])) {
            throw new \coding_exception('The \'assignid\' value must be set in other.');
        }
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['assignid'])) {
            throw new \coding_exception('The \'assignid\' value must be set in other.');
        }
    }


}
