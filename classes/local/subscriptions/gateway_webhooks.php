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

use core_payment\account;
use curl;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Webhook endpoints the cart holds at a payment provider, one per Moodle payment account.
 *
 * The gateway plugin keeps its own endpoint for checkout events; this one only carries the
 * subscription invoice events the cart needs to record recurring payments.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway_webhooks {
    /** @var string Gateway plugin name. */
    public const GATEWAY_STRIPE = 'stripe';

    /** @var string[] Stripe events the cart endpoint subscribes to. */
    public const STRIPE_EVENTS = ['invoice.paid', 'invoice.payment_failed'];

    /**
     * The stored endpoint for a payment account, or null.
     *
     * @param int $accountid
     * @param string $gateway
     * @return stdClass|null
     */
    public static function get_record(int $accountid, string $gateway = self::GATEWAY_STRIPE): ?stdClass {
        global $DB;
        $record = $DB->get_record('local_shopping_cart_gwwebhooks', ['accountid' => $accountid, 'gateway' => $gateway]);
        return $record ?: null;
    }

    /**
     * Store (or replace) the endpoint of a payment account.
     *
     * @param int $accountid
     * @param string $gateway
     * @param string $webhookid endpoint id at the provider, may be empty for manually created endpoints
     * @param string $secret signing secret
     * @return stdClass the stored record
     */
    public static function store(int $accountid, string $gateway, string $webhookid, string $secret): stdClass {
        global $DB;
        $now = time();
        $record = self::get_record($accountid, $gateway);
        if ($record) {
            $record->webhookid = $webhookid;
            $record->secret = $secret;
            $record->timemodified = $now;
            $DB->update_record('local_shopping_cart_gwwebhooks', $record);
            return $record;
        }
        $record = (object) [
            'accountid' => $accountid,
            'gateway' => $gateway,
            'webhookid' => $webhookid,
            'secret' => $secret,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_shopping_cart_gwwebhooks', $record);
        return $record;
    }

    /**
     * URL of the cart's endpoint for a payment account.
     *
     * @param int $accountid
     * @param string $gateway
     * @return string
     */
    public static function endpoint_url(int $accountid, string $gateway = self::GATEWAY_STRIPE): string {
        $url = new moodle_url('/local/shopping_cart/webhooks/' . $gateway . '.php', ['accountid' => $accountid]);
        return $url->out(false);
    }

    /**
     * The gateway configuration (api keys etc.) of a payment account.
     *
     * @param int $accountid
     * @param string $gateway
     * @return array
     * @throws moodle_exception when the account has no enabled gateway of that type
     */
    public static function get_gateway_config(int $accountid, string $gateway = self::GATEWAY_STRIPE): array {
        $account = new account($accountid);
        $gateways = $account->get_gateways();
        if (empty($gateways[$gateway]) || !$gateways[$gateway]->get('enabled')) {
            throw new moodle_exception('subscriptionrenewals:nogateway', 'local_shopping_cart', '', $accountid);
        }
        return $gateways[$gateway]->get_configuration();
    }

    /**
     * Register the cart's endpoint at Stripe for a payment account and store its signing secret.
     *
     * Idempotent per account: an existing record is returned unchanged.
     *
     * @param int $accountid
     * @return stdClass the stored record
     * @throws moodle_exception on an API error
     */
    public static function register_stripe(int $accountid): stdClass {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if ($existing = self::get_record($accountid, self::GATEWAY_STRIPE)) {
            return $existing;
        }
        $config = self::get_gateway_config($accountid, self::GATEWAY_STRIPE);
        if (empty($config['secretkey'])) {
            throw new moodle_exception('subscriptionrenewals:nogateway', 'local_shopping_cart', '', $accountid);
        }

        $params = ['url' => self::endpoint_url($accountid), 'description' => 'local_shopping_cart subscription renewals'];
        $query = http_build_query($params);
        foreach (self::STRIPE_EVENTS as $event) {
            $query .= '&' . rawurlencode('enabled_events[]') . '=' . rawurlencode($event);
        }

        $client = new curl();
        $client->setHeader([
            'Authorization: Bearer ' . $config['secretkey'],
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        $response = $client->post('https://api.stripe.com/v1/webhook_endpoints', $query);
        $data = json_decode((string) $response, true);
        if (empty($data['id']) || empty($data['secret'])) {
            $message = $data['error']['message'] ?? (string) $response;
            throw new moodle_exception('subscriptionrenewals:registerfailed', 'local_shopping_cart', '', $message);
        }
        return self::store($accountid, self::GATEWAY_STRIPE, (string) $data['id'], (string) $data['secret']);
    }
}
