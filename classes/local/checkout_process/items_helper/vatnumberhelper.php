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
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\local\checkout_process\items_helper;

use SoapClient;

/**
 * Class checkout
 *
 * @author Jacob Viertel
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vatnumberhelper {
    /**
     * VATNRCHECKERURL
     * @var string
     */
    const VATNRCHECKERURL = 'https://ec.europa.eu/taxation_customs/vies/rest-api//check-vat-number';
    /**
     * @var string
     */
    const RESTCOUNTRIESURL = "https://restcountries.com/v3.1/alpha/";
    /**
     * @var string
     */
    const VATCOMPLYCHECKERURL = 'https://api.vatcomply.com/vat?vat_number=';

    /**
     * @var string
     */
    const WSDL = "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";

    /**
     * Function to return an array of localized country codes.
     *
     * @return array
     */
    public static function get_countrycodes_array(): array {
        $stringman = get_string_manager();
        return [
            'novatnr' => $stringman->get_string('novatnr', 'local_shopping_cart'),
            'AT' => $stringman->get_string('at', 'local_shopping_cart'),
            'DE' => $stringman->get_string('de', 'local_shopping_cart'),
            'EU' => $stringman->get_string('eu', 'local_shopping_cart'),
            'BE' => $stringman->get_string('be', 'local_shopping_cart'),
            'BG' => $stringman->get_string('bg', 'local_shopping_cart'),
            'CY' => $stringman->get_string('cy', 'local_shopping_cart'),
            'CZ' => $stringman->get_string('cz', 'local_shopping_cart'),
            'DK' => $stringman->get_string('dk', 'local_shopping_cart'),
            'EE' => $stringman->get_string('ee', 'local_shopping_cart'),
            'EL' => $stringman->get_string('el', 'local_shopping_cart'),
            'ES' => $stringman->get_string('es', 'local_shopping_cart'),
            'FI' => $stringman->get_string('fi', 'local_shopping_cart'),
            'FR' => $stringman->get_string('fr', 'local_shopping_cart'),
            'HR' => $stringman->get_string('hr', 'local_shopping_cart'),
            'HU' => $stringman->get_string('hu', 'local_shopping_cart'),
            'IE' => $stringman->get_string('ie', 'local_shopping_cart'),
            'IT' => $stringman->get_string('it', 'local_shopping_cart'),
            'LU' => $stringman->get_string('lu', 'local_shopping_cart'),
            'LV' => $stringman->get_string('lv', 'local_shopping_cart'),
            'LT' => $stringman->get_string('lt', 'local_shopping_cart'),
            'MT' => $stringman->get_string('mt', 'local_shopping_cart'),
            'NL' => $stringman->get_string('nl', 'local_shopping_cart'),
            'PL' => $stringman->get_string('pl', 'local_shopping_cart'),
            'PT' => $stringman->get_string('pt', 'local_shopping_cart'),
            'RO' => $stringman->get_string('ro', 'local_shopping_cart'),
            'SE' => $stringman->get_string('se', 'local_shopping_cart'),
            'SI' => $stringman->get_string('si', 'local_shopping_cart'),
            'SK' => $stringman->get_string('sk', 'local_shopping_cart'),
            'GB' => $stringman->get_string('gb', 'local_shopping_cart'),
            'XI' => $stringman->get_string('xi', 'local_shopping_cart'),
        ];
    }

    /**
     * Function to verify VATNR of business partner online.
     * @param string $countrycode
     * @param string $vatnrnumber
     * @param ?object $client
     * @return bool
     */
    public static function is_vatnr_valid(string $countrycode, string $vatnrnumber, ?object $client = null): bool {
        // Special treatment for the Behat and PHPUnit tests.
        if ((defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $key = 'mockvat_' . strtolower($countrycode) . '_' . strtolower($vatnrnumber);
            $mockresponse = get_config('local_shopping_cart', $key);
            if (!empty($mockresponse)) {
                $decoded = json_decode($mockresponse, true);
                return isset($decoded['valid']) && $decoded['valid'];
            }
        }

        if (
            empty($countrycode) ||
            empty($vatnrnumber)
        ) {
            return false;
        }
        $vatregion = self::get_vat_region($countrycode);
        $vatnrnumber = str_replace($countrycode, '', $vatnrnumber);
        switch ($vatregion) {
            case 'gb':
                $response = self::validate_with_hmrc($vatnrnumber);
                break;
            case 'eu':
                $response = self::validate_with_vies($countrycode, $vatnrnumber, $client);
                break;
            default:
                $response = self::validate_with_vatcomply($vatnrnumber);
                break;
        }
        if (isset($response['valid']) && $response['valid']) {
            return true;
        }
        return false;
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
     * @param string $countrycode
     * @param string $vatnumber
     * @param ?object $client
     *
     * @return array
     */
    public static function validate_with_vies($countrycode, $vatnumber, ?object $client = null) {
        try {
            $client = $client ?? new SoapClient(self::WSDL);
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
}
