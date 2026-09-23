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

use context_system;
use local_shopping_cart\event\subscription_payment_failed;
use local_shopping_cart\event\subscription_renewed;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use local_shopping_cart\task\create_renewal_invoice_task;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../lib.php');

/**
 * Records recurring subscription payments the payment provider collected after the initial checkout.
 *
 * A paid renewal becomes one ledger entry per item of the original purchase (so cash reports see it),
 * one row in local_shopping_cart_renewals (idempotency and invoice state), an event, and - when an
 * invoicing platform is configured - an ad-hoc task that issues the invoice there.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renewal_handler {
    /** @var string Renewal status: the provider collected the payment. */
    public const STATUS_PAID = 'paid';

    /** @var string Renewal status: the provider could not collect the payment. */
    public const STATUS_FAILED = 'failed';

    /** @var string Invoice status: waiting for the invoicing task. */
    public const INVOICE_PENDING = 'pending';

    /** @var string Invoice status: invoice exists at the invoicing platform. */
    public const INVOICE_CREATED = 'created';

    /** @var string Invoice status: amounts did not reconcile, needs a human. */
    public const INVOICE_HELD = 'held';

    /** @var string Invoice status: permanent configuration problem (e.g. unknown item), needs a human. */
    public const INVOICE_FAILED = 'failed';

    /** @var int Moodle payment account the webhook belongs to. */
    private int $accountid;

    /** @var string Gateway plugin name. */
    private string $gateway;

    /**
     * Constructor.
     *
     * @param int $accountid Moodle payment account the webhook belongs to
     * @param string $gateway gateway plugin name
     */
    public function __construct(int $accountid, string $gateway = gateway_webhooks::GATEWAY_STRIPE) {
        $this->accountid = $accountid;
        $this->gateway = $gateway;
    }

    /**
     * Dispatch a decoded webhook event.
     *
     * @param array $event decoded JSON event
     * @return bool true when the event was recorded (or already was), false when it is not ours
     */
    public function handle_event(array $event): bool {
        if (empty(get_config('local_shopping_cart', 'enablesubscriptionrenewals'))) {
            return false;
        }
        $type = (string) ($event['type'] ?? '');
        if (!in_array($type, ['invoice.paid', 'invoice.payment_failed'], true)) {
            return false;
        }
        $object = $event['data']['object'] ?? null;
        if (!is_array($object)) {
            return false;
        }
        $invoice = stripe_invoice::from_event_object($object);
        if ($invoice->id === '' || $invoice->subscriptionid === '') {
            return false;
        }
        // The first payment of a subscription is delivered and invoiced by the checkout itself.
        if ($invoice->is_initial()) {
            return false;
        }
        $identifier = self::resolve_identifier($invoice, $this->gateway);
        if ($identifier <= 0) {
            return false;
        }
        if (self::get_renewal($this->gateway, $invoice->id)) {
            return true; // Already recorded: a redelivery.
        }
        $items = self::paid_items_of($identifier);
        if (empty($items)) {
            return false;
        }

        $status = $type === 'invoice.paid' ? self::STATUS_PAID : self::STATUS_FAILED;
        return $this->record($invoice, $identifier, $items, $status);
    }

    /**
     * Map a provider invoice to the cart identifier of the original purchase.
     *
     * Metadata first (the gateway attaches component/itemid/userid to the subscription), then the
     * gateway's own tables (subscription -> product -> itemid), which hold the same identifier.
     *
     * @param stripe_invoice $invoice
     * @param string $gateway
     * @return int the identifier, 0 when unknown
     */
    public static function resolve_identifier(stripe_invoice $invoice, string $gateway = gateway_webhooks::GATEWAY_STRIPE): int {
        global $DB;

        $meta = $invoice->metadata;
        $component = (string) ($meta['component'] ?? '');
        if (($component === '' || $component === 'local_shopping_cart') && !empty($meta['itemid'])) {
            return (int) $meta['itemid'];
        }
        if ($component !== '' && $component !== 'local_shopping_cart') {
            return 0;
        }

        if ($gateway !== gateway_webhooks::GATEWAY_STRIPE) {
            return 0;
        }
        $dbman = $DB->get_manager();
        if (
            !$dbman->table_exists('paygw_stripe_subscriptions')
            || !$dbman->table_exists('paygw_stripe_products')
        ) {
            return 0;
        }
        $sql = "SELECT p.itemid
                  FROM {paygw_stripe_subscriptions} s
                  JOIN {paygw_stripe_products} p ON p.productid = s.productid
                 WHERE s.subscriptionid = :subscriptionid
                   AND p.component = :component";
        $itemid = $DB->get_field_sql($sql, ['subscriptionid' => $invoice->subscriptionid, 'component' => 'local_shopping_cart']);
        return (int) $itemid;
    }

    /**
     * The successfully paid history rows of a purchase.
     *
     * @param int $identifier
     * @return stdClass[]
     */
    public static function paid_items_of(int $identifier): array {
        $rows = shopping_cart_history::return_data_via_identifier($identifier);
        return array_values(array_filter($rows, static function ($row) {
            return (int) $row->paymentstatus === LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
        }));
    }

    /**
     * A recorded renewal by provider invoice id.
     *
     * @param string $gateway
     * @param string $externalinvoiceid
     * @return stdClass|null
     */
    public static function get_renewal(string $gateway, string $externalinvoiceid): ?stdClass {
        global $DB;
        $record = $DB->get_record(
            'local_shopping_cart_renewals',
            ['gateway' => $gateway, 'externalinvoiceid' => $externalinvoiceid]
        );
        return $record ?: null;
    }

    /**
     * Distribute the collected gross amount over the original items, keeping each item's tax rate.
     *
     * The provider may collect less or more than the original checkout (coupons, proration). Each
     * item gets its proportional share; rounding residue goes to the last item so the shares add up
     * to the collected amount exactly.
     *
     * @param array $items list of ['price' => original gross, 'taxpercentage' => rate as fraction (0.2)]
     * @param float $gross amount collected
     * @return array list of ['price' => gross share, 'tax' => tax share], same order as $items
     */
    public static function split_amount(array $items, float $gross): array {
        $count = count($items);
        if ($count === 0) {
            return [];
        }
        $originaltotal = 0.0;
        foreach ($items as $item) {
            $originaltotal += (float) ($item['price'] ?? 0);
        }
        $result = [];
        $assigned = 0.0;
        foreach (array_values($items) as $index => $item) {
            $share = $originaltotal > 0
                ? round($gross * ((float) ($item['price'] ?? 0)) / $originaltotal, 2)
                : round($gross / $count, 2);
            if ($index === $count - 1) {
                $share = round($gross - $assigned, 2);
            }
            $assigned += $share;
            $rate = (float) ($item['taxpercentage'] ?? 0);
            $tax = $rate > 0 ? round($share - $share / (1 + $rate), 2) : 0.0;
            $result[] = ['price' => $share, 'tax' => $tax];
        }
        return $result;
    }

    /**
     * Write ledger rows, the renewal record, the event and (for paid renewals) queue the invoice.
     *
     * @param stripe_invoice $invoice
     * @param int $identifier
     * @param stdClass[] $items paid history rows of the original purchase
     * @param string $status STATUS_PAID or STATUS_FAILED
     * @return bool
     */
    private function record(stripe_invoice $invoice, int $identifier, array $items, string $status): bool {
        global $DB;

        $now = time();
        $userid = (int) reset($items)->userid;
        $gross = $invoice->gross_paid();
        $currency = strtoupper($invoice->currency);

        $transaction = $DB->start_delegated_transaction();

        if ($status === self::STATUS_PAID) {
            $shares = self::split_amount(array_map(static function ($row) {
                return ['price' => (float) $row->price, 'taxpercentage' => (float) ($row->taxpercentage ?? 0)];
            }, $items), $gross);

            foreach ($items as $index => $row) {
                $ledger = new stdClass();
                $ledger->userid = (int) $row->userid;
                $ledger->itemid = (int) $row->itemid;
                $ledger->itemname = $row->itemname;
                $ledger->price = $shares[$index]['price'];
                $ledger->tax = $shares[$index]['tax'];
                $ledger->taxpercentage = $row->taxpercentage ?? null;
                $ledger->taxcategory = $row->taxcategory ?? null;
                $ledger->taxcountrycode = $row->taxcountrycode ?? null;
                $ledger->discount = 0;
                $ledger->credits = 0;
                $ledger->fee = 0;
                $ledger->currency = $currency !== '' ? $currency : $row->currency;
                $ledger->componentname = $row->componentname;
                $ledger->costcenter = $row->costcenter ?? null;
                $ledger->identifier = $identifier;
                $ledger->payment = LOCAL_SHOPPING_CART_PAYMENT_METHOD_SUBSCRIPTION_RENEWAL;
                $ledger->paymentstatus = LOCAL_SHOPPING_CART_PAYMENT_SUCCESS;
                $ledger->accountid = $this->accountid;
                $ledger->usermodified = (int) $row->userid;
                $ledger->timecreated = $now;
                $ledger->timemodified = $now;
                $ledger->area = $row->area;
                // The provider's invoice id ties the ledger row to the renewal (and keeps redeliveries apart).
                $ledger->annotation = $invoice->id;
                $ledger->schistoryid = (int) $row->id;
                $ledger->address_billing = $row->address_billing ?? null;
                $ledger->address_shipping = $row->address_shipping ?? null;
                $ledger->vatnumber = $row->vatnumber ?? null;
                $ledger->nritems = $row->nritems ?? 1;
                $ledger->multipliable = $row->multipliable ?? 0;
                shopping_cart::add_record_to_ledger_table($ledger);
            }
        }

        $renewal = (object) [
            'identifier' => $identifier,
            'userid' => $userid,
            'accountid' => $this->accountid,
            'gateway' => $this->gateway,
            'subscriptionid' => $invoice->subscriptionid,
            'externalinvoiceid' => $invoice->id,
            'amount' => $gross,
            'currency' => $currency,
            'periodstart' => $invoice->periodstart,
            'periodend' => $invoice->periodend,
            'status' => $status,
            'invoicestatus' => null,
            'erpinvoiceid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $renewal->id = $DB->insert_record('local_shopping_cart_renewals', $renewal);

        $transaction->allow_commit();

        $eventdata = [
            'context' => context_system::instance(),
            'userid' => $userid,
            'relateduserid' => $userid,
            'other' => [
                'renewalid' => $renewal->id,
                'identifier' => $identifier,
                'subscriptionid' => $invoice->subscriptionid,
                'externalinvoiceid' => $invoice->id,
                'amount' => $gross,
                'currency' => $currency,
                'periodstart' => $invoice->periodstart,
                'periodend' => $invoice->periodend,
            ],
        ];
        if ($status === self::STATUS_PAID) {
            subscription_renewed::create($eventdata)->trigger();
            create_renewal_invoice_task::queue($renewal);
        } else {
            subscription_payment_failed::create($eventdata)->trigger();
        }
        return true;
    }
}
