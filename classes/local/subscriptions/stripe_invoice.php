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
 * The parts of a Stripe invoice object the cart needs, read from the decoded webhook payload.
 *
 * Pure value object: no HTTP, no database. It accepts both the classic invoice shape
 * (subscription and subscription_details on the invoice) and the shape of API versions from 2025 on
 * (subscription under parent.subscription_details).
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stripe_invoice {
    /** @var string Stripe invoice id (in_...). */
    public string $id = '';

    /** @var string Stripe subscription id (sub_...), empty for one-off invoices. */
    public string $subscriptionid = '';

    /** @var string Stripe billing reason, e.g. subscription_create, subscription_cycle. */
    public string $billingreason = '';

    /** @var array Metadata attached to the subscription (userid, itemid, component, ...). */
    public array $metadata = [];

    /** @var int Amount paid in the smallest currency unit. */
    public int $amountpaid = 0;

    /** @var int Amount due in the smallest currency unit. */
    public int $amountdue = 0;

    /** @var string Lower-case ISO currency code. */
    public string $currency = '';

    /** @var int Start of the billed period (unix). */
    public int $periodstart = 0;

    /** @var int End of the billed period (unix). */
    public int $periodend = 0;

    /** @var string Stripe customer id. */
    public string $customerid = '';

    /**
     * Read an invoice object from a decoded webhook payload.
     *
     * @param array $invoice event.data.object of an invoice.* event
     * @return self
     */
    public static function from_event_object(array $invoice): self {
        $self = new self();
        $self->id = (string) ($invoice['id'] ?? '');
        $self->billingreason = (string) ($invoice['billing_reason'] ?? '');
        $self->amountpaid = (int) ($invoice['amount_paid'] ?? 0);
        $self->amountdue = (int) ($invoice['amount_due'] ?? 0);
        $self->currency = strtolower((string) ($invoice['currency'] ?? ''));
        $self->customerid = self::id_of($invoice['customer'] ?? '');

        $parentdetails = $invoice['parent']['subscription_details'] ?? [];
        $self->subscriptionid = self::id_of($invoice['subscription'] ?? ($parentdetails['subscription'] ?? ''));

        $metadata = $invoice['subscription_details']['metadata']
            ?? ($parentdetails['metadata'] ?? ($invoice['metadata'] ?? []));
        $self->metadata = is_array($metadata) ? $metadata : [];

        // The billed period is the span over all lines; fall back to the invoice's own period.
        $start = null;
        $end = null;
        foreach ($invoice['lines']['data'] ?? [] as $line) {
            if (!isset($line['period']['start'], $line['period']['end'])) {
                continue;
            }
            $start = $start === null ? (int) $line['period']['start'] : min($start, (int) $line['period']['start']);
            $end = $end === null ? (int) $line['period']['end'] : max($end, (int) $line['period']['end']);
        }
        $self->periodstart = $start ?? (int) ($invoice['period_start'] ?? 0);
        $self->periodend = $end ?? (int) ($invoice['period_end'] ?? 0);

        return $self;
    }

    /**
     * Stripe sends related objects either as id string or as expanded object.
     *
     * @param mixed $value
     * @return string
     */
    private static function id_of($value): string {
        if (is_array($value)) {
            return (string) ($value['id'] ?? '');
        }
        return (string) ($value ?? '');
    }

    /**
     * Whether this invoice belongs to the first payment of a subscription, which the checkout already handled.
     *
     * @return bool
     */
    public function is_initial(): bool {
        return $this->billingreason === 'subscription_create';
    }

    /**
     * Gross amount paid as a decimal (cents to units; zero-decimal currencies are passed through).
     *
     * @return float
     */
    public function gross_paid(): float {
        return self::to_decimal($this->amountpaid, $this->currency);
    }

    /**
     * Convert a Stripe amount to a decimal, honouring zero-decimal currencies.
     *
     * @param int $amount
     * @param string $currency
     * @return float
     */
    public static function to_decimal(int $amount, string $currency): float {
        $zerodecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf',
            'xof', 'xpf'];
        if (in_array(strtolower($currency), $zerodecimal, true)) {
            return (float) $amount;
        }
        return round($amount / 100, 2);
    }
}
