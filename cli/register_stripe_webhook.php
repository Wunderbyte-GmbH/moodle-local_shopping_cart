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
 * Registers the cart's Stripe webhook endpoint for subscription renewals of one payment account.
 *
 * Without --secret the endpoint is created through the Stripe API with the account's secret key and
 * the signing secret is stored. With --secret the endpoint was created in the Stripe dashboard by
 * hand and only its signing secret is stored.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_shopping_cart\local\subscriptions\gateway_webhooks;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'accountid' => 0,
        'secret' => '',
        'webhookid' => '',
        'show' => false,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['accountid'])) {
    echo "Register the shopping cart's Stripe webhook endpoint for subscription renewals.

Options:
  --accountid=N     Moodle payment account id (required).
  --secret=whsec_.. Store the signing secret of an endpoint created by hand instead of
                    creating one through the Stripe API.
  --webhookid=we_.. Optional endpoint id to store together with --secret.
  --show            Only print the endpoint URL and whether a secret is stored.
  -h, --help        Print this help.

Example:
  php local/shopping_cart/cli/register_stripe_webhook.php --accountid=5
";
    exit(0);
}

$accountid = (int) $options['accountid'];
$url = gateway_webhooks::endpoint_url($accountid);

if ($options['show']) {
    $record = gateway_webhooks::get_record($accountid);
    cli_writeln('Endpoint URL: ' . $url);
    cli_writeln('Events:       ' . implode(', ', gateway_webhooks::STRIPE_EVENTS));
    cli_writeln('Secret stored: ' . ($record ? 'yes (webhook id ' . ($record->webhookid ?: '-') . ')' : 'no'));
    exit(0);
}

if ($options['secret'] !== '') {
    $record = gateway_webhooks::store(
        $accountid,
        gateway_webhooks::GATEWAY_STRIPE,
        (string) $options['webhookid'],
        (string) $options['secret']
    );
    cli_writeln('Stored signing secret for account ' . $accountid . '. Endpoint URL: ' . $url);
    exit(0);
}

try {
    $record = gateway_webhooks::register_stripe($accountid);
} catch (moodle_exception $e) {
    cli_error($e->getMessage());
}
cli_writeln('Endpoint registered at Stripe: ' . ($record->webhookid ?: '-'));
cli_writeln('Endpoint URL: ' . $url);
cli_writeln('Events:       ' . implode(', ', gateway_webhooks::STRIPE_EVENTS));
exit(0);
