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
 * The templaterule class handles the interactions with template rules.
 *
 * @package local_shopping_cart
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local;

use SoapClient;
use local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper;

/**
 * Class templaterule
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnrchecker {
    /**
     * @var string
     */
    const RESTCOUNTRIESURL = "https://restcountries.com/v3.1/alpha/";

    /** @var stdClass $vatnrdataset Allows us to pass on the data we retrieve during verification to data processing */
    public static $vatnrdataset = null;


    /**
     * Function to determine if country is within EU. Returns false when no country code was given.
     * @param string $countrykey
     * @return bool
     */
    public static function is_european(string $countrykey): bool {
        $countries = vatnumberhelper::get_countrycodes_array();
        if (isset($countries[$countrykey])) {
            return true;
        }
        return false;
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $countrykey
     * @return bool
     */
    public static function is_own_country(string $countrykey): bool {
        $hostvatnr = get_config('local_shopping_cart', 'owncountrycode');
        if ($countrykey == $hostvatnr) {
            return true;
        }
        return false;
    }
}
