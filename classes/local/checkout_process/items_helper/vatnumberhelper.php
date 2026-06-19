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
    const VATCOMPLYCHECKERURL = 'https://api.vatcomply.com/vat?vat_number=';

    /**
     * @var string
     */
    const WSDL = "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";

    /**
     * ISO country codes treated as EU VAT region for VIES checks.
     *
     * @var array
     */
    const EUROPEANVATCOUNTRYCODES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO',
        'SE', 'SI', 'SK',
    ];

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
        ];
    }

    /**
     * Function to verify VATNR of business partner online.
     * @param string $countrycode
     * @param string $vatnrnumber
     * @param ?object $client
     * @return bool
     */
    /**
     * Diagnostic trace of the last is_vatnr_valid() call (request-scoped).
     * @var array
     */
    private static $lasttrace = [];

    /**
     * Set when the last check failed because the number is the site's own VAT number.
     * @var bool
     */
    private static $lastfailureownvat = false;

    /**
     * Language key for the last VAT validation technical error (rate limit,
     * service unavailable), for detailed user feedback.
     * @var string|null
     */
    protected static $lastvalidationerrorkey = null;

    /**
     * Records a diagnostic trace line for the last VAT check.
     *
     * @param string $line
     * @return void
     */
    private static function add_trace(string $line): void {
        self::$lasttrace[] = $line;
    }

    /**
     * Returns the diagnostic trace of the last VAT check as HTML (for
     * developer-debug feedback).
     *
     * @return string
     */
    public static function get_last_trace_html(): string {
        if (empty(self::$lasttrace)) {
            return '';
        }
        return '<br><small>' . implode('<br>', array_map('s', self::$lasttrace)) . '</small>';
    }

    /**
     * True when the last check failed because the user entered the site's own VAT number.
     *
     * @return bool
     */
    public static function last_failure_was_own_vatnr(): bool {
        return self::$lastfailureownvat;
    }

    public static function is_vatnr_valid(string $countrycode, string $vatnrnumber, ?object $client = null): bool {
        self::$lasttrace = [];
        self::$lastfailureownvat = false;
        self::$lastvalidationerrorkey = null;
        self::add_trace("input: country={$countrycode} vatnr={$vatnrnumber}");

        // Special treatment for the Behat and PHPUnit tests.
        if ((defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $key = 'mockvat_' . strtolower($countrycode) . '_' . strtolower($vatnrnumber);
            $mockresponse = get_config('local_shopping_cart', $key);
            if (!empty($mockresponse)) {
                $decoded = json_decode($mockresponse, true);
                return isset($decoded['valid']) && $decoded['valid'];
            }
        }

        $ownvatnrnumber = (string)get_config('local_shopping_cart', 'ownvatnrnumber');
        if (empty($countrycode) || empty($vatnrnumber)) {
            self::add_trace('rejected: empty country or vat number');
            return false;
        }
        if ($ownvatnrnumber !== '' && str_contains($ownvatnrnumber, $vatnrnumber)) {
            // Buying from yourself with your own VAT number is not allowed.
            self::$lastfailureownvat = true;
            self::add_trace("rejected: matches own VAT number config ({$ownvatnrnumber})");
            return false;
        }
        $vatregion = self::get_vat_region($countrycode);
        self::add_trace("region: {$vatregion}");
        $vatnrnumber = str_replace($countrycode, '', $vatnrnumber);
        switch ($vatregion) {
            case 'gb':
                $response = self::validate_gb_format($vatnrnumber);
                self::add_trace('validator: gb format check');
                break;
            case 'eu':
                $response = self::validate_with_vies($countrycode, $vatnrnumber, $client);
                self::add_trace('validator: VIES (SOAP), response: ' . json_encode($response));
                break;
            default:
                $response = self::validate_with_vatcomply($vatnrnumber);
                self::add_trace('validator: vatcomply, response: ' . json_encode($response));
                break;
        }

        // A technical error (service unavailable, rate limit) is not the same as
        // an invalid VAT number: remember the error key for detailed feedback.
        if (!is_array($response)) {
            self::$lastvalidationerrorkey = 'errorvatnrserviceunavailable';
            return false;
        }
        if (!array_key_exists('valid', $response) && empty($response['error'])) {
            self::$lastvalidationerrorkey = 'errorvatnrserviceunavailable';
            return false;
        }
        if (!empty($response['error'])) {
            self::$lastvalidationerrorkey = $response['errorcode'] ?? 'errorvatnrserviceunavailable';
            return false;
        }

        if (isset($response['valid']) && $response['valid']) {
            return true;
        }
        return false;
    }

    /**
     * Return the language key for the last VAT validation technical error,
     * or null if the last check had no technical error.
     *
     * @return string|null
     */
    public static function get_last_validation_error_key(): ?string {
        return self::$lastvalidationerrorkey;
    }

    /**
     * Map VIES/SOAP error messages to user-facing language keys, so a rate
     * limit is reported differently from a general service outage.
     *
     * @param string $message
     * @return string
     */
    protected static function map_vies_error_to_string_key(string $message): string {
        $messagelc = strtolower($message);
        $limitmarkers = [
            'ms_max_concurrent_req',
            'global_max_concurrent_req',
            'max concurrent',
            'too many requests',
            'rate limit',
            'throttle',
            'request limit',
        ];
        foreach ($limitmarkers as $marker) {
            if (str_contains($messagelc, $marker)) {
                return 'errorvatnrrequestlimit';
            }
        }
        return 'errorvatnrserviceunavailable';
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
        // Use a fixed EU country list instead of an external geo API: the
        // VAT region of a country is stable and an external call here was a
        // reliability risk (the previously used restcountries API was retired).
        return in_array(strtoupper((string)$countrycode), self::EUROPEANVATCOUNTRYCODES, true);
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $vatnumber
     * @return array
     */
    public static function validate_gb_format(string $vatnumber): array {
        // Remove non-digits.
        $vatnumber = preg_replace('/\D/', '', $vatnumber);

        // Length check: UK VAT numbers are 9 or 12 digits.
        if (!preg_match('/^\d{9}(\d{3})?$/', $vatnumber)) {
            return ['valid' => false];
        }

        // Handle 12-digit branch numbers (ignore last 3 digits).
        if (strlen($vatnumber) === 12) {
            $vatnumber = substr($vatnumber, 0, 9);
        }

        $digits = array_map('intval', str_split($vatnumber));

        // Step 1: Multiply first 7 digits by weights.
        $weights = [8, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 7; $i++) {
            $sum += $digits[$i] * $weights[$i];
        }

        // Step 2: Modulo 97.
        $remainder = $sum % 97;

        // Step 3: Calculate check digits.
        $checkdigits = 97 - $remainder;

        // Step 4: Compare with last 2 digits.
        $provided = ($digits[7] * 10) + $digits[8];

        if ($checkdigits === $provided) {
            return ['valid' => true];
        }

        // Some VAT numbers require adding 55 to the total before mod 97.
        $sum += 55;
        $remainder = $sum % 97;
        $checkdigits = 97 - $remainder;

        if ($checkdigits === $provided) {
            return ['valid' => true];
        }

        // Valid VAT numbers must fall into certain ranges.
        $numeric = (int)$vatnumber;

        $validranges = [
            [100000000, 999999999],
            [1000000, 9999999],
        ];

        foreach ($validranges as [$min, $max]) {
            if ($numeric >= $min && $numeric <= $max) {
                return ['valid' => true];
            }
        }

        return ['valid' => false];
    }

    /**
     * Function to return an array of localized country codes.
     * @param string $vatnumber
     * @return string
     */
    public static function validate_with_vatcomply($vatnumber): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $url = self::VATCOMPLYCHECKERURL . urlencode($vatnumber);
        $curl = new \curl();
        $response = $curl->get($url, [], [
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 3,
            'CURLOPT_TIMEOUT' => 10,
        ]);
        // The callers expect an array shaped like ['valid' => bool].
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            return [
                'error' => true,
                'errorcode' => 'errorvatnrserviceunavailable',
            ];
        }
        return $decoded;
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
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'errorcode' => self::map_vies_error_to_string_key($e->getMessage()),
            ];
        }
    }
}
