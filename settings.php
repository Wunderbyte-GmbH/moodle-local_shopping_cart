<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_shopping_cart
 * @category    admin
 * @copyright   2021 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;
use local_shopping_cart\admin_setting_taxcategories;
use local_shopping_cart\local\vatnrchecker;
use local_shopping_cart\shopping_cart;
use mod_booking\customfield\booking_handler;

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_shopping_cart';

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $settings = new admin_settingpage('local_shopping_cart_settings', '');
    $ADMIN->add('localplugins', new admin_category($componentname, new lang_string('pluginname', $componentname)));
    $ADMIN->add($componentname, $settings);

    $paymentaccountrecords = helper::get_payment_accounts_to_manage(context_system::instance(), false);

    $paymentaccounts = [];
    foreach ($paymentaccountrecords as $paymentaccountrecord) {
        $paymentaccounts[$paymentaccountrecord->get('id')] = $paymentaccountrecord->get('name');
    }

    if (empty($paymentaccounts)) {

        $moodleurl = new moodle_url('/payment/accounts.php');
        $urlobject = new stdClass;
        $urlobject->link = $moodleurl->out(false);

        // If we have no payment accounts then show a static text instead.
        $settings->add(new admin_setting_description(
                'nopaymentaccounts',
                new lang_string('nopaymentaccounts', 'local_shopping_cart'),
                new lang_string('nopaymentaccountsdesc', 'local_shopping_cart', $urlobject)
        ));

    } else {
        // Connect payment account.
        $settings->add(
                new admin_setting_configselect(
                        $componentname . '/accountid',
                        new lang_string('accountid', $componentname),
                        new lang_string('accountid:description', $componentname),
                        null,
                        $paymentaccounts
                )
        );

        // Allow chosing individual paymentaccount for each item.
        $settings->add(
                new admin_setting_configcheckbox($componentname . '/allowchooseaccount',
                        new lang_string('allowchooseaccount', 'local_shopping_cart'),
                        new lang_string('allowchooseaccount_desc', 'local_shopping_cart'), 0));
    }

    // Currency dropdown.
    $currenciesobjects = shopping_cart::get_possible_currencies();

    $currencies = ['EUR' => 'Euro (EUR)'];

    foreach ($currenciesobjects as $currenciesobject) {
        $currencyidentifier = $currenciesobject->get_identifier();
        $currencies[$currencyidentifier] = $currenciesobject->out(current_language()) . ' (' . $currencyidentifier . ')';
    }

    $settings->add(
        new admin_setting_configselect(
                $componentname . '/globalcurrency',
                get_string('globalcurrency', $componentname),
                get_string('globalcurrencydesc', $componentname),
                'EUR', $currencies));

    // Max items in cart.
    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/maxitems',
                    new lang_string('maxitems', $componentname),
                    new lang_string('maxitems:description', $componentname),
                    10,
                    PARAM_INT
            )
    );

    // Item expiriation time in minutes.
    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/expirationtime',
                    new lang_string('expirationtime', $componentname),
                    new lang_string('expirationtime:description', $componentname),
                    15,
                    PARAM_INT
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/expirationtime',
                    new lang_string('expirationtime', $componentname),
                    new lang_string('expirationtime:description', $componentname),
                    15,
                    PARAM_INT
            )
    );

    $settings->add(
        new admin_setting_configtext(
                $componentname . '/bookingfee',
                get_string('bookingfee', $componentname),
                get_string('bookingfee_desc', $componentname),
                0,
                PARAM_FLOAT
        )
    );

    $settings->add(
        new admin_setting_configcheckbox($componentname . '/bookingfeevariable',
                get_string('bookingfeevariable', 'local_shopping_cart'),
                get_string('bookingfeevariable_desc', 'local_shopping_cart'), 0)
        );
    $bookingfeevariable = get_config('local_shopping_cart', 'bookingfeevariable') == 1;
    if ($bookingfeevariable) {
        $settings->add (
                new admin_setting_configtextarea(
                $componentname . '/definefeesforcostcenters',
                get_string('definefeesforcostcenters', 'local_shopping_cart'),
                get_string('definefeesforcostcenters_desc', 'local_shopping_cart'),
                '', PARAM_TEXT, 30, 10)
                );
    }

    $settings->add(
            new admin_setting_configcheckbox($componentname . '/bookingfeeonlyonce',
                    get_string('bookingfeeonlyonce', 'local_shopping_cart'),
                    get_string('bookingfeeonlyonce_desc', 'local_shopping_cart'), 1)
    );

    // Setting to round percentage discounts to full integers.
    $settings->add(
            new admin_setting_configcheckbox($componentname . '/rounddiscounts',
                    new lang_string('rounddiscounts', 'local_shopping_cart'),
                    new lang_string('rounddiscounts_desc', 'local_shopping_cart'), 1));

    // Setting to enable address processing during checkout.
    $settings->add(
            new admin_setting_configmulticheckbox($componentname . '/addresses_required',
                    new lang_string('addresses_required:title', 'local_shopping_cart'),
                    new lang_string('addresses_required:desc', 'local_shopping_cart'),
                    [""],
                    [
                            'billing' => ucfirst(new lang_string('addresses:billing', 'local_shopping_cart')),
                            'shipping' => ucfirst(new lang_string('addresses:shipping', 'local_shopping_cart')),
                    ]
            ));
    // If this setting is turned on, all payment items in shopping cart need to have the same cost center.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/samecostcenter',
                get_string('samecostcenter', 'local_shopping_cart'),
                get_string('samecostcenter_desc', 'local_shopping_cart'), 0));

    // Setting to activate manual rebooking for cashier.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/manualrebookingisallowed',
                get_string('manualrebookingisallowed', 'local_shopping_cart'),
                get_string('manualrebookingisallowed_desc', 'local_shopping_cart'), 0));

    $settings->add(
        new admin_setting_configtext(
                $componentname . '/uniqueidentifier',
                get_string('uniqueidentifier', $componentname),
                get_string('uniqueidentifier_desc', $componentname),
                0,
                PARAM_INT
        )
    );

    $settings->add(
            new admin_setting_confightmleditor(
                    $componentname . '/additonalcashiersection',
                    new lang_string('additonalcashiersection', $componentname),
                    new lang_string('additonalcashiersection:description', $componentname),
                    '..',
                    PARAM_RAW
            )
    );

    $settings->add(
        new admin_setting_confightmleditor(
                $componentname . '/additonalcashiersection',
                get_string('additonalcashiersection', $componentname),
                get_string('additonalcashiersection:description', $componentname),
                '..',
                PARAM_RAW
        )
    );

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/accepttermsandconditions',
                get_string('accepttermsandconditions', 'local_shopping_cart'),
                get_string('accepttermsandconditions:description', 'local_shopping_cart'), 0));

    $settings->add(
            new admin_setting_configtextarea(
                    $componentname . '/termsandconditions',
                    get_string('termsandconditions', $componentname),
                    get_string('termsandconditions:description', $componentname),
                    null,
                    PARAM_RAW
            )
    );

    // If this setting is turned on, all customers have to pay the sellers tax template.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/owncountrytax',
                get_string('owncountrytax', 'local_shopping_cart'),
                get_string('owncountrytax_desc', 'local_shopping_cart'), 0));

    $defaultreceipthtml =
    '<table cellpadding="5" cellspacing="0" style="width: 100%; ">
    <tr>
    <td><!--<img src="url-to-your-logo"--></td>
    <td style="text-align: right">
    Datum: [[date]]<br><br>
    </td>
    </tr>
    <tr>
    <td style="font-size:1.3em; font-weight: bold;">
    <br><br>
    Booking confirmation<br>
    </td>
    </tr>
    <tr>
    <td colspan="2" style="text-align: left;">Transaction number: [[id]]</td>
    </tr>
    <tr>
    <td colspan="2" style="text-align: left;">
    [[firstname]] [[lastname]]<br>
    [[mail]]
    </td>
    </tr>
    </table>
    <br><br><br>
    <table cellpadding="5" cellspacing="0" style="width: 100%;" border="0">
    <tr style="background-color: #cccccc; padding:5px;">
    <td style="text-align: center; width: 10%;"><b>#</b></td>
    <td style="text-align: left; width: 30%;"><b>Name</b></td>
    <td style="text-align: left; width: 15%;"><b>Location</b></td>
    <td style="text-align: left; width: 10%;"><b>Day & Time</b></td>
    <td style="text-align: center; width: 10%;"><b>Total</b></td>
    <td style="text-align: center; width: 10%;"><b>Outstanding</b></td>
    <td style="text-align: center; width: 15%;"><b>Paid</b></td>
    </tr>
    [[items]]
    <tr>
    <td style="text-align: center;">[[pos]]</td>
    <td style="text-align: left;">[[name]]</td>
    <td style="text-align: left;">[[location]]</td>
    <td style="text-align: left;">[[dayofweektime]]</td>
    <td style="text-align: right;">[[originalprice]] EUR</td>
    <td style="text-align: right;">[[outstandingprice]] EUR</td>
    <td style="text-align: right;">[[price]] EUR</td>
    </tr>
    [[/items]]
    </table>
    <hr>
    <table cellpadding="5" cellspacing="0" style="width: 100%;" border="0">
    <tr>
    <td colspan="3"><b>Total sum: </b></td>
    <td style="text-align: right;"><b>[[sum]] EUR</b></td>
    </tr>
    </table>';

    $settings->add(
        new admin_setting_configtextarea(
                $componentname . '/receipthtml',
                get_string('receipthtml', $componentname),
                get_string('receipthtml:description', $componentname),
                $defaultreceipthtml,
                PARAM_RAW
        )
    );


    $fileid = 'local_shopping_cart_receiptimage';
    $name = 'local_shopping_cart/receiptimage';
    $title = get_string('receiptimage', 'local_shopping_cart');
    $description = get_string('receiptimage:description', 'local_shopping_cart');
    $opts = ['accepted_types' => ['.png', '.jpg'], 'maxfiles' => 1];
    $setting = new admin_setting_configstoredfile($name, $title, $description, $fileid, 0, $opts);
    $settings->add($setting);

    // Cancellation settings.
    $cancellationsettings = new admin_settingpage('local_shopping_cart_cancellation_settings',
        get_string('cancellationsettings', 'local_shopping_cart'));

    $cancellationsettings->add(
        new admin_setting_configtext(
                $componentname . '/cancelationfee',
                get_string('cancelationfee', $componentname),
                get_string('cancelationfee:description', $componentname),
                -1,
                PARAM_FLOAT
        )
    );

    $cancellationsettings->add(
        new admin_setting_configcheckbox($componentname . '/calculateconsumation',
            get_string('calculateconsumation', 'local_shopping_cart'),
            get_string('calculateconsumation_desc', 'local_shopping_cart'), 0));

    $fixedpercentages[-1] = get_string('nofixedpercentage', 'local_shopping_cart');
    foreach (range(5, 100, 5) as $number) {
        $fixedpercentages[$number] = "$number %";
    }

    $cancellationsettings->add(
        new admin_setting_configselect($componentname . '/calculateconsumationfixedpercentage',
            get_string('calculateconsumationfixedpercentage', 'local_shopping_cart'),
            get_string('calculateconsumationfixedpercentage_desc', 'local_shopping_cart'), -1, $fixedpercentages));

    $cancellationsettings->add(
        new admin_setting_configcheckbox($componentname . '/fixedpercentageafterserviceperiodstart',
                get_string('fixedpercentageafterserviceperiodstart', 'local_shopping_cart'),
                get_string('fixedpercentageafterserviceperiodstart_desc', 'local_shopping_cart'), 1));

        // This way of rebooking doesn't seem useful anymore...
        // Since the functions introduced in the rebooking section (see further below).
        // Therefore will be only displayed if already in use.
    if (!empty(get_config('local_shopping_cart', 'allowrebookingcredit'))) {
        $cancellationsettings->add(
                new admin_setting_configcheckbox($componentname . '/allowrebookingcredit',
                get_string('allowrebookingcredit', 'local_shopping_cart'),
                get_string('allowrebookingcredit_desc', 'local_shopping_cart'), 0));
    };

    $ADMIN->add($componentname, $cancellationsettings);

    // Cash report settings.
    $cashreportsettings = new admin_settingpage('local_shopping_cart_cashreport_settings',
        get_string('cashreportsettings', 'local_shopping_cart'));
    $cashreportsettings->add(
            new admin_setting_configcheckbox($componentname . '/cashreportshowcustomorderid',
                    get_string('cashreport:showcustomorderid', 'local_shopping_cart'),
                    get_string('cashreport:showcustomorderid_desc', 'local_shopping_cart'), 0));
    $cashreportsettings->add(
        new admin_setting_configcheckbox($componentname . '/showdailysums',
                get_string('showdailysums', 'local_shopping_cart'),
                '', 1));
    $cashreportsettings->add(
        new admin_setting_configcheckbox($componentname . '/showdailysumscurrentcashier',
                get_string('showdailysumscurrentcashier', 'local_shopping_cart'),
                '', 1));

    $cashreportsettings->add(
        new admin_setting_configtextarea(
                $componentname . '/dailysumspdfhtml',
                get_string('dailysumspdfhtml', $componentname),
                get_string('dailysumspdfhtml:description', $componentname),
                '', PARAM_RAW
        )
    );

    $limitopts = [
        '0' => get_string('nolimit', 'local_shopping_cart'),
        '10' => '10',
        '50' => '50',
        '100' => '100',
        '500' => '500',
        '1000' => '1000',
        '5000' => '5000',
        '10000' => '10000',
        '50000' => '50000',
        '100000' => '100000',
    ];
    $cashreportsettings->add(new admin_setting_configselect('local_shopping_cart/downloadcashreportlimit',
            get_string('downloadcashreportlimit', 'local_shopping_cart'),
            get_string('downloadcashreportlimitdesc', 'local_shopping_cart'),
            '10000', // Default value.
            $limitopts
    ));
    $ADMIN->add($componentname, $cashreportsettings);

    // Setting to enable taxes processing.
    $taxsettings = new admin_settingpage('local_shopping_cart_tax_settings', new lang_string('taxsettings', 'local_shopping_cart'));
    $taxsettings->add(
            new admin_setting_configcheckbox($componentname . '/enabletax',
                    new lang_string('enabletax', 'local_shopping_cart'),
                    new lang_string('enabletax_desc', 'local_shopping_cart'), 0));

    $taxprocessingenabled = get_config('local_shopping_cart', 'enabletax') == 1;
    if ($taxprocessingenabled) {
        $taxcategoriesexample = '
                <a data-toggle="collapse" href="#collapseTaxCategories" role="button"
                        aria-expanded="false" aria-controls="collapseTaxCategories">
                ' . get_string('taxcategories_examples_button', $componentname) . '
                </a>
                <div class="collapse mb-5" id="collapseTaxCategories">
                <div class="card card-body">
                Multi country multi tax categories:
                <pre class="mb-1 p-1">
                default A:0 B:0 C:0
                AT A:20 B:10 C:0
                DE A:19 B:10 C:0</pre><hr/>
                Multi tax categories, no countries:
                <pre class="mb-1 p-1">A:30 B:0</pre><hr/>
                Just one default tax:
                <pre class="mb-1 p-1">20</pre>
                </div>
                </div>';
        $taxsettings->add(
                new admin_setting_taxcategories(
                        $componentname . '/taxcategories',
                        new lang_string('taxcategories', $componentname),
                        new lang_string('taxcategories_desc', $componentname) . $taxcategoriesexample,
                        '20',
                        PARAM_TEXT
                )
        );

        $taxsettings->add(
                new admin_setting_configtext(
                        $componentname . '/defaulttaxcategory',
                        new lang_string('defaulttaxcategory', $componentname),
                        new lang_string('defaulttaxcategory_desc', $componentname),
                        "",
                        PARAM_TEXT
                )
        );
        $taxsettings->add(
                new admin_setting_configcheckbox(
                        $componentname . '/itempriceisnet',
                        get_string('itempriceisnet', $componentname),
                        get_string('itempriceisnet_desc', $componentname),
                        '1'
                )
        );
    }
    $ADMIN->add($componentname, $taxsettings);

    // Setting to enable taxes processing.
    $installmentsettings = new admin_settingpage(
        'local_shopping_cart_installment_settings',
        get_string('installmentsettings', 'local_shopping_cart')
        );
    $installmentsettings->add(
            new admin_setting_configcheckbox($componentname . '/enableinstallments',
                    get_string('enableinstallments', 'local_shopping_cart'),
                    get_string('enableinstallments_desc', 'local_shopping_cart'), 0));

    $installmentsenabled = get_config('local_shopping_cart', 'enableinstallments') == 1;
    if ($installmentsenabled) {

        $installmentsettings->add(
                new admin_setting_configtext(
                        $componentname . '/timebetweenpayments',
                        get_string('timebetweenpayments', $componentname),
                        get_string('timebetweenpayments_desc', $componentname),
                        30,
                        PARAM_INT
                )
            );

        $installmentsettings->add(
        new admin_setting_configtext(
                $componentname . '/reminderdaysbefore',
                get_string('reminderdaysbefore', $componentname),
                get_string('reminderdaysbefore_desc', $componentname),
                3,
                PARAM_INT
        )
        );
    }
    $ADMIN->add($componentname, $installmentsettings);

    defined('MOODLE_INTERNAL') || die;

    // Add a heading for the section.
    $settings->add(new admin_setting_heading('local_shopping_cart/invoicingplatformheading',
            get_string('invoicingplatformheading', 'local_shopping_cart'),
            get_string('invoicingplatformdescription', 'local_shopping_cart')
    ));

    // Add used platforms here. Currently we only support ERPNext.
    // Use lower case array keys. The key is used to create the appropriate class. Example: new erpnext_invoice($data).
    $options = [
        'noinvoice' => get_string('noselection', 'form'),
        'erpnext' => get_string('erpnext', 'local_shopping_cart'),
        'saveinvoicenumber' => get_string('saveinvoicenumber', 'local_shopping_cart'),
    ];
    $settings->add(new admin_setting_configselect('local_shopping_cart/invoicingplatform',
            get_string('chooseplatform', 'local_shopping_cart'),
            get_string('chooseplatformdesc', 'local_shopping_cart'),
            'noinvoice', // Default value.
            $options
    ));

    // Add a text field for the Base URL.
    $settings->add(new admin_setting_configtext('local_shopping_cart/startinvoicenumber',
            get_string('startinvoicenumber', 'local_shopping_cart'),
            get_string('startinvoicenumber_desc', 'local_shopping_cart'),
            "INV-10000",
            PARAM_TEXT
    ));

    // Add a text field for the Base URL.
    $settings->add(new admin_setting_configtext('local_shopping_cart/pathtoinvoices',
            get_string('pathtoinvoices', 'local_shopping_cart'),
            get_string('pathtoinvoices_desc', 'local_shopping_cart'),
            "/local_shopping_cart_invoices",
            PARAM_TEXT
    ));

    // Add a text field for the Base URL.
    $settings->add(new admin_setting_configtext('local_shopping_cart/baseurl',
            get_string('baseurl', 'local_shopping_cart'),
            get_string('baseurldesc', 'local_shopping_cart'),
            '',
            PARAM_URL
    ));

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext('local_shopping_cart/token',
            get_string('token', 'local_shopping_cart'),
            get_string('tokendesc', 'local_shopping_cart'),
            '',
            PARAM_TEXT
    ));

    $countries = get_string_manager()->get_list_of_countries(true, 'en');
    $newcountries = [];
    // English language string should also be the array key. Not the language code.
    foreach ($countries as $country) {
        $newcountries[$country] = $country;
    }
    // Create the country setting.
    $settings->add(new admin_setting_configselect(
            'local_shopping_cart/defaultcountry',
            get_string('choosedefaultcountry', 'local_shopping_cart'),
            get_string('choosedefaultcountrydesc', 'local_shopping_cart'),
            'Austria', // Default value (empty for none selected).
            $newcountries
    ));

    // Add a heading for the rebooking section.
    $settings->add(new admin_setting_heading($componentname . '/rebookingheading',
            get_string('rebookingheading', 'local_shopping_cart'),
            get_string('rebookingheadingdescription', 'local_shopping_cart')
    ));

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/allowrebooking',
                get_string('allowrebooking', 'local_shopping_cart'),
                get_string('allowrebooking_desc', 'local_shopping_cart'), 0));

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext($componentname . '/rebookingperiod',
            get_string('rebookingperiod', 'local_shopping_cart'),
            get_string('rebookingperioddesc', 'local_shopping_cart'),
            '',
            PARAM_INT
    ));

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext('local_shopping_cart/rebookingmaxnumber',
            get_string('rebookingmaxnumber', 'local_shopping_cart'),
            get_string('rebookingmaxnumberdesc', 'local_shopping_cart'),
            '',
            PARAM_INT
    ));

    $settings->add(
        new admin_setting_configtext(
                $componentname . '/rebookingfee',
                get_string('rebookingfee', $componentname),
                get_string('rebookingfee_desc', $componentname),
                0,
                PARAM_FLOAT
        )
        );

    // Add a heading for the section.
    $settings->add(new admin_setting_heading('local_shopping_cart/vatnrcheckerheading',
            get_string('vatnrcheckerheading', 'local_shopping_cart'),
            get_string('vatnrcheckerheadingdescription', 'local_shopping_cart')
            ));

    // Checkbox to show vatnr check on checkout.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/showvatnrchecker',
                get_string('showvatnrchecker', 'local_shopping_cart'),
                get_string('showvatnrcheckerdescription', 'local_shopping_cart'), 0));


    $settings->add(
        new admin_setting_configselect($componentname . '/owncountrycode',
                get_string('owncountrycode', $componentname),
                get_string('owncountrycode_desc', $componentname),
                null,
                vatnrchecker::return_countrycodes_array()
    ));

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext('local_shopping_cart/ownvatnrnumber',
            get_string('ownvatnrnumber', 'local_shopping_cart'),
            get_string('ownvatnrnumber_desc', 'local_shopping_cart'),
            '',
            PARAM_ALPHANUM
    ));

    // Add a heading for the section.
    $settings->add(new admin_setting_heading('local_shopping_cart/privacyheading',
            get_string('privacyheading', 'local_shopping_cart'),
            get_string('privacyheadingdescription', 'local_shopping_cart')
    ));

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/deleteledger',
                get_string('deleteledger', 'local_shopping_cart'),
                get_string('deleteledgerdescription', 'local_shopping_cart'), 0));
}
