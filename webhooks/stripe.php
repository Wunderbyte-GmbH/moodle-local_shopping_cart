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
 * Stripe webhook endpoint of the cart for subscription invoice events (invoice.paid, invoice.payment_failed).
 *
 * One endpoint per Moodle payment account; the account id selects the signing secret. The gateway
 * plugin's own endpoint is untouched, it keeps handling checkout and subscription status events.
 *
 * Responses: 200 recorded, 202 ignored (not ours, disabled, or initial payment), 400 bad payload or
 * signature, 500 processing error (Stripe retries).
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing -- Server-to-server call, authenticated by signature.

define('NO_MOODLE_COOKIES', true);

use local_shopping_cart\local\subscriptions\gateway_webhooks;
use local_shopping_cart\local\subscriptions\renewal_handler;
use local_shopping_cart\local\subscriptions\stripe_signature;

require(__DIR__ . '/../../../config.php');

$accountid = required_param('accountid', PARAM_INT);

if (empty(get_config('local_shopping_cart', 'enablesubscriptionrenewals'))) {
    http_response_code(202);
    exit;
}

$record = gateway_webhooks::get_record($accountid, gateway_webhooks::GATEWAY_STRIPE);
if (!$record) {
    http_response_code(400);
    exit;
}

$payload = (string) file_get_contents('php://input');
$sigheader = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if (!stripe_signature::verify($payload, $sigheader, (string) $record->secret)) {
    http_response_code(400);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    exit;
}

try {
    $handler = new renewal_handler($accountid, gateway_webhooks::GATEWAY_STRIPE);
    http_response_code($handler->handle_event($event) ? 200 : 202);
} catch (Throwable $e) {
    debugging('Stripe renewal webhook failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
}
