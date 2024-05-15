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

use local_shopping_cart\admin_setting_taxcategories;
use local_shopping_cart\shopping_cart;

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_shopping_cart';

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $settings = new admin_settingpage('local_shopping_cart_settings', '');
    $ADMIN->add('localplugins', new admin_category($componentname, get_string('pluginname', $componentname)));
    $ADMIN->add($componentname, $settings);

    $paymentaccountrecords = $DB->get_records_sql("
        SELECT id, name
        FROM {payment_accounts}
        WHERE enabled = 1");

    $paymentaccounts = [];
    foreach ($paymentaccountrecords as $paymentaccountrecord) {
        $paymentaccounts[$paymentaccountrecord->id] = $paymentaccountrecord->name;
    }

    if (empty($paymentaccounts)) {

        $moodleurl = new moodle_url('/payment/accounts.php');
        $urlobject = new stdClass;
        $urlobject->link = $moodleurl->out(false);

        // If we have no payment accounts then show a static text instead.
        $settings->add(new admin_setting_description(
                'nopaymentaccounts',
                get_string('nopaymentaccounts', 'local_shopping_cart'),
                get_string('nopaymentaccountsdesc', 'local_shopping_cart', $urlobject)
        ));

    } else {
        // Connect payment account.
        $settings->add(
                new admin_setting_configselect(
                        $componentname . '/accountid',
                        get_string('accountid', $componentname),
                        get_string('accountid:description', $componentname),
                        null,
                        $paymentaccounts
                )
        );
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
                    get_string('maxitems', $componentname),
                    get_string('maxitems:description', $componentname),
                    10,
                    PARAM_INT
            )
    );

    // Item expiriation time in minutes.
    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/expirationtime',
                    get_string('expirationtime', $componentname),
                    get_string('expirationtime:description', $componentname),
                    15,
                    PARAM_INT
            )
    );

    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/expirationtime',
                    get_string('expirationtime', $componentname),
                    get_string('expirationtime:description', $componentname),
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
        new admin_setting_configcheckbox($componentname . '/bookingfeeonlyonce',
                get_string('bookingfeeonlyonce', 'local_shopping_cart'),
                get_string('bookingfeeonlyonce_desc', 'local_shopping_cart'), 1));

    // Setting to round percentage discounts to full integers.
    $settings->add(
            new admin_setting_configcheckbox($componentname . '/rounddiscounts',
                    get_string('rounddiscounts', 'local_shopping_cart'),
                    get_string('rounddiscounts_desc', 'local_shopping_cart'), 1));

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox($componentname . '/allowrebooking',
                get_string('allowrebooking', 'local_shopping_cart'),
                get_string('allowrebooking_desc', 'local_shopping_cart'), 0));

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
                    get_string('additonalcashiersection', $componentname),
                    get_string('additonalcashiersection:description', $componentname),
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
                ' . get_string('receipt:bookingconfirmation', 'local_shopping_cart') . '<br>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: left;">' .
                get_string('receipt:transactionno', 'local_shopping_cart') . ': [[id]]</td>
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
            <td style="text-align: left; width: 40%;"><b>' . get_string('receipt:name', 'local_shopping_cart') . '</b></td>
            <td style="text-align: left; width: 15%;"><b>' . get_string('receipt:location', 'local_shopping_cart') . '</b></td>
            <td style="text-align: left; width: 15%;"><b>' . get_string('receipt:dayofweektime', 'local_shopping_cart') . '</b></td>
            <td style="text-align: center; width: 20%;"><b>' . get_string('receipt:price', 'local_shopping_cart') . '</b></td>
        </tr>
        [[items]]
        <tr>
            <td style="text-align: center;">[[pos]]</td>
            <td style="text-align: left;">[[name]]</td>
            <td style="text-align: left;">[[location]]</td>
            <td style="text-align: left;">[[dayofweektime]]</td>
            <td style="text-align: right;">[[price]] EUR</td>
        </tr>
        [[/items]]
    </table>
        <hr>
        <table cellpadding="5" cellspacing="0" style="width: 100%;" border="0">
        <tr>
        <td colspan="3"><b>' . get_string('receipt:total', 'local_shopping_cart') . ': </b></td>
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

    $cancellationsettings->add(
        new admin_setting_configcheckbox($componentname . '/allowrebookingcredit',
                get_string('allowrebookingcredit', 'local_shopping_cart'),
                get_string('allowrebookingcredit_desc', 'local_shopping_cart'), 0));

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
    $taxsettings = new admin_settingpage('local_shopping_cart_tax_settings', get_string('taxsettings', 'local_shopping_cart'));
    $taxsettings->add(
            new admin_setting_configcheckbox($componentname . '/enabletax',
                    get_string('enabletax', 'local_shopping_cart'),
                    get_string('enabletax_desc', 'local_shopping_cart'), 0));

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
                <pre class="mb-1 p-1">default A:0 B:0 C:0
                At A:20 B:10 C:0
                De A:19 B:10 C:0</pre><hr/>
                Multi tax categories, no countries:
                <pre class="mb-1 p-1">A:30 B:0</pre><hr/>
                Just one default tax:
                <pre class="mb-1 p-1">20</pre>
                </div>
                </div>';
        $taxsettings->add(
                new admin_setting_taxcategories(
                        $componentname . '/taxcategories',
                        get_string('taxcategories', $componentname),
                        get_string('taxcategories_desc', $componentname) . $taxcategoriesexample,
                        '',
                        PARAM_TEXT
                )
        );

        $taxsettings->add(
                new admin_setting_configtext(
                        $componentname . '/defaulttaxcategory',
                        get_string('defaulttaxcategory', $componentname),
                        get_string('defaulttaxcategory_desc', $componentname),
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
        'erpnext' => get_string('erpnext', 'local_shopping_cart'),
        'noinvoice' => get_string('noselection', 'form'),
    ];
    $settings->add(new admin_setting_configselect('local_shopping_cart/invoicingplatform',
            get_string('chooseplatform', 'local_shopping_cart'),
            get_string('chooseplatformdesc', 'local_shopping_cart'),
            'noinvoice', // Default value.
            $options
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
