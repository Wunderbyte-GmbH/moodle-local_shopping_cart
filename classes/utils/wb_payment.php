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
 * Wunderbyte Payment Methods.
 *
 * Contains methods for license verification and more.
 *
 * @package local_shopping_cart
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\utils;

/**
 * Class to handle Wunderbyte Payment Methods.
 *
 * Contains methods for license verification and more.
 *
 * @copyright 2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wb_payment {
    /** @var string The product identifier license keys must carry to activate PRO features here. */
    const PRODUCT = 'shoppingcartpro';

    /** @var string Wunderbyte shared public key used to verify license keys. */
    const WB_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu8vRBnPDug2pKoGY9wQS
KNTK1SzrPuU0KC8xm22GPQZQM1XkPpvNwBp8CmXUN29r/qiPxapDNVmIH5Ectvb+
NA7EsuVSS8xV6HfjV0tNZKIfFA4b1JD7t6l4gGDLuoppvKQV9n1JP/uZhQlFZ8Dg
7qMXGsEWRcmRGSBZxIVA+EiN35ALsR78MYWEmuAtKKtskqD4cwnAQzZhU1tZRFHz
/uSfhS2tFXQ7vjvCPIozzo9Mgy4Vr4Qoc9ohg0AfK/D3IoA/mpQFpVC+hyS+rQ0d
uqjiVvh1b0cI3ZBEwWeaNKR4Z3dVb3RHOnICCJPyxxIfSDKWDmQDMCMLa5UjvSvM
pwIDAQAB
-----END PUBLIC KEY-----";

    /**
     * Decrypt a license key to get its payload (expiration date, optionally
     * followed by ";" and the product identifier the key was issued for).
     *
     * @param string $encryptedlicensekey
     * @return string|false the decrypted payload, or false if it could not be decrypted
     */
    public static function decryptlicensekey(string $encryptedlicensekey) {
        global $CFG;
        // Step 1: Do base64 decoding.
        $encryptedlicensekey = base64_decode($encryptedlicensekey);

        // Step 2: Decrypt using public key.
        openssl_public_decrypt($encryptedlicensekey, $licensekey, self::WB_PUBLIC_KEY);

        // Step 3: Do another base64 decode and decrypt using wwwroot.
        if (empty($licensekey)) {
            return false;
        }

        $c = base64_decode($licensekey);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-128-CBC");
        $iv = substr($c, 0, $ivlen);

        // Bugfix when passing wrong license keys that are too short.
        if (strlen($iv) != 16) {
            return false;
        }

        $sha2len = 32;
        $ciphertextraw = substr($c, $ivlen + $sha2len);
        $decryptedcontent = openssl_decrypt($ciphertextraw, $cipher, $CFG->wwwroot, $options = OPENSSL_RAW_DATA, $iv);

        return $decryptedcontent;
    }

    /**
     * Decrypt and validate the license key currently stored in config.
     *
     * @return array with keys 'valid' (bool), 'expirationdate' (string), 'reason'
     *         (one of 'missing', 'invalid', 'wrongproduct', 'expired', 'valid')
     */
    public static function get_license_info(): array {
        $result = ['valid' => false, 'expirationdate' => '', 'reason' => 'missing'];

        $pluginconfig = get_config('local_shopping_cart');
        if (empty($pluginconfig->licensekey)) {
            return $result;
        }

        $decrypted = self::decryptlicensekey($pluginconfig->licensekey);
        if (empty($decrypted)) {
            $result['reason'] = 'invalid';
            return $result;
        }

        [$expirationdate, $product] = array_pad(explode(';', $decrypted, 2), 2, '');
        $result['expirationdate'] = $expirationdate;

        if ($product !== self::PRODUCT) {
            $result['reason'] = 'wrongproduct';
            return $result;
        }

        $expirationtimestamp = strtotime($expirationdate);
        if ($expirationtimestamp === false || time() >= $expirationtimestamp) {
            $result['reason'] = 'expired';
            return $result;
        }

        $result['valid'] = true;
        $result['reason'] = 'valid';
        return $result;
    }

    /**
     * Helper function to determine if a valid, unexpired "shoppingcartpro" license key is set.
     *
     * @return bool true if the PRO version is activated
     */
    public static function pro_version_is_activated(): bool {
        if (self::get_license_info()['valid']) {
            return true;
        }

        // Overriding - always use PRO for testing / debugging.
        if ((defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            return true;
        }

        return false;
    }
}
