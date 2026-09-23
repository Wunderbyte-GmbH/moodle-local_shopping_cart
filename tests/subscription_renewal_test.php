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

namespace local_shopping_cart;

use advanced_testcase;
use local_shopping_cart\event\subscription_payment_failed;
use local_shopping_cart\event\subscription_renewed;
use local_shopping_cart\local\subscriptions\gateway_webhooks;
use local_shopping_cart\local\subscriptions\renewal_handler;
use local_shopping_cart\local\subscriptions\renewal_invoice_builder;
use local_shopping_cart\local\subscriptions\stripe_invoice;
use local_shopping_cart\local\subscriptions\stripe_signature;
use local_shopping_cart\task\create_renewal_invoice_task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

/**
 * Recurring subscription payments recorded from the provider's invoice events.
 *
 * @package    local_shopping_cart
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_shopping_cart\local\subscriptions\renewal_handler
 * @covers     \local_shopping_cart\local\subscriptions\stripe_invoice
 * @covers     \local_shopping_cart\local\subscriptions\stripe_signature
 * @covers     \local_shopping_cart\local\subscriptions\renewal_invoice_builder
 * @covers     \local_shopping_cart\local\subscriptions\gateway_webhooks
 * @covers     \local_shopping_cart\task\create_renewal_invoice_task
 */
final class subscription_renewal_test extends advanced_testcase {
    /** @var int Cart identifier of the original purchase. */
    private int $identifier = 4711;

    /** @var int Payment account id used in the tests. */
    private int $accountid = 9;

    /**
     * A user with a paid purchase of two items under one identifier, as the checkout leaves it.
     *
     * @return \stdClass the user
     */
    private function seed_purchase(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        $now = time();
        foreach (
            [
                ['itemid' => 78, 'itemname' => 'Wizard AI - Monthly Subscription (S)', 'price' => 34.80, 'tax' => 5.80],
                ['itemid' => 80, 'itemname' => 'Wizard AI - Monthly Subscription (M)', 'price' => 58.80, 'tax' => 9.80],
            ] as $item
        ) {
            $DB->insert_record('local_shopping_cart_history', (object) [
                'userid' => $user->id,
                'itemid' => $item['itemid'],
                'itemname' => $item['itemname'],
                'price' => $item['price'],
                'tax' => $item['tax'],
                'taxpercentage' => 0.2,
                'taxcategory' => 'A',
                'taxcountrycode' => 'AT',
                'currency' => 'EUR',
                'componentname' => 'mod_booking',
                'area' => 'option',
                'identifier' => $this->identifier,
                'payment' => LOCAL_SHOPPING_CART_PAYMENT_METHOD_ONLINE,
                'paymentstatus' => LOCAL_SHOPPING_CART_PAYMENT_SUCCESS,
                'usermodified' => $user->id,
                'timecreated' => $now,
                'timemodified' => $now,
                'vatnumber' => '',
            ]);
        }
        return $user;
    }

