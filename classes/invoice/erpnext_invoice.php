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
 * Creat invoices with ERPNext using this class
 *
 * @package local_shopping_cart
 * @author David Bogner
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_shopping_cart\invoice;

use core\event\base;
use core\task\manager;
use core_user;
use curl;
use local_shopping_cart\interfaces\invoice;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\local\checkout_process\items_helper\address_operations;
use local_shopping_cart\local\vatnrchecker;
use local_shopping_cart\shopping_cart_history;
use local_shopping_cart\task\create_invoice_task;
use stdClass;

/**
 * Class erpnext_invoice. This class allows to create invoices on a remote instance of the Open Source ERP solution ERPNext.
 *
 * @author David Bogner
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class erpnext_invoice implements invoice {
    /**
     * @var string
     */
    private string $baseurl;
    /**
     * @var string
     */
    private string $token;
    /**
     * @var array|string[]
     */
    private array $headers;
    /**
     * @var curl curl wrapper
     */
    private curl $client;
    /**
     * @var stdClass
     */
    private stdClass $user;
    /**
     * @var string json
     */
    private string $jsoninvoice;
    /**
     * @var string json
     */
    public string $errormessage = '';
    /**
     * @var string customer name
     */
    private string $customername;
    /**
     * @var int address id from shopping_cart_address
     */
    private int $addressid = 0;
    /**
     * @var string customer company name
     */
    private string $customercompany = '';
    /**
     * @var array items on the invoice
     */
    private array $invoiceitems;
    /**
     * @var array Data structure of the invoice as array that can be json encoded.
     */
    private array $invoicedata = [];

    /**
     * @var array Payment entry from ERPNext.
     */
    private array $paymententry = [];

    /**
     * @var string Billing address name as used in ERP.
     */
    private string $billingaddressname = '';

    /**
     * Contents of the object:
     * id
     * userid
     * name
     * state
     * address
     * address2
     * city
     * zip
     * phone
     * company
     *
     * @var stdClass Address record from DB.
     */
    private stdClass $billingaddress;

    /**
     * @var int shopping cart history identifier
     */
    private int $identifier;

    /**
     * @var bool True when the invoice data is injected by a non-cart caller instead of read from cart tables.
     */
    private bool $injected = false;

    /**
     * @var string|null Injected billing country ISO2 code (overrides billingaddress->state for tax logic).
     */
    private ?string $taxcountrycode = null;

    /**
     * @var bool Injected has-VAT-id flag (used instead of the cart ledger when injected).
     */
    private bool $injectedhasvatid = false;

    /**
     * @var string|null Transaction currency; null keeps the ERPNext/company default (EUR).
     */
    private ?string $currency = null;

    /**
     * @var float|null Exchange rate from the transaction currency to the company currency.
     */
    private ?float $conversionrate = null;

    /**
     * @var string External idempotency reference (e.g. a Stripe order id); stored as ERPNext po_no.
     */
    private string $reference = '';

    /**
     * @var string|null Explicit tax template name to use (overrides auto-selection when set).
     */
    private ?string $forcedtaxtemplate = null;

    /**
     * @var string Name of the created ERPNext Sales Invoice (set after creation).
     */
    public string $invoiceid = '';

    /**
     * @var bool True when the reconciliation guard rejected the invoice (persistent, do not retry).
     */
    public bool $reconciliationfailed = false;

    /**
     * Set up curl to be able to connect to ERPNext using config settings.
     */
    public function __construct() {
        global $CFG;
        // Backward compatibilty for older Moodle versions. TODO Remove in 4.5!
        require_once($CFG->dirroot . "/lib/filelib.php");

        $this->baseurl = get_config('local_shopping_cart', 'baseurl');
        $this->token = get_config('local_shopping_cart', 'token');
        $this->headers = [
                'Content-Type: application/json',
                'Authorization: token ' . $this->token,
        ];
        $this->client = new curl();
        $this->client->setHeader($this->headers);
    }

    /**
     * Create the ad hoc task for invoice creation.
     *
     * @param base $event
     * @return void
     */
    public static function create_invoice_task(base $event): void {
        $customdata = [];
        $customdata['classname'] = __CLASS__;
        $customdata['identifier'] = $event->other['identifier'];
        $createinvoicetask = new create_invoice_task();
        $createinvoicetask->set_userid($event->userid);
        $createinvoicetask->set_next_run_time(time());
        $createinvoicetask->set_custom_data($customdata);
        manager::reschedule_or_queue_adhoc_task($createinvoicetask);
    }

    /**
     * Create customer
     *
     * @param int $identifier
     * @return bool true if invoice was created, false if not
     */
    public function create_invoice(int $identifier): bool {
        global $DB;
        $this->identifier = $identifier;
        $url = $this->baseurl . '/api/resource/Sales Invoice';
        // Set up invoice creation.
        $this->invoiceitems = shopping_cart_history::return_data_via_identifier($identifier);

        // Set user.
        foreach ($this->invoiceitems as $item) {
            $this->addressid = (int) $item->address_billing;
            if (empty($this->user)) {
                $this->user = core_user::get_user($item->userid);
                break;
            }
            break;
        }
        // Get user addressid if no addressid was given.
        if (!$this->addressid) {
            $addressrecords = address_operations::get_all_user_addresses($this->user->id);
            if (!empty($addressrecords)) {
                $this->addressid = array_key_first($addressrecords);
            } else {
                throw new \moodle_exception(
                    'nobillingaddress',
                    'local_shopping_cart',
                    '',
                    null,
                    'No billing address available for the user.'
                );
            }
        }

        $this->billingaddress = address_operations::get_specific_user_address($this->addressid);
        if (!empty($this->billingaddress->company)) {
            $this->customername = address_operations::get_specific_user_address($this->addressid)->company;
            $this->customercompany = $this->customername;
        } else {
            $this->customername = fullname($this->user) . ' - ' . $this->user->id;
        }

        $prepareinvoice = $this->prepare_json_invoice_data();
        if (!$prepareinvoice) {
            return false;
        }
        $customerexists = $this->customer_exists();
        if (!$customerexists) {
            mtrace('Customer does not yet exist in ERP');
            if (!$this->create_customer()) {
                mtrace('Customer creation failed');
                return false;
            }
            if (!$this->set_customer_name()) {
                mtrace('Failed to set ERPNext customer name');
                return false;
            }
        }

        $response = $this->client->post(str_replace(' ', '%20', $url), $this->jsoninvoice);
        $success = $this->validate_response($response, $url);
        if ($success) {
            $invoice = new stdClass();
            $invoice->identifier = $identifier;
            $invoice->timecreated = time();
            $responsedata = json_decode($response, true);
            $invoice->invoiceid = $responsedata['data']['name'];
            $DB->insert_record('local_shopping_cart_invoices', $invoice);

            // Submit the invoice.
            $submitresponse = $this->submit_invoice($invoice->invoiceid);
            if ($submitresponse) {
                // Mark the invoice as paid.
                $paymentsuccess = $this->create_payment($responsedata['data'], $invoice->invoiceid);
                if ($paymentsuccess && $this->submit_payment_entry()) {
                    return true;
                } else {
                    mtrace("ERROR: Payment was not saved in ERPNext.");
                }
            }
        }
        return false;
    }

    /**
     * Create a Sales Invoice from explicit data, without any shopping-cart tables.
     *
     * Reuses the same ERPNext helpers as {@see create_invoice()} but takes all data from a plain DTO,
     * so callers outside the cart (e.g. a Stripe webhook) can invoice. v1 creates and submits the Sales
     * Invoice only (no Payment Entry). A reconciliation guard refuses to submit an invoice whose ERPNext
     * grand total does not match the amount actually paid.
     *
     * Expected $data shape:
     *  ->reference       string  external idempotency key (stored as po_no)
     *  ->currency        string  transaction currency (e.g. 'USD'); optional
     *  ->conversionrate  float   rate to company currency; optional
     *  ->grosscheck      float   amount actually paid (net+VAT); optional reconciliation target
     *  ->taxtemplate     string  ERPNext template to force; optional (else auto-selected)
     *  ->vatnumber       string  customer VAT id; optional
     *  ->user            object  {id, email, firstname, lastname}
     *  ->billing         object  {company, name, state(ISO2 country), address, city, zip, id}
     *  ->items           array   of {itemname, net, serviceperiodstart, serviceperiodend}
     *
     * @param stdClass $data
     * @return bool true when a Sales Invoice was created (or already existed) and submitted
     */
    public function create_invoice_from_data(stdClass $data): bool {
        $this->injected = true;
        $this->reference = (string) ($data->reference ?? '');
        $this->currency = !empty($data->currency)
            ? (string) $data->currency
            : ((string) get_config('local_shopping_cart', 'defaultcurrency') ?: null);
        $this->conversionrate = isset($data->conversionrate) && $data->conversionrate ? (float) $data->conversionrate : null;
        $this->forcedtaxtemplate = !empty($data->taxtemplate) ? (string) $data->taxtemplate : null;
        $this->injectedhasvatid = !empty($data->vatnumber);

        $this->user = $data->user;
        $this->billingaddress = $data->billing;
        $this->taxcountrycode = $data->billing->state ?? null;
        if (!empty($this->billingaddress->company)) {
            $this->customername = $this->billingaddress->company;
            $this->customercompany = $this->customername;
        } else {
            $this->customername = fullname($this->user) . ' - ' . $this->user->id;
            $this->customercompany = '';
        }

        // Build invoice items: net price goes in as price with tax 0, so rate = price - tax = net.
        $vatnumber = (string) ($data->vatnumber ?? '');
        $this->invoiceitems = [];
        foreach ($data->items as $lineitem) {
            $item = new stdClass();
            $item->itemname = $lineitem->itemname;
            $item->price = (float) $lineitem->net;
            $item->tax = 0;
            $item->vatnumber = $vatnumber;
            $item->serviceperiodstart = $lineitem->serviceperiodstart ?? time();
            $item->serviceperiodend = $lineitem->serviceperiodend ?? time();
            $item->timecreated = time();
            $item->address_billing = '';
            $this->invoiceitems[] = $item;
        }

        // Idempotency: reuse an already-submitted invoice for this reference.
        if ($this->reference !== '') {
            $existing = $this->find_invoice_by_reference($this->reference);
            if (!empty($existing)) {
                $this->invoiceid = $existing;
                return true;
            }
        }

        if (!$this->prepare_json_invoice_data()) {
            return false;
        }

        if (!$this->customer_exists()) {
            if (!$this->create_customer() || !$this->set_customer_name()) {
                return false;
            }
        }

        // Create the Sales Invoice (draft).
        $url = $this->baseurl . '/api/resource/Sales Invoice';
        $response = $this->client->post(str_replace(' ', '%20', $url), $this->jsoninvoice);
        if (!$this->validate_response($response, $url)) {
            return false;
        }
        $responsedata = json_decode($response, true);
        $this->invoiceid = $responsedata['data']['name'];

        // Reconciliation guard: ERPNext grand total must match what the customer actually paid.
        if (isset($data->grosscheck)) {
            $grandtotal = (float) ($responsedata['data']['grand_total'] ?? 0);
            if (abs($grandtotal - (float) $data->grosscheck) > 0.02) {
                $this->errormessage = "ERPNext gross {$grandtotal} does not match paid amount "
                    . "{$data->grosscheck} for reference {$this->reference}; invoice left as draft.";
                mtrace($this->errormessage);
                $this->delete_invoice($this->invoiceid);
                $this->invoiceid = '';
                $this->reconciliationfailed = true;
                return false;
            }
        }

        return $this->submit_invoice($this->invoiceid);
    }

    /**
     * Find a submitted Sales Invoice by its external reference (po_no).
     *
     * @param string $reference
     * @return string the ERPNext invoice name, or '' if none
     */
    public function find_invoice_by_reference(string $reference): string {
        $filters = '[["Sales Invoice","po_no","=","' . addslashes($reference) . '"],'
            . '["Sales Invoice","docstatus","=",1]]';
        $url = str_replace(' ', '%20', $this->baseurl . '/api/resource/Sales Invoice?filters=' . $filters);
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return '';
        }
        $data = json_decode($response, true);
        return $data['data'][0]['name'] ?? '';
    }

    /**
     * Delete a (draft) Sales Invoice in ERPNext.
     *
     * @param string $invoiceid
     * @return bool
     */
    public function delete_invoice(string $invoiceid): bool {
        $url = str_replace(' ', '%20', $this->baseurl . '/api/resource/Sales Invoice/' . $invoiceid);
        $response = $this->client->delete($url);
        return $this->validate_response($response, $url);
    }

    /**
     * Fetch the list of ERPNext Item codes (for settings dropdowns).
     *
     * @return array item codes
     */
    public function get_erp_items(): array {
        $url = str_replace(' ', '%20', $this->baseurl . '/api/resource/Item?limit_page_length=0');
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return [];
        }
        $data = json_decode($response, true);
        return array_column($data['data'] ?? [], 'name');
    }

    /**
     * Fetch the list of ERPNext currencies (for settings dropdowns).
     *
     * @return array currency codes
     */
    public function get_erp_currencies(): array {
        $url = str_replace(' ', '%20', $this->baseurl . '/api/resource/Currency?limit_page_length=0');
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return [];
        }
        $data = json_decode($response, true);
        return array_column($data['data'] ?? [], 'name');
    }

    /**
     * Submit invoice.
     *
     * @param string $invoiceid
     * @return bool true if invoice was submitted, false if not
     */
    public function submit_invoice(string $invoiceid): bool {
        $submiturl = $this->baseurl . '/api/resource/Sales Invoice/' . $invoiceid;
        $data = ['status' => 'Submitted', 'docstatus' => '1'];
        $submitdata = json_encode($data);
        $submitresponse = $this->client->put(str_replace(' ', '%20', $submiturl), $submitdata);
        return $this->validate_response($submitresponse, $submiturl);
    }

    /**
     * Submit payment entry in ERPNext.
     *
     * @return bool true if invoice was submitted, false if not
     */
    public function submit_payment_entry(): bool {
        $paymententryid = $this->paymententry['data']['name'];
        $submiturl = $this->baseurl . '/api/resource/Payment Entry/' . $paymententryid;
        $data = ['status' => 'Submitted', 'docstatus' => '1'];
        $submitdata = json_encode($data);
        $submitresponse = $this->client->put(str_replace(' ', '%20', $submiturl), $submitdata);
        return $this->validate_response($submitresponse, $submiturl);
    }

    /**
     * Create payment
     *
     * @param array $invoicedata
     * @param string $invoiceid
     *
     * @return bool true if invoice was submitted, false if not
     */
    public function create_payment(array $invoicedata, string $invoiceid): bool {
        $paymententryurl = $this->baseurl . '/api/resource/Payment Entry';
        $paymententrydata = json_encode([
            'payment_type' => 'Receive',
            'party_type' => 'Customer',
            'party' => $this->customername,
            'paid_amount' => $invoicedata['grand_total'],
            'received_amount' => $invoicedata['grand_total'],
            'target_exchange_rate' => 1.0,
            'paid_to' => 'Erste Bank - WB',
            'paid_to_account_currency' => 'EUR',
            'reference_no' => $invoicedata['name'] . '-' . $invoicedata['posting_date'],
            'reference_date' => date('Y-m-d'),
            'references' => [
                [
                    'reference_doctype' => 'Sales Invoice',
                    'reference_name' => $invoiceid,
                    'total_amount' => $invoicedata['grand_total'],
                    'outstanding_amount' => $invoicedata['grand_total'],
                    'allocated_amount' => $invoicedata['grand_total'],
                ],
            ],
        ]);
        $paymentresponse = $this->client->post(str_replace(' ', '%20', $paymententryurl), $paymententrydata);
        if (!empty($paymentresponse)) {
            $this->paymententry = json_decode($paymentresponse, true);
        }
        return $this->validate_response($paymentresponse, $paymententryurl);
    }

    /**
     * Get tax templates available in the ERP system.
     *
     * @return array available tax tampletes, empty if no template found.
     */
    public function get_erp_taxes_charges_templates(): array {
        // Fetch 50 templates from ERP. It should be rare to have more than 50 templates configured.
        $uncleanedurl = $this->baseurl . '/api/resource/Sales Taxes and Charges Template?limit_page_length=50';
        $url = str_replace(' ', '%20', $uncleanedurl);
        $response = $this->client->get($url);
        $success = $this->validate_response($response, $url);
        $templates = [];
        if ($success) {
            $responsearray = json_decode($response, true);
            $templates = array_column($responsearray['data'], 'name');
        } else {
            throw new \moodle_exception(
                'error',
                'local_shopping_cart',
                '',
                null,
                'There was a problem fetching tax templates from ERPNext: ' . $response
            );
        }
        return $templates;
    }

    /**
     * Set tax tamplete to use for the invoice.
     *
     * @return string tax tamplete
     */
    public function set_taxes_charges_template(): string {
        $taxtemplates = $this->get_erp_taxes_charges_templates();
        $countrykey = $this->taxcountrycode ?? $this->billingaddress->state;
        if ($this->injected) {
            // Injected callers supply the has-VAT-id flag directly (no cart ledger).
            $hasvatid = $this->injectedhasvatid;
        } else {
            $cartstore = cartstore::instance($this->user->id);
            $cartstore->set_countrycode($countrykey);
            $ledgerentries = shopping_cart_history::return_data_from_ledger_via_identifier($this->identifier);
            $hasvatid = !empty(reset($ledgerentries)->vatnumber);
        }
        return self::select_tax_template($countrykey, $hasvatid, $taxtemplates);
    }

    /**
     * Pure selection of the ERPNext tax template from country code + presence of a VAT id.
     *
     * @param string $countrykey ISO2 country code
     * @param bool $hasvatid whether a valid VAT id is present
     * @param array $taxtemplates available ERPNext template names
     * @return string chosen template name
     */
    public static function select_tax_template(string $countrykey, bool $hasvatid, array $taxtemplates): string {
        // ToDo: template names are hardcoded for internal use; make generic via settings.
        $isowncountry = vatnrchecker::is_own_country($countrykey);
        $iseuropean = vatnrchecker::is_european($countrykey);

        if ($iseuropean && !$isowncountry && in_array('EU Reverse Charge', $taxtemplates) && $hasvatid) {
            // EU reverse charge (cross-border B2B with VAT id).
            return 'EU Reverse Charge';
        } else if ($iseuropean && !$hasvatid) {
            return 'Austria Tax';
        } else if (!$iseuropean && in_array('Export VAT', $taxtemplates)) {
            // Export (non-EU) sales.
            return 'Export VAT';
        } else if ($isowncountry && in_array('Austria Tax', $taxtemplates)) {
            return 'Austria Tax';
        }
        // Default fallback to Austria Tax if no other condition is met.
        return 'Austria Tax';
    }

    /**
     * Resolve the tax template to use: the forced one if set, otherwise auto-select.
     *
     * @return string
     */
    private function resolve_tax_template(): string {
        if (!empty($this->forcedtaxtemplate)) {
            return $this->forcedtaxtemplate;
        }
        return $this->set_taxes_charges_template();
    }

    /**
     * Get all addresses for a given customer from ERPNext.
     *
     * @param string $customername The name of the customer.
     * @return array An array of address titles.
     */
    private function get_all_customer_addresses(string $customername): array {
        $filters = str_replace(
            ' ',
            '%20',
            '[["Address","address_type","=","Billing"],["Address","name","like","%' . $customername . '%"]]'
        );
        $url = $this->baseurl .
                '/api/resource/Address?filters=' . $filters;
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return [];
        }
        $data = json_decode($response, true);
        return array_column($data['data'], 'name');
    }

    /**
     * Get billing address of customer.
     * @return string Address name of the customer or empty string
     */
    public function get_erp_billing_address_name(): string {
        // Use the already-resolved billing address (set from cart tables or injected data).
        $addressrecord = $this->billingaddress;
        if ($addressrecord) {
            // Check if the address exists in ERPNext.
            if (!empty($this->customercompany)) {
                $addresstitle = $addressrecord->company;
            } else {
                $addresstitle =
                        $addressrecord->name . ' - ' .
                        $addressrecord->city . ' - ' .
                        $addressrecord->id;
            }

            $erpnextaddresses = $this->get_all_customer_addresses($addresstitle);
            if (empty($erpnextaddresses)) {
                return $this->create_address($addressrecord, $addresstitle);
            } else {
                // If there are more than 1 billing addresses in ERPNext we use the first one.
                reset($erpnextaddresses);
                return current($erpnextaddresses);
            }
        } else {
            throw new \moodle_exception(
                'nobillingaddress',
                'local_shopping_cart',
                '',
                null,
                'No billing address available for the user.'
            );
        }
    }

    /**
     * Create a address on ERPNext. That is needed for invoicing.
     *
     * @param object $addressrecord
     * @param string $addresstitle
     * @return string address name in ERPNext
     */
    public function create_address(object $addressrecord, string $addresstitle): string {
        $url = $this->baseurl . '/api/resource/Address';
        $address = [];
        $address['address_title'] = $addresstitle;
        $address['address_type'] = 'Billing';
        $address['address_line1'] = $addressrecord->address;
        $address['city'] = $addressrecord->city;
        $address['pincode'] = $addressrecord->zip;
        $address['country'] = $this->get_country_name_by_code($addressrecord->state);
        $address['customer'] = $addressrecord->name;
        $response = $this->client->post($url, json_encode($address));
        $success = $this->validate_response($response, $url);
        if ($success) {
            $responsedata = json_decode($response, true);
            // Extract address name from response.
            if (isset($responsedata['data']['name'])) {
                $addressname = $responsedata['data']['name'];
                mtrace('Successfully created ERPNext address: ' . $addressname, DEBUG_DEVELOPER);
                return $addressname;
            } else {
                mtrace('ERPNext address created but name not found in response', DEBUG_DEVELOPER);
                return ''; // Success but couldn't extract name.
            }
        } else {
            throw new \moodle_exception(
                'error',
                'local_shopping_cart',
                '',
                null,
                'There was a problem with retrieving the address from ERPNext: ' . $response
            );
        }
    }

    /**
     * Get ERPNext country name from country code.
     *
     * @param string $code The ISO country code (e.g. 'AT', 'DE').
     * @return string|null The country name (e.g. 'Austria'), or null if not found.
     */
    protected function get_country_name_by_code(string $code): ?string {
        $url = $this->baseurl . '/api/resource/Country?filters=[["code","=","' . $code . '"]]';
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            throw new \moodle_exception(
                'error',
                'local_shopping_cart',
                '',
                null,
                'There was a problem with retrieving the country from ERPNext: ' . $response
            );
        }
        $data = json_decode($response);
        return $data->data[0]->name;
    }

    /**
     * Prepare the json for the REST API.
     * Return true on success false on failure.
     *
     * @return bool
     */
    public function prepare_json_invoice_data(): bool {
        $serviceperiodstart = null;
        $serviceperiodend = null;
        $this->invoicedata['taxcountrycode'] = $this->billingaddress->state;
        foreach ($this->invoiceitems as $item) {
            if (!$this->item_exists($item->itemname)) {
                return false;
            }
            $itemdata = [];
            $itemdata['item_code'] = $item->itemname;
            $itemdata['qty'] = 1;

            $this->invoicedata['vatid'] = $item->vatnumber;
            if (!isset($this->invoicedata['taxes_and_charges'])) {
                $this->invoicedata['taxes_and_charges'] = $this->resolve_tax_template();
                if (!$this->invoicedata['taxes_and_charges']) {
                    return false;
                } else {
                    self::tax_charge_exists($this->invoicedata['taxes_and_charges']);
                }
            }
            // Always use net price to send to ERPNext. In shopping_cart_history table column price is gross.
            $itemdata['rate'] = (float) $item->price - (float) $item->tax;
            $this->invoicedata['items'][] = $itemdata;

            $itemserviceperiodstart = $item->serviceperiodstart ?? $item->timecreated;
            $itemserviceperiodend = $item->serviceperiodend ?? $item->timecreated;
            if (
                is_null($serviceperiodstart) ||
                $itemserviceperiodstart < $serviceperiodstart
            ) {
                $serviceperiodstart = $itemserviceperiodstart;
            }
            if (
                is_null($serviceperiodend) ||
                $itemserviceperiodend > $serviceperiodend
            ) {
                $serviceperiodend = $itemserviceperiodend;
            }
            $this->invoicedata['address_billing'] = $item->address_billing;
        }
        // For 1 year booking license we have to adapt the end date of service period. It's a hack.
        foreach ($this->invoiceitems as $item) {
            if (strpos($item->itemname, '1 year') !== false) {
                $serviceperiodend = strtotime('+1 year -1 day', $serviceperiodstart);
                break;
            }
        }
        $this->billingaddressname = $this->get_erp_billing_address_name();
        if (empty($this->billingaddressname)) {
            return false;
        }
        $this->invoicedata['address_billing'] = $this->billingaddressname;
        $this->invoicedata['customer'] = $this->customername;
        $date = date('Y-m-d', time());
        // Convert the Unix timestamp to ISO 8601 date format.
        $this->invoicedata['posting_date'] = $date;
        $this->invoicedata['set_posting_time'] = 1;
        $this->invoicedata['due_date'] = $date;
        $this->invoicedata['from'] = date('Y-m-d', $serviceperiodstart);
        $this->invoicedata['to'] = date('Y-m-d', $serviceperiodend);
        $this->invoicedata['terms'] = 'Thank you for your online payment and your trust in our services.';
        $this->invoicedata['customer_address'] = $this->billingaddressname;
        // Multi-currency support: only emit when explicitly set, so the existing EUR cart flow is unchanged.
        if (!empty($this->currency)) {
            $this->invoicedata['currency'] = $this->currency;
            if (!empty($this->conversionrate)) {
                $this->invoicedata['conversion_rate'] = $this->conversionrate;
            }
        }
        // External idempotency reference (e.g. Stripe order id); only set for injected callers.
        if (!empty($this->reference)) {
            $this->invoicedata['po_no'] = $this->reference;
        }
        $this->jsoninvoice = json_encode($this->invoicedata);
        return true;
    }

    /**
     * Check if the customer already exists so it is not recreated on ERPNext.
     * If we pass the same customer name again to ERPNext, a new customer with a digit attached to the
     * currently used customer is created. That is what we want to avoid.
     *
     * @return bool
     */
    public function customer_exists(): bool {
        $uncleanedurl = $this->baseurl . "/api/resource/Customer/" . rawurlencode($this->customername) . "/";
        $url = str_replace(' ', '%20', $uncleanedurl);
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return false;
        } else {
            $responsetaxid = json_decode($response);
            if (
                $responsetaxid->data->tax_id == '' &&
                isset($this->invoicedata['vatid']) &&
                $this->invoicedata['vatid'] !== $responsetaxid->data->tax_id
            ) {
                $responsetaxid->data->tax_id = $this->invoicedata['vatid'];
                $response = $this->client->put($url, json_encode($responsetaxid->data));
                return $this->validate_response($response, $url);
            }
            return true;
        }
    }

    /**
     * Check if the tax charge already exists so it is not recreated on ERPNext.
     *
     * @param string $taxchargestemplate
     *
     * @return bool
     */
    public function tax_charge_exists(string $taxchargestemplate): bool {
        $uncleanedurl =
            $this->baseurl . "/api/resource/Sales%20Taxes%20and%20Charges%20Template/" . rawurlencode($taxchargestemplate) . "/";
        $url = str_replace(' ', '%20', $uncleanedurl);
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return false;
        } else {
            $taxtemplate = json_decode($response);
            $taxes = [];
            foreach ($taxtemplate->data->taxes as $tax) {
                $taxes[] =
                    [
                        'charge_type' => $tax->charge_type,
                        'account_head' => $tax->account_head,
                        'description' => $tax->description,
                        'rate' => $tax->rate,
                    ];
            }
            $this->invoicedata['taxes'] = $taxes;
        }
        return $this->validate_response($response, $url);
    }

    /**
     * Create a customer on ERPNext. That is needed for invoicing.
     *
     * @return bool
     */
    public function create_customer(): bool {
        $url = $this->baseurl . '/api/resource/Customer';
        $customer = [];
        $customer['customer_name'] = $this->customername;
        // Todo: Hardcoded ERP values. Replace with variabls.
        if (!empty($this->customercompany)) {
            $customer['customer_type'] = 'Company';
        } else {
            $customer['customer_type'] = 'Individual';
        }
        $customer['customer_group'] = 'All Customer Groups';
        // Todo: Implement Customer Address.
        $countrycode = get_config('local_shopping_cart', 'defaultcountry');
        if (in_array($countrycode, $this->get_all_territories())) {
            $customer['territory'] = $countrycode;
        } else {
            // This is a value present by default in ERPNext.
            $customer['territory'] = 'All Territories';
        }
        $customer['email_id'] = $this->user->email;
        $customer['customer_details'] = "Moodle user id: " . $this->user->id;
        if (isset($this->invoicedata['vatid'])) {
            $customer['tax_id'] = $this->invoicedata['vatid'];
        }
        $response = $this->client->post($url, json_encode($customer));
        $success = $this->validate_response($response, $url);
        if (!$success) {
            return false;
        }

        // Now it's necessary to make sure that the connection to the address is correct.
        $data = [];
        $links = ['link_doctype' => 'Customer', 'link_name' => $this->customername];
        $data['links'] = [$links];
        $url = $this->baseurl . '/api/resource/Address/' . rawurlencode($this->billingaddressname);
        $response = $this->client->put($url, json_encode($data));

        return $this->validate_response($response, $url);
    }

    /**
     * Do not use. This is dangerous and should not be done in Moodle.
     *
     * Create a tax charge on ERPNext. That is needed for invoicing.
     *
     * @return bool
     */
    public function create_tax_charge(): bool {
        $url = $this->baseurl . '/api/resource/Sales%20Taxes%20and%20Charges%20Template';
        $taxpercentage = reset($this->invoiceitems);
        $taxpercentage = $taxpercentage->taxpercentage ?? '0.0';
        $title = "Test";
        // This is the company in ERPNext which is the seller, not the customer.
        $company = $this->get_default_company();
        $taxes = [
            [
                "charge_type" => "On Net Total",
                "account_head" => "Umsatzsteuer - WB",
                "rate" => $taxpercentage,
                "description" => "VAT " . $taxpercentage . "%",
            ],
        ];
        $taxtemplate = [
            "title" => $title,
            "company" => $company,
            "taxes" => $taxes,
        ];
        $response = $this->client->post($url, json_encode($taxtemplate));
        if (!$response) {
            return false;
        }
        return $this->validate_response($response, $url);
    }

    /**
     * Check if the item exists on ERPNext. If not, it is not possible to create an invoice.
     * TODO: Create item if it does not exist.
     *
     * @param string $itemname
     * @return bool
     */
    public function item_exists(string $itemname): bool {
        $url = $this->baseurl . '/api/resource/Item/' . $itemname . "/";
        $response = $this->client->get(str_replace(' ', '%20', $url));
        if (!$response) {
            return false;
        }
        return $this->validate_response($response, $url);
    }

    /**
     * Check if entry exists in the JSON response.
     *
     * @param string $response The JSON response from ERPNext.
     * @param string $url of the request response from ERPNext.
     * @return bool True if the entry exists, false otherwise.
     */
    public function validate_response(string $response, string $url): bool {
        // Decode the JSON response into an associative array.
        $resparray = json_decode($response, true);

        // Check if the response contains data.
        if (isset($resparray['data']) || isset($resparray['message'])) {
            return true; // Entry exists or entry was successfully created.
        }

        // Capture the calling function name for debugging.
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callhistory = var_export($backtrace, true);

        // Check if the response contains an error message.
        if (isset($resparray['exc_type'])) {
            $this->errormessage = $resparray['exc_type'] . ' - ' . $url;
            mtrace("API response: {$this->errormessage} | Called by: {$callhistory}");
            return false; // Entry does not exist (error).
        }
        if (isset($resparray['exception'])) {
            $this->errormessage = $resparray['exception'] . ' - ' . $url;
            mtrace("API response: {$this->errormessage} | Called by: {$callhistory}");
            return false; // Entry does not exist (error).
        }

        // Log a generic error message if no specific error is found.
        mtrace("API response: Unknown issue with response from URL: {$url} | Called by: {$callhistory}");
        return false;
    }

    /**
     * Get all territories from ERP so we can check if they match the value used in Moodle.
     * Empty array is returned if request had a problem.
     *
     * @return string[] Array of territory names (countries and regions like EU)
     */
    private function get_all_territories(): array {
        $url = $this->baseurl . '/api/resource/Territory/';
        $response = $this->client->get($url);
        if (!$response) {
            return [];
        }
        $success = $this->validate_response($response, $url);
        if ($success) {
            $territoryarray = json_decode($response, true);
            if (isset($territoryarray['data'])) {
                return array_column($territoryarray['data'], 'name');
            }
        }
        return [];
    }

    /**
     * Set customer name, as it is not set correctly during customer creation.
     *
     * @return bool
     */
    private function set_customer_name(): bool {
        $url = $this->baseurl . '/api/resource/Customer/' . rawurlencode($this->customername);
        if (!empty($this->customercompany)) {
            $customer = ['customer_name' => $this->customername];
        } else {
            $customer = ['customer_name' => fullname($this->user)];
        }
        $json = json_encode($customer);
        $response = $this->client->put(str_replace(' ', '%20', $url), $json);
        if (!$response) {
            return false;
        }
        return $this->validate_response($response, $url);
    }

    /**
     * Get the default company name from ERPNext.
     *
     * @return string Returns the default company name or empty string if not found.
     */
    public function get_default_company(): string {
        // API endpoint to get the default company.
        $url = $this->baseurl . '/api/resource/Company';

        // Make GET request to ERPNext API.
        $response = $this->client->get($url);

        $success = $this->validate_response($response, $url);
        // If the response is not valid, return null.
        if (!$success) {
            return '';
        }

        // Decode the response body.
        $data = json_decode($response, true);

        // Validate the response structure and ensure data exists.
        if (!isset($data['data'][0]['name'])) {
            return '';
        }

        // Return the default company name.
        return $data['data'][0]['name'];
    }
}
