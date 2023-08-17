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
use PgSql\Lob;

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
                    $componentname . '/cancelationfee',
                    get_string('cancelationfee', $componentname),
                    get_string('cancelationfee:description', $componentname),
                    -1,
                    PARAM_FLOAT
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
        new admin_setting_configcheckbox($componentname . '/calculateconsumation',
                get_string('calculateconsumation', 'local_shopping_cart'),
                get_string('calculateconsumation_desc', 'local_shopping_cart'), 0));

    // Setting to round percentage discounts to full integers.
    $settings->add(
            new admin_setting_configcheckbox($componentname . '/rounddiscounts',
                    get_string('rounddiscounts', 'local_shopping_cart'),
                    get_string('rounddiscounts_desc', 'local_shopping_cart'), 1));

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

    $fileid = 'local_shopping_cart_receiptimage';
    $name = 'local_shopping_cart/receiptimage';
    $title = get_string('receiptimage', 'local_shopping_cart');
    $description = get_string('receiptimage:description', 'local_shopping_cart');
    $opts = array('accepted_types' => array('.png', '.jpg'), 'maxfiles' => 1);
    $setting = new admin_setting_configstoredfile($name, $title, $description, $fileid, 0, $opts);
    $settings->add($setting);

    // Cash report settings.
    $cashreportsettings = new admin_settingpage('local_shopping_cart_cashreport_settings',
        get_string('cashreportsettings', 'local_shopping_cart'));
    $cashreportsettings->add(
            new admin_setting_configcheckbox($componentname . '/cashreportshowcustomorderid',
                    get_string('cashreport:showcustomorderid', 'local_shopping_cart'),
                    get_string('cashreport:showcustomorderid_desc', 'local_shopping_cart'), 0));
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
                <a data-toggle="collapse" href="#collapseExample" role="button"
                        aria-expanded="false" aria-controls="collapseExample">
                ' . get_string('taxcategories_examples_button', $componentname) . '
                </a>
                <div class="collapse mb-5" id="collapseExample">
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
    }
    $ADMIN->add($componentname, $taxsettings);
}
