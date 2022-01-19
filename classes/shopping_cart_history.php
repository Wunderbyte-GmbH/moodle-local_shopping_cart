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
     * @var int
     */
    private $moduleid;

    /**
     * @var int
     */
    private $userid;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $paymenttype;

    /**
     * @var int
     */
    private $timecreated;


    /**
     * Entity constructor.
     *
     * @param array $data
     */
    public function __construct(int $id = null) {
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

    /**
     * Write data into db.
     *
     * @param int $userid
     * @return int
     */
    private function write_to_db(int $userid): int {
        return 1;
    }
}
