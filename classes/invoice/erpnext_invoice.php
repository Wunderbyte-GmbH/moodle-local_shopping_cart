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
use local_shopping_cart\interfaces\invoice;
use curl;
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
    private $baseurl;
    /**
     * @var string
     */
    private $token;
    /**
     * @var array|string[]
     */
    private array $headers;
    /**
     * @var curl curl wrapper
     */
    private curl $client;
    /**
     * @var bool|stdClass
     */
    private $user;
    /**
     * @var false|string json
     */
    private $jsoninvoice;
    /**
     * @var string json
     */
    public string $errormessage = '';
    /**
     * @var string customer name
     */
    private string $customer;
    /**
     * @var array items on the invoice
     */
    private array $invoiceitems;
    /**
     * @var array Data structure of the invoice as array that can be json encoded.
     */
    private array $invoicedata = [];

    /**
     * Set up curl to be able to connect to ERPNext using config settings.
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
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
    public static function create_invoice_task(base $event) {
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
        $url = $this->baseurl . '/api/resource/Sales Invoice';
        // Setup invoice creation.
        $this->invoiceitems = shopping_cart_history::return_data_via_identifier($identifier);

        // Set user.
        foreach ($this->invoiceitems as $item) {
            if (empty($this->user)) {
                $this->user = core_user::get_user($item->userid);
                break;
            }
        }
        $this->customer = fullname($this->user) . ' - ' . $this->user->id;
        $prepareinvoice = $this->prepare_json_invoice_data();
        if (!$prepareinvoice) {
            return false;
        }
        $customerexists = $this->customer_exists();
        if (!$customerexists) {
            if (!$this->create_customer()) {
                return false;
            }
            if (!$this->set_customer_name()) {
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
                $paymentresponse = $this->create_payment($submitresponse, $invoice->invoiceid);
                if ($paymentresponse) {
                    $submitresponse = $this->submit_payment_entry($paymentresponse);
                    if (
                        $submitresponse
                    ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Create customer
     *
     * @param string $invoicename
     * @param string $customeremail
     * @return bool true if invoice was send, false if not
     */
    public function send_invoice($invoicename, $customeremail): bool {
        // Prepare the email parameters.
        $invoicepdf = $this->get_invoice_pdf($invoicename);
        if (!$invoicepdf) {
            return false;
        }
        $currentlang = current_language();
        force_current_language($this->user->lang);
        $emailparams = [
            "recipients" => $customeremail,
            "subject" => get_string('erpnext_subject', 'local_shopping_cart') . " " . $invoicename,
            "content" => '<div class="ql-editor read-mode"><p>Testing Content</p></div>',
            "doctype" => "Sales Invoice",
            "name" => $invoicename,
            "send_mail" => 1,
            "print_html" => null,
            "send_me_a_copy" => 0,
            "print_format" => "Standardrechnung",
            "sender" => "info@wunderbyte.at",
            "attachments" => [],
            "read_receipt" => 0,
            "print_letterhead" => 1,
            "send_after" => null,
            "print_language" => "de",
        ];
        force_current_language($currentlang);
        $jsondata = json_encode($emailparams);
        $url = $this->baseurl . '/api/method/frappe.core.doctype.communication.email.make';
        $response = $this->client->post(str_replace(' ', '%20', $url), $jsondata);

        $success = $this->validate_response($response, $url);
        if ($success) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Submit invoice.
     *
     * @param string $invoiceid
     * @return string true if invoice was submitted, false if not
     */
    public function submit_invoice($invoiceid): string {
        $submiturl = $this->baseurl . '/api/resource/Sales Invoice/' . $invoiceid;
        $submitdata = json_encode([
            'status' => 'Submitted',
            'docstatus' => '1',
        ]);
        $submitresponse = $this->client->put(str_replace(' ', '%20', $submiturl), $submitdata);
        if ($this->validate_response($submitresponse, $submiturl)) {
            return $submitresponse;
        }
        return false;
    }

    /**
     * Submit invoice.
     *
     * @param string $paymentresponse
     * @return string true if invoice was submitted, false if not
     */
    public function submit_payment_entry($paymentresponse): string {
        $paymentresponsedata = json_decode($paymentresponse, true);
        $paymententryid = $paymentresponsedata['data']['name'];
        $submiturl = $this->baseurl . '/api/resource/Payment Entry/' . $paymententryid;
        $submitdata = json_encode([
            'status' => 'Submitted',
            'docstatus' => '1',
        ]);
        $submitresponse = $this->client->put(str_replace(' ', '%20', $submiturl), $submitdata);
        if ($this->validate_response($submitresponse, $submiturl)) {
            return $submitresponse;
        }
        return false;
    }


    /**
     * Create payment
     *
     * @param string $submitresponse
     * @param string $invoiceid
     *
     * @return string true if invoice was submitted, false if not
     */
    public function create_payment($submitresponse, $invoiceid): string {
        $jsoninvoice = json_decode($submitresponse);
        $paymententryurl = $this->baseurl . '/api/resource/Payment Entry';
        $paymententrydata = json_encode([
            'payment_type' => 'Receive',
            'party_type' => 'Customer',
            'party' => $this->customer,
            'paid_amount' => $jsoninvoice->data->grand_total,
            'received_amount' => $jsoninvoice->data->grand_total,
            'target_exchange_rate' => 1.0,
            'paid_to' => 'Erste Bank - WB',
            'paid_to_account_currency' => 'EUR',
            'reference_no' => $jsoninvoice->data->name . '-' . $jsoninvoice->data->posting_date,
            'reference_date' => date('Y-m-d'),
            'references' => [
                [
                    'reference_doctype' => 'Sales Invoice',
                    'reference_name' => $invoiceid,
                    'total_amount' => $jsoninvoice->data->grand_total,
                    'outstanding_amount' => $jsoninvoice->data->grand_total,
                    'allocated_amount' => $jsoninvoice->data->grand_total,
                ],
            ],
        ]);
        $paymentresponse = $this->client->post(str_replace(' ', '%20', $paymententryurl), $paymententrydata);
        if ($this->validate_response($paymentresponse, $paymententryurl)) {
            return $paymentresponse;
        }
        return false;
    }


    /**
     * Create customer
     *
     * @param string $invoicename
     * @return string invoice as pdf
     */
    private function get_invoice_pdf($invoicename) {
        $url = $this->baseurl . "/api/method/frappe.utils.print_format.download_pdf";
        $params = [
            "doctype" => get_string('erpnext_reference_doctype', 'local_shopping_cart'),
            "name" => $invoicename,
            "format" => "Standard",
            "no_letterhead" => 0,
        ];
        $query = "doctype=" . urlencode($params["doctype"]) .
             "&name=" . urlencode($params["name"]) .
             "&format=" . urlencode($params["format"]) .
             "&no_letterhead=" . urlencode($params["no_letterhead"]);

        $urlwithquery = "$url?$query";
        $response = $this->client->get($urlwithquery);
        $success = $this->validate_response($response, $url);
        if ($success) {
            return false;
        } else {
            return base64_encode($response);
        }
    }

    /**
     * Get tax tamplete.
     *
     * @return string tax tamplete
     */
    public function get_taxes_charges_template(): string {
        $iseuropean = vatnrchecker::is_european($this->invoicedata['taxcountrycode'] ?? null);
        $isowncountry = vatnrchecker::is_own_country($this->invoicedata['taxcountrycode'] ?? null);
        return vatnrchecker::get_template(
            $iseuropean,
            $isowncountry,
            $this->invoicedata['uid']
        );
    }

    /**
     * Get billing address of customer.
     * @return string
     */
    public function get_billing_address(): string {
        $address = '';
        global $DB;

        // Get billing address.
        $data = [
            'id' => $this->invoicedata['address_billing'],
        ];

        $addressrecord = $DB->get_record(
            'local_shopping_cart_address',
            $data
        );
        if ($addressrecord) {
            // Check if address exists in erp.
            $addresstitle =
                $addressrecord->name . ' - ' .
                $addressrecord->city . ' - ' .
                $addressrecord->id;

            $uncleanedurl = $this->baseurl . "/api/resource/Address/" . rawurlencode($addresstitle . '-Abrechnung') . "/";
            $url = str_replace(' ', '%20', $uncleanedurl);
            $response = $this->client->get($url);
            if (!$this->validate_response($response, $url)) {
                // Create new address.
                $response = self::create_address($addressrecord, $addresstitle);
                if (!$this->validate_response($response, $url)) {
                    return false;
                }
            }
            $response = json_decode($response);
            return $response->data->name;
        }
        return $address;
    }

    /**
     * Create a address on ERPNext. That is needed for invoicing.
     * @param object $addressrecord
     * @param string $addresstitle
     * @return string
     */
    public function create_address($addressrecord, $addresstitle): string {

        $url = $this->baseurl . '/api/resource/Address';
        $address = [];
        $address['address_title'] = $addresstitle;
        $address['address_type'] = 'Billing';
        $address['address_line1'] = $addressrecord->address;
        $address['city'] = $addressrecord->city;
        $address['state'] = $addressrecord->state;
        $address['pincode'] = $addressrecord->zip;
        $address['country'] = get_string($addressrecord->state, 'core_countries');
        $address['customer'] = $addressrecord->name;

        $response = $this->client->post($url, json_encode($address));
        if (!$this->validate_response($response, $url)) {
            return false;
        }
        return $response;
    }

    /**
     * Prepre the json for the REST API.
     * @return bool
     */
    public function prepare_json_invoice_data(): bool {
        $serviceperiodstart = null;
        $serviceperiodend = null;
        foreach ($this->invoiceitems as $item) {
            if (!$this->item_exists($item->itemname)) {
                return false;
            }
            if (empty($this->invoicedata['timecreated'])) {
                $this->invoicedata['timecreated'] = $item->timemodified;
            }
            $itemdata = [];
            $itemdata['item_code'] = $item->itemname;
            $itemdata['qty'] = 1;

            $this->invoicedata['taxcountrycode'] = $item->taxcountrycode;
            $this->invoicedata['uid'] = $item->vatnumber;
            if (!isset($this->invoicedata['taxes_and_charges'])) {
                $this->invoicedata['taxes_and_charges'] = self::get_taxes_charges_template();
                if (!$this->invoicedata['taxes_and_charges']) {
                    return false;
                } else {
                    self::tax_charge_exists($this->invoicedata['taxes_and_charges']);
                }
            }

            if (isset($item->vatnumber) && !is_null($item->vatnumber)) {
                $itemdata['rate'] = (float) $item->price;
            } else {
                $itemdata['rate'] = (float) $item->price - (float) $item->tax;
            }

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
        $billingaddress = $this->get_billing_address();
        if (!$billingaddress) {
            return false;
        }
        $this->invoicedata['customer'] = $this->customer;
        $date = date('Y-m-d', $this->invoicedata['timecreated']);
        // Convert the Unix timestamp to ISO 8601 date format.
        $this->invoicedata['posting_date'] = $date;
        $this->invoicedata['set_posting_time'] = 1;
        $this->invoicedata['due_date'] = $date;
        $this->invoicedata['from'] = date('Y-m-d', $serviceperiodstart);
        $this->invoicedata['to'] = date('Y-m-d', $serviceperiodend);

        $this->invoicedata['customer_address'] = $billingaddress;
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
        $uncleanedurl = $this->baseurl . "/api/resource/Customer/" . rawurlencode($this->customer) . "/";
        $url = str_replace(' ', '%20', $uncleanedurl);
        $response = $this->client->get($url);
        if (!$this->validate_response($response, $url)) {
            return false;

        } else {
            $responsetaxid = json_decode($response);
            if (
                $responsetaxid->data->tax_id == '' &&
                isset($this->invoicedata['uid'])
            ) {
                $responsetaxid->data->tax_id = $this->invoicedata['uid'];
                $response = $this->client->put($url, json_encode($responsetaxid->data));
            }
        }
        return $this->validate_response($response, $url);
    }

    /**
     * Check if the tax charge already exists so it is not recreated on ERPNext.
     * @param string $taxchargestemplate
     *
     * @return bool
     */
    public function tax_charge_exists($taxchargestemplate): bool {
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
        $customer['customer_name'] = $this->customer;
        $customer['customer_type'] = 'Individual';
        $customer['customer_group'] = 'All Customer Groups';
        // TODO: Implement Customer Address.
        $countrycode = get_config('local_shopping_cart', 'defaultcountry');
        if (in_array($countrycode, $this->get_all_territories())) {
            $customer['territory'] = $countrycode;
        } else {
            // This is a value present by default in ERPNext.
            $customer['territory'] = 'All Territories';
        }
        $customer['email_id'] = $this->user->email;
        $customer['customer_details'] = $this->user->id;
        if (isset($this->invoicedata['uid'])) {
            $customer['tax_id'] = $this->invoicedata['uid'];
        }
        $response = $this->client->post($url, json_encode($customer));
        if (!$response) {
            return false;
        }
        return $this->validate_response($response, $url);
    }

    /**
     * Create a tax charge on ERPNext. That is needed for invoicing.
     *
     * @return bool
     */
    public function create_tax_charge(): bool {
        $url = $this->baseurl . '/api/resource/Sales%20Taxes%20and%20Charges%20Template';
        $taxpercentage = reset($this->invoiceitems);
        $taxpercentage = $taxpercentage->taxpercentage ?? '0.0';
        $title = "Test";
        $company = "Wunderbyte GmbH";
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
        if (isset($resparray['data']) ||isset($resparray['message'])) {
            return true; // Entry exists or entry was successfully created.
        }
        // Check if the response contains an error message.
        if (isset($resparray['exc_type'])) {
            $this->errormessage = $resparray['exc_type'] . ' - ' . $url;
            return false; // Entry does not exist (error).
        }
        if (isset($resparray['exception'])) {
            $this->errormessage = $resparray['exception'] . ' - ' . $url;
            return false; // Entry does not exist (error).
        }
        return false;
    }

    /**
     * Get all territories from ERP so we can check if they match the value used in Moodle.
     * Empty array is returned if request had a problem.
     *
     * @return array of countries and territories (like EU)
     */
    private function get_all_territories(): array {
        $url = $this->baseurl . '/api/resource/Territory/';
        $response = $this->client->get($url);
        if (!$response) {
            return false;
        }
        $success = $this->validate_response($response, $url);
        if ($success) {
            $territoryarray = json_decode($response, true);
            return array_column($territoryarray['data'], 'name');
        }
        return [];
    }

    /**
     * Set customer name, as it is not set correctly curing customer creation.
     *
     * @return bool
     */
    private function set_customer_name(): bool {
        $url = $this->baseurl . '/api/resource/Customer/' . rawurlencode($this->customer);
        $json = json_encode(['customer_name' => fullname($this->user)]);
        $response = $this->client->put(str_replace(' ', '%20', $url), $json);
        if (!$response) {
            return false;
        }
        return $this->validate_response($response, $url);
    }
}
