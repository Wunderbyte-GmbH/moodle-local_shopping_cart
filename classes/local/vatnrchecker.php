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

/**
 * Class templaterule
 *
 * @author Georg Maißer
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnrchecker {

    /**
     * VATNRCHECKERURL
     *
     * @var string
     */
    const VATNRCHECKERURL = 'https://ec.europa.eu/taxation_customs/vies/rest-api//check-vat-number';

    /** @var stdClass $vatnrdataset Allows us to pass on the data we retrieve during verification to data processing */
    public static $vatnrdataset = null;

    /**
     * Function to verify VATNR of business partner online.
     * @param string $countrycode
     * @param string $vatnrnumber
     * @return string
     */
    public static function check_vatnr_number(string $countrycode, string $vatnrnumber) {
        $response = [];

        if (empty($countrycode)
            || empty($vatnrnumber)) {

            return '';
        }

        $url = self::VATNRCHECKERURL;
        $params = (object)[
            "countryCode" => $countrycode,
            "vatNumber" => $vatnrnumber,
            "requesterMemberStateCode" => get_config('local_shopping_cart', 'owncountrycode'),
            "requesterNumber" => get_config('local_shopping_cart', 'ownvatnrnumber'),

        ];

        $header = [
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);

        $info = curl_getinfo($ch);
        $error = curl_error($ch);

        curl_close($ch);

        return $response;
    }

    /**
     * Function to return an array of localized country codes.
     *
     * @return array
     */
    public static function return_countrycodes_array() {
        $originallanguage = current_language();
        force_current_language('en');
        $countries = [
            'novatnr' => get_string('novatnr', 'local_shopping_cart'),
            'AT' => get_string('at', 'local_shopping_cart'),
            'BE' => get_string('be', 'local_shopping_cart'),
            'BG' => get_string('bg', 'local_shopping_cart'),
            'CY' => get_string('cy', 'local_shopping_cart'),
            'CZ' => get_string('cz', 'local_shopping_cart'),
            'DE' => get_string('de', 'local_shopping_cart'),
            'DK' => get_string('dk', 'local_shopping_cart'),
            'EE' => get_string('ee', 'local_shopping_cart'),
            'EL' => get_string('el', 'local_shopping_cart'),
            'ES' => get_string('es', 'local_shopping_cart'),
            'FI' => get_string('fi', 'local_shopping_cart'),
            'FR' => get_string('fr', 'local_shopping_cart'),
            'HR' => get_string('hr', 'local_shopping_cart'),
            'HU' => get_string('hu', 'local_shopping_cart'),
            'IE' => get_string('ie', 'local_shopping_cart'),
            'IT' => get_string('it', 'local_shopping_cart'),
            'LU' => get_string('lu', 'local_shopping_cart'),
            'LV' => get_string('lv', 'local_shopping_cart'),
            'LT' => get_string('lt', 'local_shopping_cart'),
            'MT' => get_string('mt', 'local_shopping_cart'),
            'NL' => get_string('nl', 'local_shopping_cart'),
            'PL' => get_string('pl', 'local_shopping_cart'),
            'PT' => get_string('pt', 'local_shopping_cart'),
            'RO' => get_string('ro', 'local_shopping_cart'),
            'SE' => get_string('se', 'local_shopping_cart'),
            'SI' => get_string('si', 'local_shopping_cart'),
            'SK' => get_string('sk', 'local_shopping_cart'),
            'GB' => get_string('gb', 'local_shopping_cart'),
            'XI' => get_string('xi', 'local_shopping_cart'),
            'EU' => get_string('eu', 'local_shopping_cart'),
        ];
        force_current_language($originallanguage);
        return $countries;
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $countrykey
     * @return array
     */
    public static function is_european($countrykey) {
        $countries = self::return_countrycodes_array();
        if (isset($countries[$countrykey])) {
            return true;
        }
        return false;
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $countrykey
     * @return array
     */
    public static function is_own_country($countrykey) {
        $hostvatnr = get_config('local_shopping_cart', 'owncountrycode');
        if ($countrykey == $hostvatnr) {
            return true;
        }
        return false;
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $countrykey
     * @return string
     */
    public static function get_template(
        $iseuropean,
        $isowncountry,
        $uid
    ) {
        if (!is_null($uid)) {
            return 'Export VAT';
        }
        $hostvatnr = get_config('local_shopping_cart', 'owncountrycode');
        $countries = self::return_countrycodes_array();
        if (
            $isowncountry ||
            (
                $iseuropean &&
                get_config('local_shopping_cart', 'owncountrytax')
            )
        ) {
            return $countries[$hostvatnr] . ' Tax';
        } else if ($iseuropean) {
            return 'EU Reverse Charge';
        }
        return 'Export VAT';
    }
}