    /**
     * A Stripe invoice.* event as the 2025 API delivers it (subscription under parent).
     *
     * @param string $type event type
     * @param string $invoiceid Stripe invoice id
     * @param string $billingreason
     * @param int $amountpaid cents
     * @param array $metadata subscription metadata
     * @return array
     */
    private function stripe_event(
        string $type,
        string $invoiceid,
        string $billingreason = 'subscription_cycle',
        int $amountpaid = 9360,
        array $metadata = []
    ): array {
        if (empty($metadata)) {
            $metadata = ['component' => 'local_shopping_cart', 'paymentarea' => 'main', 'itemid' => (string) $this->identifier];
        }
        return [
            'id' => 'evt_' . $invoiceid,
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $invoiceid,
                    'object' => 'invoice',
                    'billing_reason' => $billingreason,
                    'amount_paid' => $type === 'invoice.paid' ? $amountpaid : 0,
                    'amount_due' => $amountpaid,
                    'currency' => 'eur',
                    'customer' => 'cus_1',
                    'parent' => [
                        'type' => 'subscription_details',
                        'subscription_details' => ['subscription' => 'sub_1', 'metadata' => $metadata],
                    ],
                    'lines' => ['data' => [
                        ['period' => ['start' => 1760000000, 'end' => 1762678400]],
                    ]],
                ],
            ],
        ];
    }

    /**
     * Signatures: a valid one passes, a wrong secret and an old timestamp do not.
     */
    public function test_signature(): void {
        $payload = '{"id":"evt_1"}';
        $header = stripe_signature::sign($payload, 'whsec_a', 1700000000);
        $this->assertTrue(stripe_signature::verify($payload, $header, 'whsec_a', 300, 1700000100));
        $this->assertFalse(stripe_signature::verify($payload, $header, 'whsec_b', 300, 1700000100));
        $this->assertFalse(stripe_signature::verify($payload, $header, 'whsec_a', 300, 1700001000));
        $this->assertFalse(stripe_signature::verify($payload . ' ', $header, 'whsec_a', 300, 1700000100));
        $this->assertFalse(stripe_signature::verify($payload, '', 'whsec_a'));
    }

    /**
     * Both invoice shapes (classic and 2025 parent) yield the same subscription, metadata and period.
     */
    public function test_invoice_parsing_both_shapes(): void {
        $modern = $this->stripe_event('invoice.paid', 'in_1')['data']['object'];
        $classic = $modern;
        unset($classic['parent']);
        $classic['subscription'] = 'sub_1';
        $classic['subscription_details'] = ['metadata' => $modern['parent']['subscription_details']['metadata']];

        foreach ([$modern, $classic] as $shape) {
            $invoice = stripe_invoice::from_event_object($shape);
            $this->assertSame('in_1', $invoice->id);
            $this->assertSame('sub_1', $invoice->subscriptionid);
            $this->assertSame((string) $this->identifier, $invoice->metadata['itemid']);
            $this->assertSame(1760000000, $invoice->periodstart);
            $this->assertSame(1762678400, $invoice->periodend);
            $this->assertSame(93.60, $invoice->gross_paid());
            $this->assertFalse($invoice->is_initial());
        }
        $this->assertSame(500.0, stripe_invoice::to_decimal(500, 'jpy'));
    }

    /**
     * The collected amount is split proportionally with the items' tax rates; shares add up exactly.
     */
    public function test_split_amount(): void {
        $items = [['price' => 34.80, 'taxpercentage' => 0.2], ['price' => 58.80, 'taxpercentage' => 0.2]];
        $shares = renewal_handler::split_amount($items, 93.60);
        $this->assertEquals([['price' => 34.80, 'tax' => 5.80], ['price' => 58.80, 'tax' => 9.80]], $shares);

        // A discounted renewal keeps the proportions and the rounding residue lands on the last item.
        $shares = renewal_handler::split_amount($items, 50.00);
        $this->assertEqualsWithDelta(50.00, $shares[0]['price'] + $shares[1]['price'], 0.0001);
        $this->assertEqualsWithDelta(18.59, $shares[0]['price'], 0.0001);

        $this->assertSame([], renewal_handler::split_amount([], 10.0));
        $shares = renewal_handler::split_amount([['price' => 0, 'taxpercentage' => 0]], 10.0);
        $this->assertEquals([['price' => 10.0, 'tax' => 0.0]], $shares);
    }

    /**
     * Nothing is recorded while the feature is off.
     */
    public function test_disabled_feature_ignores_events(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_purchase();
        set_config('enablesubscriptionrenewals', 0, 'local_shopping_cart');

        $handler = new renewal_handler($this->accountid);
        $this->assertFalse($handler->handle_event($this->stripe_event('invoice.paid', 'in_1')));
        $this->assertSame(0, $DB->count_records('local_shopping_cart_renewals'));
    }

    /**
     * The first payment of a subscription is the checkout's business and is skipped.
     */
    public function test_initial_payment_is_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_purchase();
        set_config('enablesubscriptionrenewals', 1, 'local_shopping_cart');

        $handler = new renewal_handler($this->accountid);
        $this->assertFalse($handler->handle_event($this->stripe_event('invoice.paid', 'in_0', 'subscription_create')));
        $this->assertSame(0, $DB->count_records('local_shopping_cart_renewals'));
        $this->assertSame(0, $DB->count_records('local_shopping_cart_ledger'));
    }

    /**
     * A paid renewal becomes one ledger row per item, a renewal row, an event and (with ERPNext) a task.
     */
    public function test_paid_renewal_is_recorded_once(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->seed_purchase();
        set_config('enablesubscriptionrenewals', 1, 'local_shopping_cart');
        set_config('invoicingplatform', 'erpnext', 'local_shopping_cart');

        $sink = $this->redirectEvents();
        $handler = new renewal_handler($this->accountid);
        $this->assertTrue($handler->handle_event($this->stripe_event('invoice.paid', 'in_1')));

        $ledger = array_values($DB->get_records('local_shopping_cart_ledger', [], 'id ASC'));
        $this->assertCount(2, $ledger);
        $this->assertSame(34.80, (float) $ledger[0]->price);
        $this->assertSame(5.80, (float) $ledger[0]->tax);
        $this->assertSame((int) $ledger[0]->payment, LOCAL_SHOPPING_CART_PAYMENT_METHOD_SUBSCRIPTION_RENEWAL);
        $this->assertSame(LOCAL_SHOPPING_CART_PAYMENT_SUCCESS, (int) $ledger[0]->paymentstatus);
        $this->assertSame($this->identifier, (int) $ledger[0]->identifier);
        $this->assertSame('in_1', $ledger[0]->annotation);
        $this->assertSame($this->accountid, (int) $ledger[0]->accountid);
        $this->assertSame('EUR', $ledger[1]->currency);

        $renewal = $DB->get_record('local_shopping_cart_renewals', ['externalinvoiceid' => 'in_1']);
        $this->assertNotFalse($renewal);
        $this->assertSame((int) $user->id, (int) $renewal->userid);
        $this->assertSame(renewal_handler::STATUS_PAID, $renewal->status);
        $this->assertSame(93.60, (float) $renewal->amount);
        $this->assertSame(1762678400, (int) $renewal->periodend);
        $this->assertSame(renewal_handler::INVOICE_PENDING, $renewal->invoicestatus);

        $events = array_filter($sink->get_events(), static fn($e) => $e instanceof subscription_renewed);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame($this->identifier, (int) $event->other['identifier']);
        $this->assertSame((int) $user->id, (int) $event->relateduserid);

        $tasks = \core\task\manager::get_adhoc_tasks(create_renewal_invoice_task::class);
        $this->assertCount(1, $tasks);
        $this->assertSame((int) $renewal->id, (int) reset($tasks)->get_custom_data()->renewalid);

        // Stripe redelivers: acknowledged, but nothing is written twice.
        $this->assertTrue($handler->handle_event($this->stripe_event('invoice.paid', 'in_1')));
        $this->assertSame(2, $DB->count_records('local_shopping_cart_ledger'));
        $this->assertSame(1, $DB->count_records('local_shopping_cart_renewals'));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(create_renewal_invoice_task::class));

        // A second month is a new invoice and is recorded on its own.
        $this->assertTrue($handler->handle_event($this->stripe_event('invoice.paid', 'in_2')));
        $this->assertSame(4, $DB->count_records('local_shopping_cart_ledger'));
        $this->assertSame(2, $DB->count_records('local_shopping_cart_renewals'));
    }

    /**
     * Without an invoicing platform no task is queued and the invoice status stays empty.
     */
    public function test_paid_renewal_without_invoicing_platform(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_purchase();
        set_config('enablesubscriptionrenewals', 1, 'local_shopping_cart');
        set_config('invoicingplatform', 'noinvoice', 'local_shopping_cart');

        $handler = new renewal_handler($this->accountid);
        $this->assertTrue($handler->handle_event($this->stripe_event('invoice.paid', 'in_1')));
        $renewal = $DB->get_record('local_shopping_cart_renewals', ['externalinvoiceid' => 'in_1']);
        $this->assertNull($renewal->invoicestatus);
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(create_renewal_invoice_task::class));
    }

    /**
     * A failed collection is recorded and announced, but never touches the ledger or the invoice queue.
     */
    public function test_failed_payment_is_recorded_without_ledger(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_purchase();
        set_config('enablesubscriptionrenewals', 1, 'local_shopping_cart');
        set_config('invoicingplatform', 'erpnext', 'local_shopping_cart');

        $sink = $this->redirectEvents();
        $handler = new renewal_handler($this->accountid);
        $this->assertTrue($handler->handle_event($this->stripe_event('invoice.payment_failed', 'in_3')));

        $this->assertSame(0, $DB->count_records('local_shopping_cart_ledger'));
        $renewal = $DB->get_record('local_shopping_cart_renewals', ['externalinvoiceid' => 'in_3']);
        $this->assertSame(renewal_handler::STATUS_FAILED, $renewal->status);
        $this->assertNull($renewal->invoicestatus);
        $this->assertCount(0, \core\task\manager::get_adhoc_tasks(create_renewal_invoice_task::class));
        $events = array_filter($sink->get_events(), static fn($e) => $e instanceof subscription_payment_failed);
        $this->assertCount(1, $events);
    }

    /**
     * Invoices that belong to another component, an unknown purchase or no subscription are not ours.
     */
    public function test_foreign_or_unknown_invoices_are_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_purchase();
        set_config('enablesubscriptionrenewals', 1, 'local_shopping_cart');
        $handler = new renewal_handler($this->accountid);

        $foreignmeta = ['component' => 'enrol_fee', 'itemid' => '3'];
        $foreign = $this->stripe_event('invoice.paid', 'in_4', 'subscription_cycle', 1000, $foreignmeta);
        $this->assertFalse($handler->handle_event($foreign));

        $unknownmeta = ['component' => 'local_shopping_cart', 'itemid' => '999999'];
        $unknown = $this->stripe_event('invoice.paid', 'in_5', 'subscription_cycle', 1000, $unknownmeta);
        $this->assertFalse($handler->handle_event($unknown));

        $oneoff = $this->stripe_event('invoice.paid', 'in_6');
        unset($oneoff['data']['object']['parent']);
        $this->assertFalse($handler->handle_event($oneoff));

        $this->assertFalse($handler->handle_event(['type' => 'customer.subscription.updated', 'data' => ['object' => []]]));
        $this->assertSame(0, $DB->count_records('local_shopping_cart_renewals'));
    }

    /**
     * Without metadata the gateway's own tables map the subscription to the purchase.
     */
    public function test_identifier_from_gateway_tables(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('paygw_stripe_subscriptions') || !$dbman->table_exists('paygw_stripe_products')) {
            $this->markTestSkipped('paygw_stripe is not installed.');
        }
        $user = $this->seed_purchase();
        $DB->insert_record('paygw_stripe_products', (object) [
            'productid' => 'prod_1', 'component' => 'local_shopping_cart', 'paymentarea' => 'main', 'itemid' => $this->identifier,
        ]);
        $DB->insert_record('paygw_stripe_subscriptions', (object) [
            'userid' => $user->id, 'subscriptionid' => 'sub_1', 'customerid' => 'cus_1', 'status' => 'active',
            'productid' => 'prod_1', 'priceid' => 'price_1',
        ]);

        $event = $this->stripe_event('invoice.paid', 'in_7', 'subscription_cycle', 9360, ['unrelated' => '1']);
        $invoice = stripe_invoice::from_event_object($event['data']['object']);
        $this->assertSame($this->identifier, renewal_handler::resolve_identifier($invoice));
    }

    /**
     * The invoice DTO carries net prices, the provider's invoice id as reference and the billed period.
     */
    public function test_invoice_builder(): void {
        $renewal = (object) [
            'externalinvoiceid' => 'in_1', 'currency' => 'EUR', 'amount' => 93.60,
            'periodstart' => 1760000000, 'periodend' => 1762678400, 'timecreated' => 1760000001,
        ];
        $rows = [
            (object) ['itemname' => 'Wizard AI - Monthly Subscription (S)', 'price' => 34.80, 'tax' => 5.80, 'vatnumber' => ''],
            (object) ['itemname' => 'Wizard AI - Monthly Subscription (M)', 'price' => 58.80, 'tax' => 9.80, 'vatnumber' => 'ATU1'],
        ];
        $user = (object) ['id' => 5, 'email' => 'ada@example.com', 'firstname' => 'Ada', 'lastname' => 'Lovelace'];
        $billing = (object) ['company' => '', 'name' => 'Ada Lovelace', 'state' => 'AT', 'address' => 'Ring 1', 'city' => 'Wien',
            'zip' => '1010', 'id' => 3];

        $dto = renewal_invoice_builder::build($renewal, $rows, $user, $billing);
        $this->assertSame('in_1', $dto->reference);
        $this->assertSame('EUR', $dto->currency);
        $this->assertSame(93.60, $dto->grosscheck);
        $this->assertTrue($dto->markpaid);
        $this->assertSame('ATU1', $dto->vatnumber);
        $this->assertNull($dto->taxtemplate);
        $this->assertCount(2, $dto->items);
        $this->assertSame(29.00, $dto->items[0]->net);
        $this->assertSame(49.00, $dto->items[1]->net);
        $this->assertSame(1760000000, $dto->items[0]->serviceperiodstart);
        $this->assertSame(1762678400, $dto->items[0]->serviceperiodend);
    }

    /**
     * Endpoint secrets are stored per payment account and replaced on re-registration.
     */
    public function test_webhook_registry(): void {
        $this->resetAfterTest();
        $this->assertNull(gateway_webhooks::get_record(5));
        gateway_webhooks::store(5, 'stripe', 'we_1', 'whsec_1');
        $this->assertSame('whsec_1', gateway_webhooks::get_record(5)->secret);
        gateway_webhooks::store(5, 'stripe', '', 'whsec_2');
        $this->assertSame('whsec_2', gateway_webhooks::get_record(5)->secret);
        $this->assertStringContainsString('webhooks/stripe.php?accountid=5', gateway_webhooks::endpoint_url(5));
    }

    /**
     * A renewal without ledger rows is a permanent problem: the task marks it failed and does not retry.
     */
    public function test_invoice_task_marks_permanent_failure(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->seed_purchase();
        set_config('invoicingplatform', 'erpnext', 'local_shopping_cart');
        $id = $DB->insert_record('local_shopping_cart_renewals', (object) [
            'identifier' => $this->identifier, 'userid' => $user->id, 'accountid' => $this->accountid, 'gateway' => 'stripe',
            'subscriptionid' => 'sub_1', 'externalinvoiceid' => 'in_9', 'amount' => 93.60, 'currency' => 'EUR',
            'periodstart' => 0, 'periodend' => 0, 'status' => renewal_handler::STATUS_PAID,
            'invoicestatus' => renewal_handler::INVOICE_PENDING, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $task = new create_renewal_invoice_task();
        $task->set_custom_data((object) ['renewalid' => $id]);
        $this->expectOutputRegex('/no ledger rows/');
        $task->execute();

        $status = $DB->get_field('local_shopping_cart_renewals', 'invoicestatus', ['id' => $id]);
        $this->assertSame(renewal_handler::INVOICE_FAILED, $status);
    }
}
