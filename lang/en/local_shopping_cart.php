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
 * Plugin strings are defined here.
 *
 * @package     local_shopping_cart
 * @category    string
 * @copyright   2021 Wunderbyte GmbH<info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Shopping Cart';
$string['sendpaymentbutton'] = 'Checkout';

$string['addtocart'] = 'Add to cart';

$string['mycart'] = 'My Cart';

// Settings.
$string['maxitems'] = 'Max. items in the shopping cart';
$string['maxitems:description'] = 'Set the maximum number of items for the user shopping cart';
$string['expirationtime'] = 'Set expiration time in minutes';
$string['expirationtime:description'] = 'How long should the item be in the cart?';
$string['cancelationfee'] = 'Cancelation fee';
$string['cancelationfee:description'] = 'Automatically deducted fee for cancelation by user.
                                        -1 means that cancelation by user is not possible.';
$string['addon'] = 'Set addon time';
$string['addon:description'] = 'Addition to the expiration time after checkout process is initiated';
$string['additonalcashiersection'] = 'Add text for cashier section';
$string['additonalcashiersection:description'] = 'Add HTML shortcodes or items to buy for the cashier shopping tab';
$string['accountid'] = 'Payment account';
$string['accountid:description'] = 'Choose your preferred payment account.';
$string['nopaymentaccounts'] = '<div class="text-danger font-weight-bold">No payment account exists!</div>';
$string['nopaymentaccountsdesc'] = '<p><a href="{$a->link}" target="_blank">Click here to create a payment account.</a></p>';
$string['showdescription'] = 'Show description';
$string['rounddiscounts'] = 'Round discounts';
$string['rounddiscounts_desc'] = 'Round discounts to full numbers (no decimals)';
$string['taxsettings'] = 'Shopping Cart Taxes';
$string['enabletax'] = 'Enable Tax processing';
$string['enabletax_desc'] = 'Should tax information processing be enabled for this module';
$string['taxcategories'] = 'Tax categories and their tax percentage';
$string['taxcategories_desc'] = 'Tax categories per user-country and their tax percentage<br/>i.e.: <pre>At A:20 B:10 C:0
De A:19 B:10 C:0
default A:0 B:0 C:0 </pre>';
$string['taxcategories_invalid'] = 'The given tax categories can not be parsed!';
$string['defaulttaxcategory'] = 'Default tax category';
$string['defaulttaxcategory_desc'] = 'Default tax category to be used when not explicitly declared by cart item (i.e. "A")';

// Capabilities.
$string['shopping_cart:canbuy'] = 'Can buy';
$string['shopping_cart:history'] = 'See History';
$string['shopping_cart:cashier'] = 'Is a cashier';

// File: lib.php.
$string['foo'] = 'foo';

// Cache.
$string['cachedef_cashier'] = 'Cashier cache';
$string['cachedef_cacheshopping'] = 'Shopping cache';
$string['cachedef_schistory'] = 'Shopping cart history cache';

// Errors.
$string['itemcouldntbebought'] = 'Item {$a} couldn\'t be bought';
$string['noitemsincart'] = 'There are no items in the cart';
$string['error:cachiercapabilitymissing'] = 'ERROR: You are missing the cashier capability needed to create receipts.';

// Cart.
$string['total'] = 'Total:';
$string['total_net'] = 'Total Net:';
$string['total_gross'] = 'Total Gross:';
$string['paymentsuccessful'] = 'Payment successful!';
$string['paymentdenied'] = 'Payment denied!';
$string['paymentsuccessfultext'] = 'Your payment provider has confirmed the payment. Thank you for your purchase.';
$string['backtohome'] = 'Back to home.';

$string['success'] = 'Success';
$string['pending'] = 'Pending';
$string['failure'] = 'Failure';

$string['cartisfull'] = 'Your shopping cart is full.';
$string['cartisempty'] = 'Your shopping cart is empty.';
$string['yourcart'] = 'Your shopping cart';
$string['addedtocart'] = '{$a} was added to your cart.';

// Cashier.
$string['paymentonline'] = 'online';
$string['paymentcashier'] = 'at cashier\'s office';
$string['paymentcashier:cash'] = 'with cash at cashier\'s office';
$string['paymentcashier:creditcard'] = 'with credit card at cashier\'s office';
$string['paymentcashier:debitcard'] = 'with debit card at cashier\'s office';
$string['paymentcredits'] = 'with credits';
$string['unknown'] = ' - method unknown';
$string['paid'] = 'Paid';
$string['paymentconfirmed'] = 'Payment confirmed';
$string['restart'] = 'Next customer';
$string['print'] = 'Print';
$string['previouspurchases'] = 'Previous purchases';
$string['checkout'] = '<i class="fa fa-shopping-cart" aria-hidden="true"></i> Proceed to checkout...';
$string['nouserselected'] = 'No user selected';
$string['selectuser'] = 'Select a user...';
$string['user'] = 'User...';
$string['searchforitem'] = 'Search for item...';

$string['payedwithcash'] = 'Confirm cash payment';
$string['payedwithcreditcard'] = 'Confirm credit card payment';
$string['payedwithdebitcard'] = 'Confirm debit card payment';

$string['cancelpurchase'] = 'Cancel purchase';
$string['canceled'] = 'Canceled';
$string['canceldidntwork'] = 'Cancel didn\'t work';
$string['cancelsuccess'] = 'Successfully canceled';

$string['youcancanceluntil'] = 'You can cancel until {$a}.';
$string['youcannotcancelanymore'] = 'No cancelation possible.';

