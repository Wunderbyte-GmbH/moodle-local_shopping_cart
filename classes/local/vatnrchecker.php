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
    const VATVIESCHECKERURL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';
    /**
     * @var string
     */
    const VATHMRCCHECKERURL = 'https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number';
    /**
     * @var string
     */
    const VATCOMPLYCHECKERURL = 'https://api.vatcomply.com/vat?vat_number=';
    /**
     * @var string
     */
    const RESTCOUNTRIESURL = "https://restcountries.com/v3.1/alpha/";

    /** @var stdClass $vatnrdataset Allows us to pass on the data we retrieve during verification to data processing */
    public static $vatnrdataset = null;

    /**
     * Function to return an array of localized country codes.
     * @param string $countrycode
     * @param string $vatnumber
     * @param ?object $client
     *
     * @return array
     */
    public static function validate_with_vies($countrycode, $vatnumber, ?object $client = null): array {
        $wsdl = "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";
        try {
            $client = $client ?? new SoapClient($wsdl);
            return (array) $client->checkVat([
                'countryCode' => $countrycode,
                'vatNumber' => $vatnumber,
            ]);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $vatnumber
     * @return array
     */
    public static function validate_with_hmrc($vatnumber) {
        if (!preg_match('/^\d{9}$/', $vatnumber)) {
            return [
                'error' => true,
            ];
        }
        $digits = str_split($vatnumber);
        $weights = [8, 7, 6, 5, 4, 3, 2, 1];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += $digits[$i] * $weights[$i];
        }
        $remainder = $sum % 97;
        $checkdigit = 97 - $remainder;
        return [
            'valid' => (int)$digits[8] === $checkdigit,
        ];
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $vatnumber
     * @return string
     */
    public static function validate_with_vatcomply($vatnumber) {
        $url = self::VATCOMPLYCHECKERURL . urlencode($vatnumber);
        return file_get_contents($url);
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $url
     * @param string $data
     * @return string
     */
    public static function make_vat_post_request($url, $data) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return "Error: " . curl_error($ch);
        }
        curl_close($ch);
        return $response;
    }


    /**
     * Function to return an array of localized country codes.
     * @param string $countrycode
     * @return string
     */
    public static function get_vat_region($countrycode) {
        if ($countrycode == 'GB') {
            return 'gb';
        }
        if (self::is_european_region($countrycode)) {
            return 'eu';
        }
        return 'restofworld';
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $countrycode
     * @return bool
     */
    public static function is_european_region($countrycode) {
        $url = self::RESTCOUNTRIESURL . urlencode($countrycode);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "Error: " . curl_error($ch);
            return false;
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (
            isset($data[0]['region']) &&
            $data[0]['region'] === 'Europe'
        ) {
            return true;
        }
        return false;
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $countrykey
     * @return bool
     */
    public static function is_european($countrykey) {
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
    public static function is_own_country($countrykey) {
        $hostvatnr = get_config('local_shopping_cart', 'owncountrycode');
        if ($countrykey == $hostvatnr) {
            return true;
        }
        return false;
    }
}
