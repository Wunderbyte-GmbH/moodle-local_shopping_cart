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

namespace local_shopping_cart\local\subscriptions;

/**
 * Verifies the signature Stripe puts on webhook deliveries.
 *
 * Implemented without the gateway's bundled SDK so the cart does not depend on paygw_stripe internals.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stripe_signature {
    /**
     * Verify a Stripe-Signature header over the exact raw payload.
     *
     * @param string $payload raw request body
     * @param string $sigheader value of the Stripe-Signature header ("t=...,v1=...")
     * @param string $secret endpoint signing secret
     * @param int $tolerance max allowed clock skew in seconds
     * @param int|null $now current time, injectable for tests
     * @return bool
     */
    public static function verify(
        string $payload,
        string $sigheader,
        string $secret,
        int $tolerance = 300,
        ?int $now = null
    ): bool {
        if ($secret === '' || $sigheader === '') {
            return false;
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigheader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$key, $value] = $kv;
            if ($key === 't') {
                $timestamp = (int) $value;
            } else if ($key === 'v1') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || empty($signatures)) {
            return false;
        }
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a valid header for a payload (used by tests and the CLI self-check).
     *
     * @param string $payload
     * @param string $secret
     * @param int|null $timestamp
     * @return string
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }
}