$string['confirmcanceltitle'] = 'Confirm Cancelation';
$string['confirmcancelbody'] = 'Do you really want to cancel this purchase? It can\'t be undone.
 The user who purchased will get his money back of which the cancellation fee will be subtracted.';
$string['confirmcancelbodyuser'] = 'Do you really want to cancel this purchase.
                                    You\'l get the costs of your purchase minus a cancelation fee ({$a} Euro) as a credit for your next purchase.';

$string['confirmcancelallbody'] = 'Do you really want to cancel this purchase for all users?
    The following users will get their money back as credit:
    {$a->userlist}
    You can specify the cancelation fee below. It will be deduced from the original purchase price.';

$string['confirmpaidbacktitle'] = 'Confirm Payback';
$string['confirmpaidbackbody'] =
        'Do you really want to confirm that you have paid back the user her credit? This will set her credit to 0.';
$string['confirmpaidback'] = 'Confirm';

$string['confirmzeropricecheckouttitle'] = 'Pay with your credits';
$string['confirmzeropricecheckoutbody'] = 'You have enough credits to pay fully for your purchase. Do you want to proceed?';
$string['confirmzeropricecheckout'] = 'Confirm';

$string['deletecredit'] = 'Refunded';
$string['credit'] = 'Credit:';
$string['creditpaidback'] = 'Credit paid back.';

$string['cashier'] = 'Cashier';

$string['initialtotal'] = 'Price: ';
$string['usecredit'] = 'Use credit:';
$string['deductible'] = 'Deductible:';
$string['remainingcredit'] = 'Remaining credit:';
$string['remainingtotal'] = 'Price:';

$string['nopermission'] = "No permission to cancel";

// Access.php.
$string['local/shopping_cart:cashier'] = 'User has cashier rights';

// Report.
$string['reports'] = 'Reports';
$string['cashreport'] = 'Cash report';
$string['cashreport_desc'] = 'Here you get an overview over all accounting transactions.
You also can export the report in your preferred file format.';
$string['accessdenied'] = 'Access denied';
$string['nopermissiontoaccesspage'] =
        '<div class="alert alert-danger" role="alert">You have no permission to access this page.</div>';
$string['showdailysums'] = '&sum; Show daily sums...';
$string['titledailysums'] = 'Daily revenue';
$string['titledailysums:all'] = 'Total revenue';
$string['titledailysums:current'] = 'Current cashier';

// Report headers.
$string['timecreated'] = 'Created';
$string['timemodified'] = 'Completed';
$string['id'] = 'ID';
$string['identifier'] = 'TransactionID';
$string['price'] = 'Price';
$string['currency'] = 'Currency';
$string['lastname'] = 'Last name';
$string['firstname'] = 'First name';
$string['email'] = 'E-Mail';
$string['itemid'] = 'ItemID';
$string['itemname'] = 'Item name';
$string['payment'] = 'Payment method';
$string['paymentstatus'] = 'Status';
$string['gateway'] = 'Gateway';
$string['orderid'] = 'OrderID';
$string['usermodified'] = 'Modified by';

// Payment methods.
$string['paymentmethodonline'] = 'Online';
$string['paymentmethodcashier'] = 'Cashier';
$string['paymentmethodcredits'] = 'Credits';
$string['paymentmethodcreditspaidback'] = 'Credits paid back';
$string['paymentmethodcashier:cash'] = 'Cashier (Cash)';
$string['paymentmethodcashier:creditcard'] = 'Cashier (Credit card)';
$string['paymentmethodcashier:debitcard'] = 'Cashier (Debit card)';

// Payment status.
$string['paymentpending'] = 'Pending';
$string['paymentaborted'] = 'Aborted';
$string['paymentsuccess'] = 'Success';
$string['paymentcanceled'] = 'Canceled';

// Receipt.
$string['receipthtml'] = 'Put in template for receipt';
$string['receipthtml:description'] = 'You can use the following placeholders:
[[price]], [[pos]], [[name]] between [[items]] and [[/items]].
 Before and afterwards you can also use [[sum]], [[firstname]], [[lastname]], [[email]] and [[date]] (outside of [[items]] tag).
 Only use basic HTML supported by TCPDF';

$string['receiptimage'] = 'Background image for cashiers receipt';
$string['receiptimage:description'] = 'Set a background image, e.g. with logo';

// Shortcodes.
$string['shoppingcarthistory'] = 'All purchases of a given user';

// Shopping cart history card.
$string['getrefundforcredit'] = 'You can use your credits to buy a new item.';

// Form modal_cancel_all_addcredit.
$string['nousersfound'] = 'No users found';

// Discount modal.
$string['discount'] = 'Discount';
$string['applydiscount'] = 'Apply discount';
$string['adddiscounttoitem'] = 'You can reduce the price of this item either by a fixed sum or a percentage of the initial price.
    You can\'t apply both at the same time.';
$string['discountabsolute'] = 'Amount';
$string['discountabsolute_help'] = 'Reduce price by this amount, like "15". No currency.';
$string['discountpercent'] = 'Percentage';
$string['discountpercent_help'] = 'Reduce price by this percentage, like "10". Don\'t enter %-symbol';
$string['floatonly'] = 'Only numeric values (decimals) are accepted. The correct separator depends on your system.';

// Events.
$string['item_bought'] = 'Item bought';
$string['item_added'] = 'Item added';
$string['item_expired'] = 'Item expired';
$string['item_deleted'] = 'Item deleted';

// Caches.
$string['cachedef_schistory'] = 'Cache is used to store shopping cart items for users';
