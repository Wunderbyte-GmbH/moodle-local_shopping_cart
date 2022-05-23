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

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_shopping_cart';

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $settings = new admin_settingpage('local_shopping_cart_settings', get_string('pluginname', 'local_shopping_cart'));
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
                $componentname .'/accountid',
                get_string('accountid', $componentname),
                get_string('accountid:description', $componentname),
                null,
                $paymentaccounts
            )
        );
    }


    // Max items in cart.
    $settings->add(
        new admin_setting_configtext(
            $componentname .'/maxitems',
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
        new admin_setting_confightmleditor(
            $componentname . '/additonalcashiersection',
            get_string('additonalcashiersection', $componentname),
            get_string('additonalcashiersection:description', $componentname),
            '..',
            PARAM_RAW
        )
    );
}
