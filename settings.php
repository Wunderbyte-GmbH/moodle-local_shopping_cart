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
use local_shopping_cart\local\checkout_process\items_helper\vatnumberhelper;
use local_shopping_cart\shopping_cart;

defined('MOODLE_INTERNAL') || die();

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $settings = new admin_settingpage(
        'local_shopping_cart_settings',
        get_string('generalsettingspagetitle', 'local_shopping_cart')
    );
    $ADMIN->add('localplugins', new admin_category(
        'local_shopping_cart',
        new lang_string('pluginname', 'local_shopping_cart')
    ));
    $ADMIN->add('local_shopping_cart', $settings);

    $paymentaccountrecords = helper::get_payment_accounts_to_manage(context_system::instance(), false);

    $paymentaccounts = [];
    foreach ($paymentaccountrecords as $paymentaccountrecord) {
        $paymentaccounts[$paymentaccountrecord->get('id')] = $paymentaccountrecord->get('name');
    }

    if (empty($paymentaccounts)) {
        $moodleurl = new moodle_url('/payment/accounts.php');
        $urlobject = new stdClass();
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
                'local_shopping_cart/accountid',
                new lang_string('accountid', 'local_shopping_cart'),
                new lang_string('accountid:description', 'local_shopping_cart'),
                null,
                $paymentaccounts
            )
        );

        // Allow chosing individual paymentaccount for each item.
        $settings->add(
            new admin_setting_configcheckbox(
                'local_shopping_cart/allowchooseaccount',
                new lang_string('allowchooseaccount', 'local_shopping_cart'),
                new lang_string('allowchooseaccount_desc', 'local_shopping_cart'),
                0
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
            'local_shopping_cart/globalcurrency',
            get_string('globalcurrency', 'local_shopping_cart'),
            get_string('globalcurrencydesc', 'local_shopping_cart'),
            'EUR',
            $currencies
        )
    );

    // Max items in cart.
    $settings->add(
        new admin_setting_configtext(
            'local_shopping_cart/maxitems',
            new lang_string('maxitems', 'local_shopping_cart'),
            new lang_string('maxitems:description', 'local_shopping_cart'),
            10,
            PARAM_INT
        )
    );

    // Item expiriation time in minutes.
    $settings->add(
        new admin_setting_configtext(
            'local_shopping_cart/expirationtime',
            new lang_string('expirationtime', 'local_shopping_cart'),
            new lang_string('expirationtime:description', 'local_shopping_cart'),
            15,
            PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_shopping_cart/bookingfee',
            get_string('bookingfee', 'local_shopping_cart'),
            get_string('bookingfee_desc', 'local_shopping_cart'),
            0,
            PARAM_FLOAT
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/bookingfeevariable',
            get_string('bookingfeevariable', 'local_shopping_cart'),
            get_string('bookingfeevariable_desc', 'local_shopping_cart'),
            0
        )
    );
    $bookingfeevariable = get_config('local_shopping_cart', 'bookingfeevariable') == 1;
    if ($bookingfeevariable) {
        $settings->add(
            new admin_setting_configtextarea(
                'local_shopping_cart/definefeesforcostcenters',
                get_string('definefeesforcostcenters', 'local_shopping_cart'),
                get_string('definefeesforcostcenters_desc', 'local_shopping_cart'),
                '',
                PARAM_TEXT,
                30,
                10
            )
        );
    }

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/bookingfeeonlyonce',
            get_string('bookingfeeonlyonce', 'local_shopping_cart'),
            get_string('bookingfeeonlyonce_desc', 'local_shopping_cart'),
            1
        )
    );

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/rounddiscounts',
            new lang_string('rounddiscounts', 'local_shopping_cart'),
            new lang_string('rounddiscounts_desc', 'local_shopping_cart'),
            1
        )
    );

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/roundrefundamount',
            new lang_string('roundrefundamount', 'local_shopping_cart'),
            new lang_string('roundrefundamount_desc', 'local_shopping_cart'),
            1
        )
    );

    // Setting to enable address processing during checkout.
    $settings->add(
        new admin_setting_configmulticheckbox(
            'local_shopping_cart/addresses_required',
            new lang_string('addresses_required:title', 'local_shopping_cart'),
            new lang_string('addresses_required:desc', 'local_shopping_cart'),
            [""],
            [
                            'billing' => ucfirst(new lang_string('addresses:billing', 'local_shopping_cart')),
                            'shipping' => ucfirst(new lang_string('addresses:shipping', 'local_shopping_cart')),
                    ]
        )
    );

        // If no costcenter is specified in credits, they can be redeemed for items from this costcenter.
        $settings->add(
            new admin_setting_configtext(
                'local_shopping_cart/defaultcostcenterforcredits',
                get_string('defaultcostcenterforcredits', 'local_shopping_cart'),
                get_string('defaultcostcenterforcredits_desc', 'local_shopping_cart'),
                '',
                PARAM_TEXT
            )
        );

        $settings->add(
            new admin_setting_configtextarea(
                'local_shopping_cart/costcenterstrings',
                get_string('costcenterstrings', 'local_shopping_cart'),
                get_string('costcenterstrings_desc', 'local_shopping_cart'),
                '',
                PARAM_RAW
            )
        );

    // Setting to activate manual rebooking for cashier.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/manualrebookingisallowed',
            get_string('manualrebookingisallowed', 'local_shopping_cart'),
            get_string('manualrebookingisallowed_desc', 'local_shopping_cart'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_shopping_cart/prolongedpaymenttime',
            get_string('prolongedpaymenttime', 'local_shopping_cart'),
            get_string('prolongedpaymenttime_desc', 'local_shopping_cart'),
            0,
            PARAM_INT
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'local_shopping_cart/uniqueidentifier',
            get_string('uniqueidentifier', 'local_shopping_cart'),
            get_string('uniqueidentifier_desc', 'local_shopping_cart'),
            0,
            PARAM_INT
        )
    );

    // Setting to activate manual rebooking for cashier.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/alwaysanswerwithsuccessinverifypurchase',
            get_string('alwaysanswerwithsuccessinverifypurchase', 'local_shopping_cart'),
            get_string('alwaysanswerwithsuccessinverifypurchase_desc', 'local_shopping_cart'),
            0
        )
    );

    $settings->add(
        new admin_setting_confightmleditor(
            'local_shopping_cart/additonalcashiersection',
            new lang_string('additonalcashiersection', 'local_shopping_cart'),
            new lang_string('additonalcashiersection:description', 'local_shopping_cart'),
            '..',
            PARAM_RAW
        )
    );

    $settings->add(
        new admin_setting_confightmleditor(
            'local_shopping_cart/additonalcashiersection',
            get_string('additonalcashiersection', 'local_shopping_cart'),
            get_string('additonalcashiersection:description', 'local_shopping_cart'),
            '..',
            PARAM_RAW
        )
    );

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/accepttermsandconditions',
            get_string('accepttermsandconditions', 'local_shopping_cart'),
            get_string('accepttermsandconditions:description', 'local_shopping_cart'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_shopping_cart/termsandconditions',
            get_string('termsandconditions', 'local_shopping_cart'),
            get_string('termsandconditions:description', 'local_shopping_cart'),
            null,
            PARAM_RAW
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/acceptadditionalconditions',
            get_string('acceptadditionalconditions', 'local_shopping_cart'),
            get_string('acceptadditionalconditions:description', 'local_shopping_cart'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_shopping_cart/additionalconditions',
            get_string('additionalconditions', 'local_shopping_cart'),
            get_string('additionalconditions:description', 'local_shopping_cart'),
            null,
            PARAM_RAW
        )
    );

    // If this setting is turned on, all customers have to pay the sellers tax template.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/owncountrytax',
            get_string('owncountrytax', 'local_shopping_cart'),
            get_string('owncountrytax_desc', 'local_shopping_cart'),
            0
        )
    );

    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /* $defaultreceipthtml =
    '<table cellpadding="5" cellspacing="0" style="width: 100%; ">
    <tr>
    <td><!--<img src="url-to-your-logo"--></td>
    <td style="text-align: right">
    Date: [[date]]<br><br>
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
    </table>'; */

    $settings->add(
        new admin_setting_configtextarea(
            'local_shopping_cart/receipthtml',
            get_string('receipthtml', 'local_shopping_cart'),
            get_string('receipthtml:description', 'local_shopping_cart'),
            '', /* $defaultreceipthtml */
            PARAM_RAW
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/extrareceipts',
            get_string('extrareceipts', 'local_shopping_cart'),
            '',
            0
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/showextrareceiptstousers',
            get_string('showextrareceiptstousers', 'local_shopping_cart'),
            '',
            0
        )
    );

    // phpcs:ignore Squiz.PHP.CommentedOutCode.Found
    /* $defaultextrareceipthtml =
        '<table cellpadding="5" cellspacing="0" style="width: 100%; ">
        <tr>
        <td><!--<img src="url-to-your-logo"--></td>
        <td style="text-align: right">
        Date: [[date]]<br><br>
        </td>
        </tr>
        <tr>
        <td style="font-size:1.3em; font-weight: bold;">
        <br><br>
        Extra receipt (no invoice)<br>
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
        <table cellpadding="5" cellspacing="0" style="width: 100%;" border="1px solid #ccc">
        <tr style="background-color: #cccccc; padding:5px;">
        <td style="text-align: center; width: 10%;"><b>#</b></td>
        <td style="text-align: left; width: 40%;"><b>Name</b></td>
        <td style="text-align: center; width: 25%;"><b>Paid</b></td>
        <td style="text-align: center; width: 25%;"><b>Credits (added/removed)</b></td>
        </tr>
        [[items]]
        <tr>
        <td style="text-align: center;">[[pos]]</td>
        <td style="text-align: left;">[[name]]</td>
        <td style="text-align: right;">[[price]] EUR</td>
        <td style="text-align: right;">[[credits]] EUR</td>
        </tr>
        [[/items]]
        </table>
        <br>
        <br>
        Signature:
        <div style="border: 1px solid #000;">
        <br><br><br>
        </div>'; */

    $settings->add(
        new admin_setting_configtextarea(
            'local_shopping_cart/extrareceiptshtml',
            get_string('extrareceiptshtml', 'local_shopping_cart'),
            get_string('extrareceiptshtmldesc', 'local_shopping_cart'),
            '', /* $defaultextrareceipthtml */
            PARAM_RAW
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'local_shopping_cart/cancelconfirmationshtml',
            get_string('cancelconfirmationshtml', 'local_shopping_cart'),
            get_string('cancelconfirmationshtmldesc', 'local_shopping_cart'),
            '',
            PARAM_RAW
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/showdisabledcheckoutbutton',
            get_string('showdisabledcheckoutbutton', 'local_shopping_cart'),
            '',
            1
        )
    );

    // Cancellation settings.
    $cancellationsettings = new admin_settingpage(
        'local_shopping_cart_cancellation_settings',
        get_string('cancellationsettings', 'local_shopping_cart')
    );

    $cancellationsettings->add(
        new admin_setting_configtext(
            'local_shopping_cart/cancelationfee',
            get_string('cancelationfee', 'local_shopping_cart'),
            get_string('cancelationfee:description', 'local_shopping_cart'),
            -1,
            PARAM_FLOAT
        )
    );

    $cancellationsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/calculateconsumation',
            get_string('calculateconsumation', 'local_shopping_cart'),
            get_string('calculateconsumation_desc', 'local_shopping_cart'),
            0
        )
    );

    $fixedpercentages[-1] = get_string('nofixedpercentage', 'local_shopping_cart');
    foreach (range(5, 100, 5) as $number) {
        $fixedpercentages[$number] = "$number %";
    }

    $cancellationsettings->add(
        new admin_setting_configselect(
            'local_shopping_cart/calculateconsumationfixedpercentage',
            get_string('calculateconsumationfixedpercentage', 'local_shopping_cart'),
            get_string('calculateconsumationfixedpercentage_desc', 'local_shopping_cart'),
            -1,
            $fixedpercentages
        )
    );

    $cancellationsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/fixedpercentageafterserviceperiodstart',
            get_string('fixedpercentageafterserviceperiodstart', 'local_shopping_cart'),
            get_string('fixedpercentageafterserviceperiodstart_desc', 'local_shopping_cart'),
            1
        )
    );

        // This way of rebooking doesn't seem useful anymore...
        // Since the functions introduced in the rebooking section (see further below).
        // Therefore will be only displayed if already in use.
    if (!empty(get_config('local_shopping_cart', 'allowrebookingcredit'))) {
        $cancellationsettings->add(
            new admin_setting_configcheckbox(
                'local_shopping_cart/allowrebookingcredit',
                get_string('allowrebookingcredit', 'local_shopping_cart'),
                get_string('allowrebookingcredit_desc', 'local_shopping_cart'),
                0
            )
        );
    };

    $ADMIN->add('local_shopping_cart', $cancellationsettings);

    // Cash report settings.
    $cashreportsettings = new admin_settingpage(
        'local_shopping_cart_cashreport_settings',
        get_string('cashreportsettings', 'local_shopping_cart')
    );
    $cashreportsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/cashreportshowcustomorderid',
            get_string('cashreport:showcustomorderid', 'local_shopping_cart'),
            get_string('cashreport:showcustomorderid_desc', 'local_shopping_cart'),
            0
        )
    );
    $cashreportsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/showdailysums',
            get_string('showdailysums', 'local_shopping_cart'),
            '',
            1
        )
    );
    $cashreportsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/showdailysumscurrentcashier',
            get_string('showdailysumscurrentcashier', 'local_shopping_cart'),
            '',
            1
        )
    );

    $cashreportsettings->add(
        new admin_setting_configtextarea(
            'local_shopping_cart/dailysumspdfhtml',
            get_string('dailysumspdfhtml', 'local_shopping_cart'),
            get_string('dailysumspdfhtml:description', 'local_shopping_cart'),
            '',
            PARAM_RAW
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
    $cashreportsettings->add(new admin_setting_configselect(
        'local_shopping_cart/downloadcashreportlimit',
        get_string('downloadcashreportlimit', 'local_shopping_cart'),
        get_string('downloadcashreportlimitdesc', 'local_shopping_cart'),
        '10000', // Default value.
        $limitopts
    ));
    $ADMIN->add('local_shopping_cart', $cashreportsettings);

    // Shopping cart history settings.
    $shortcodeschistorysettings = new admin_settingpage(
        'local_shopping_cart_shortcodeschistorysettings',
        get_string('shortcodeschistorysettings', 'local_shopping_cart')
    );
    $shortcodeschistorysettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/schistoryshowserviceperiod',
            get_string('schistoryshowserviceperiod', 'local_shopping_cart'),
            get_string('schistoryshowserviceperiod_desc', 'local_shopping_cart'),
            0
        )
    );
    $shortcodeschistorysettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/schistorysections',
            get_string('schistorysections', 'local_shopping_cart'),
            get_string('schistorysections_desc', 'local_shopping_cart'),
            0
        )
    );
    if (get_config('local_shopping_cart', 'schistorysections')) {
        $months = [
            1 => get_string('january', 'local_wunderbyte_table'),
            2 => get_string('february', 'local_wunderbyte_table'),
            3 => get_string('march', 'local_wunderbyte_table'),
            4 => get_string('april', 'local_wunderbyte_table'),
            5 => get_string('may', 'local_wunderbyte_table'),
            6 => get_string('june', 'local_wunderbyte_table'),
            7 => get_string('july', 'local_wunderbyte_table'),
            8 => get_string('august', 'local_wunderbyte_table'),
            9 => get_string('september', 'local_wunderbyte_table'),
            10 => get_string('october', 'local_wunderbyte_table'),
            11 => get_string('november', 'local_wunderbyte_table'),
            12 => get_string('december', 'local_wunderbyte_table'),
        ];
        $shortcodeschistorysettings->add(new admin_setting_configselect(
            'local_shopping_cart/schistorysectionsstartingmonth',
            get_string('schistorysectionsstartingmonth', 'local_shopping_cart'),
            '',
            1, // Default value: January.
            $months
        ));
        $possibleintervals = [
            1 => get_string('schistorysectionsintervalannually', 'local_shopping_cart'),
            2 => get_string('schistorysectionsintervalsemiannually', 'local_shopping_cart'),
            4 => get_string('schistorysectionsintervalquarterly', 'local_shopping_cart'),
            12 => get_string('schistorysectionsintervalmonthly', 'local_shopping_cart'),
        ];
        $shortcodeschistorysettings->add(new admin_setting_configselect(
            'local_shopping_cart/schistorysectionsinterval',
            get_string('schistorysectionsinterval', 'local_shopping_cart'),
            '',
            1, // Default value: Annually (yearly).
            $possibleintervals
        ));
        if (class_exists('mod_booking\booking')) {
            $shortcodeschistorysettings->add(
                new admin_setting_configcheckbox(
                    'local_shopping_cart/schistorysectionssortbybookingcoursestarttime',
                    get_string('schistorysectionssortbybookingcoursestarttime', 'local_shopping_cart'),
                    get_string('schistorysectionssortbybookingcoursestarttime_desc', 'local_shopping_cart'),
                    0
                )
            );
        }
    }
    $ADMIN->add('local_shopping_cart', $shortcodeschistorysettings);

    // Setting to enable taxes processing.
    $taxsettings = new admin_settingpage('local_shopping_cart_tax_settings', new lang_string('taxsettings', 'local_shopping_cart'));
    $taxsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/enabletax',
            new lang_string('enabletax', 'local_shopping_cart'),
            new lang_string('enabletax_desc', 'local_shopping_cart'),
            0
        )
    );

    $taxprocessingenabled = get_config('local_shopping_cart', 'enabletax') == 1;
    if ($taxprocessingenabled) {
        $taxcategoriesexample = '
                <a data-toggle="collapse" data-bs-toggle="collapse" href="#collapseTaxCategories" role="button"
                        aria-expanded="false" aria-controls="collapseTaxCategories">
                ' . get_string('taxcategories_examples_button', 'local_shopping_cart') . '
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
                'local_shopping_cart/taxcategories',
                new lang_string('taxcategories', 'local_shopping_cart'),
                new lang_string('taxcategories_desc', 'local_shopping_cart') . $taxcategoriesexample,
                '20',
                PARAM_TEXT
            )
        );

        $taxsettings->add(
            new admin_setting_configtext(
                'local_shopping_cart/defaulttaxcategory',
                new lang_string('defaulttaxcategory', 'local_shopping_cart'),
                new lang_string('defaulttaxcategory_desc', 'local_shopping_cart'),
                "",
                PARAM_TEXT
            )
        );
        $taxsettings->add(
            new admin_setting_configcheckbox(
                'local_shopping_cart/itempriceisnet',
                get_string('itempriceisnet', 'local_shopping_cart'),
                get_string('itempriceisnet_desc', 'local_shopping_cart'),
                '1'
            )
        );
    }
    $ADMIN->add('local_shopping_cart', $taxsettings);

    // Setting to enable taxes processing.
    $installmentsettings = new admin_settingpage(
        'local_shopping_cart_installment_settings',
        get_string('installmentsettings', 'local_shopping_cart')
    );
    $installmentsettings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/enableinstallments',
            get_string('enableinstallments', 'local_shopping_cart'),
            get_string('enableinstallments_desc', 'local_shopping_cart'),
            0
        )
    );

    $installmentsenabled = get_config('local_shopping_cart', 'enableinstallments') == 1;
    if ($installmentsenabled) {
        $installmentsettings->add(
            new admin_setting_configtext(
                'local_shopping_cart/timebetweenpayments',
                get_string('timebetweenpayments', 'local_shopping_cart'),
                get_string('timebetweenpayments_desc', 'local_shopping_cart'),
                30,
                PARAM_INT
            )
        );

        $installmentsettings->add(
            new admin_setting_configtext(
                'local_shopping_cart/reminderdaysbefore',
                get_string('reminderdaysbefore', 'local_shopping_cart'),
                get_string('reminderdaysbefore_desc', 'local_shopping_cart'),
                3,
                PARAM_INT
            )
        );
    }
    $ADMIN->add('local_shopping_cart', $installmentsettings);

    // Add a heading for the section.
    $settings->add(new admin_setting_heading(
        'local_shopping_cart/invoicingplatformheading',
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
    $settings->add(new admin_setting_configselect(
        'local_shopping_cart/invoicingplatform',
        get_string('chooseplatform', 'local_shopping_cart'),
        get_string('chooseplatformdesc', 'local_shopping_cart'),
        'noinvoice', // Default value.
        $options
    ));

    // Add a text field for the Base URL.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/startinvoicenumber',
        get_string('startinvoicenumber', 'local_shopping_cart'),
        get_string('startinvoicenumber_desc', 'local_shopping_cart'),
        "INV-10000",
        PARAM_TEXT
    ));

    // Add a text field for the Base URL.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/pathtoinvoices',
        get_string('pathtoinvoices', 'local_shopping_cart'),
        get_string('pathtoinvoices_desc', 'local_shopping_cart'),
        "/local_shopping_cart_invoices",
        PARAM_TEXT
    ));

    // Add a text field for the Base URL.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/baseurl',
        get_string('baseurl', 'local_shopping_cart'),
        get_string('baseurldesc', 'local_shopping_cart'),
        '',
        PARAM_URL
    ));

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/token',
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
    $settings->add(new admin_setting_heading(
        'local_shopping_cart/rebookingheading',
        get_string('rebookingheading', 'local_shopping_cart'),
        get_string('rebookingheadingdescription', 'local_shopping_cart')
    ));

    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/allowrebooking',
            get_string('allowrebooking', 'local_shopping_cart'),
            get_string('allowrebooking_desc', 'local_shopping_cart'),
            0
        )
    );

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/rebookingperiod',
        get_string('rebookingperiod', 'local_shopping_cart'),
        get_string('rebookingperioddesc', 'local_shopping_cart'),
        '',
        PARAM_INT
    ));

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/rebookingmaxnumber',
        get_string('rebookingmaxnumber', 'local_shopping_cart'),
        get_string('rebookingmaxnumberdesc', 'local_shopping_cart'),
        '',
        PARAM_INT
    ));

    $settings->add(
        new admin_setting_configtext(
            'local_shopping_cart/rebookingfee',
            get_string('rebookingfee', 'local_shopping_cart'),
            get_string('rebookingfee_desc', 'local_shopping_cart'),
            0,
            PARAM_FLOAT
        )
    );

    // Add a heading for the section.
    $settings->add(new admin_setting_heading(
        'local_shopping_cart/vatnrcheckerheading',
        get_string('vatnrcheckerheading', 'local_shopping_cart'),
        get_string('vatnrcheckerheadingdescription', 'local_shopping_cart')
    ));

    // Checkbox to show vatnr check on checkout.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/showvatnrchecker',
            get_string('showvatnrchecker', 'local_shopping_cart'),
            get_string('showvatnrcheckerdescription', 'local_shopping_cart'),
            0
        )
    );

    // Checkbox setting to only allow bookings, if a valid VAT number is provided.
    $settings->add(new admin_setting_configcheckbox(
        'local_shopping_cart/onlywithvatnrnumber',
        get_string('onlywithvatnrnumber', 'local_shopping_cart'),
        get_string('onlywithvatnrnumber_desc', 'local_shopping_cart'),
        0,
    ));

    $settings->add(
        new admin_setting_configselect(
            'local_shopping_cart/owncountrycode',
            get_string('owncountrycode', 'local_shopping_cart'),
            get_string('owncountrycode_desc', 'local_shopping_cart'),
            null,
            vatnumberhelper::get_countrycodes_array()
        )
    );

    // Add a text field for the Token.
    $settings->add(new admin_setting_configtext(
        'local_shopping_cart/ownvatnrnumber',
        get_string('ownvatnrnumber', 'local_shopping_cart'),
        get_string('ownvatnrnumber_desc', 'local_shopping_cart'),
        '',
        PARAM_ALPHANUM
    ));

    // Add a heading for the section.
    $settings->add(new admin_setting_heading(
        'local_shopping_cart/privacyheading',
        get_string('privacyheading', 'local_shopping_cart'),
        get_string('privacyheadingdescription', 'local_shopping_cart')
    ));

    // Setting to round percentage discounts to full integers.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_shopping_cart/deleteledger',
            get_string('deleteledger', 'local_shopping_cart'),
            get_string('deleteledgerdescription', 'local_shopping_cart'),
            0
        )
    );
}
