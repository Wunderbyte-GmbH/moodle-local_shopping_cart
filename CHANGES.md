## Version 2.0.1 (2025121000)
* Improvement: Display the target user's name on the button.
* Improvement: Various adjustments for Bootstrap 5 readiness.
* Improvement: Make sure cart items of user does not contain items of buy for user.
* Bugfix: Remove items from the cart after payment confirmation on the cashier page.

## Version 2.0.0 (2025120101)
* Improvement: Now supporting Moodle 4.5+ (skipped support for Moodle 4.1 - 4.4).

## Version 1.0.25 (2025120100)
* New feature: Add header and footer to receipts with <header> and <footer> tags.
* Improvement: Check whether the user has the cashier capability when trying to get prices for others.
* Improvement: Retrieve the userid from the URL parameters and use it for the get_price external service.
* Improvement: Store and retrieve the user ID using the $_POST and $_GET superglobals.
* Improvement: Call buy_for_user in a separate external class to ensure the user ID is available during the request.
* Improvement: Get paramforuserid as an argument from shortcode to get user ID from URL.
* Improvement: Set the user ID instead of -1 in the reinit() function on the cashier page after applying a discount.
* Improvement: Use actforuser::get_foruserid to cover getting userid from argument or optional param.
* Improvement: Use actforuser::get_foruserid to render the shopping cart history of a user and add a security check.

## Version 1.0.24 (2025111000)
* Improvement: Separate template for installments, so it can be reused at other places.
* Bugfix: If for some reason cmid is empty, we re-create the option settings from DB.
* Bugfix: Fix exception when tax not enabled, but prices and installments are.

## Version 1.0.23 (2025103100)
* New feature: Bugfix: New VAT checker in checkout process.
* Improvement: Implemented new user-flow, using conditional vatnumber checker.
* Improvement: Moodle 5 - Fix background color for icons on the cashier page.
* Improvement: Fix left join of ledger and schistory and add number of items to cash report.
* Bugfix: Fix errors in shopping cart history list (items were lost because of faulty timestamp conversions).

## Version 1.0.22 (2025101400)
* Improvement: Display Payment Pluginnames in cash report table and history list
* Bugfix: Add userstocancel to potential booking event

## Version 1.0.21 (2025101300)
* Bugfix: Correctly interpret serviceperiodend for rebookings
* Bugfix: Correctly sort schistory items
* Linting: as per 2025-10 Moodle standards update

## Version 1.0.20 (2025100100)
* Improvement: Make Feedback on checkout page more granular
* Bugfix: Revert wrong implementation of service period check
* Bugfix: Avoid unwanted rebooking possibility to rebook
* Bugfix: Fix customer and address creation in ERPNext
* Bugfix: Fix VAT problems for ERPNext invoice
* Bugfix: Fix cashier page with shortcodes
* Bugfix: Display correct info about mandatory VAT checker
* Bugfix: Fix get_history_items webservice

## Version 1.0.19 (2025091900)
* Improvement: Show real number of items (also multipliable).
* Bugfix: Set book for user on each iteration of price bookforuser.
* Bugfix: Take out buyforuser singleton.
* Bugfix: Remove duplicated setting expirationtime.

## Version 1.0.18 (2025091700)
* Improvement: Improve Javascript performance.
* Improvement: Avoid unnecessary rendering of shortcode.
* Improvement: Code quality - remove multiple empty lines.
* Improvement: Use reinit() instead of removing button node, also use already existing return_item_from_history function.
* Improvement: Performance: Improve buy for user
* Improvement: Update version to support Moodle 5.
* Bugfix: Clean null values in upgrade.php before changing field precision and setting to notnull.
* Bugfix: Add missing setType for name.
* Bugfix: Make sure that a cancelled purchase cannot be rebooked anymore.
* Bugfix: Don't allow cancellation during rebooking process.
* Bugfix: Moodle 5: manual processing controls on the cashier page.
* Tests: Create SoapClientMock class to solve Moodle 5.0/phpunit 11 issues.
* Tests: Fix PHPUnit tests and namespaces for Moodle 5.0.
* Tests: Create separate github action to support Moodle 5.

## Version 1.0.17 (2025090400)
* Improvement: Localize prices with format_float.
* Improvement: Use format_float in cash report and improve code quality.
* Improvement: Add [[nritems]] placeholder to receipt.
* Improvement: Add multipliable items.
* Improvement: Apply localized number formatter in convertPricesToNumberFormat.
* Improvement: Static method shopping_cart::save_used_installments_state().
* Improvement: No amount, if only one item or no booking option.
* Improvement: Harden code for sorting date for shopping cart history sections.
* Bugfix: No "item deleted" alerts when increasing or decreasing number of items.
* Bugfix: Fix invoice for format_float.
* Bugfix: PHP 7.4 compatibility.
* Bugfix: Make sure the increase–decrease works correctly with the component callbacks (booking).
* Bugfix: Shopping cart price multiplication fix.

## Version 1.0.16 (2025081900)
* Improvement: Add new fields to make items multipliable

## Version 1.0.14 (2025072800)
* Bugfix: Fix collapsibles of new structured shopping cart history list feature.
* Bugfix: Fixed annual (yearly) sections in new shopping cart history list collapsible sections feature.
* Bugfix: ERPNext invoice creation

## Version 1.0.13 (2025072400)
* Improvement: Add addressbreaks placeholder to receipt
* Bugfix: convert strings to floats in receipt

## Version 1.0.12 (2025072100)
* New feature: enable safer pay provider
* Improvement: user experience during checkout process

## Version 1.0.11 (2025071600)
* Improvement: Termsandcondition clickable for mobile.
* Bugfix: Newly introduced values of history items (timecreated, timemodified, serviceperiodstart, serviceperiodend) were missing in execute_returns() function of get_history_items webservice.
* Bugfix: Replace hardcoded strings.
* Bugfix: Localize country names.
* Bugfix: Remove addfont roboto.
* Bugfix: Fix json encoding for ERPnext invoices.

## Version 1.0.10 (2025071300)
* New feature: Structure shopping cart history into dynamic sections (quarterly, semi-annually...),
including new settings for collapsible sections in shoppingcarthistory shortcode.
* New feature: Placeholders for company, total gross, total net and vat.
* Improvement: New function to get company name.
* Improvement: Introduce new error type: LOCAL_SHOPPING_CART_CARTPARAM_PAYMENTACCOUNT.
* Bugfix: Avoid permanent fallback on hardcoded receipt.
* Bugfix: Don't print error in check_for_ongoing_payment when not in debug mode.
* Bugfix: Replace hardcoded values in error message.
* Bugfix: Prevent accessing nonexistent objectkey.
* Bugfix: Add empty check for serviceperiod.
* Bugfix: Require to avoid Exception - Warning: Undefined array key "itemname" for unprivileged roles.
* Bugfix: No paymentaccountid being saved on form submit.

## Version 1.0.9 (2025060400)
* Improvement: Accessibility - Remove alt texts for decorative images in shopping cart - as they should not appear in screen readers.
* Improvement: Fix ERPNext invoice creation.
* Bugfix: Address ids & vat number were stored in history but not ledger table.
* Bugfix: Fix unique identifier check and vatnr field precision.
* Bugfix: Fix country name transfer to ERPNext.
* Bugfix: Add context_helper class with fix_page_context function to fix page context for shortcodes.
* Bugfix: Check for empty vatnumber instead of null to fix test.

## Version 1.0.8 (2025052102)
* Bugfix: Fix faulty upgrade logic.

## Version 1.0.7 (2025052101)
* Improvement: Switch to reusable moodle-plugin-ci.yml workflow.
* Bugfix: Deal with textarea profile fields in receipt.
* Bugfix: When payments are ongoing, we avoid mixing up our payables via the check_for_onfoign_payments.

## Version 1.0.6 (2025052100)
* Bugfix: Fix bug when required no identifier being issued properly.
* Tests: Fix broken tests.

## Version 1.0.5 (2025052000)
* Improvement: Add Tax countrycode to vatid number
* Improvement: Add company to address, make adress editable
* Improvement: Change vat number to precision 24
* Bugfix: Fix task names
* Bugfix: Billing address needs to be linked after creating new customer
* Bugfix: Fix settings and remove duplicate code
* Bugfix: Avoid users seening checkouts of other users
* Bugfix: Reintroduce check_for_ongoing_payment function on checkout page
* Bugfix: Fix Moodle Code Checker errors
* Bugfix: Fix upgrade and install.xml
* Tests: Temporarily take out failing unit tests

## Version 1.0.4 (2025050800)
* Bugfix: Fix Erp Transmission

## Version 1.0.3 (2025050500)
* Bugfix: Make sure rebookingfee can never be rebooked.
* Bugfix: Prevent missing context when using format_string in webservice.
* Bugfix: Adding workaround for grunt stale issue.

## Version 1.0.2 (2025040400)
* Bugfix: Fixed a bug where modal form did not open because description was null.

## Version 1.0.1 (2025032700)
* Bugfix: Fixes for extra receipts in shopping cart history.

## Version 1.0.0 (2025032000)
* Improvement: Use persistent storage to harden the checkout process

## Version 0.9.59 (2025031200)
* Improvement: Make sure that cancelling is not possible for items marked for rebooking.
* Bugfix: Correctly apply consumption
* Bugfix: Make sure that missing terms and conditions do not break the checkout process.
* Bugfix: Also fix additional terms and conditions.
* Tests: Include time_mock class and first test which makes use of it (rough draft, to be cleaned)

## Version 0.9.58 (2025030501)
* Bugfix: Add new reservations table for persistant cart

## Version 0.9.57 (2025030500)
* Bugfix: Make sure userid is applied correctly for cashier checkout.

## Version 0.9.56 (2025030400)
* Improvement: userinfocard now supports aliases userinfo and userinformation.
* Improvement: Add payment area to shopping cart.
* Improvement: Userinfocard shortcode for shopping cart.
* Bugfix: Global $USER uses id (not userid).
* Bugfix: Fix get_user_data and test.
* Bugfix: Fix tests for Moodle 4.2.
* Bugfix: No button for cancel confirmation if only one item in cart was cancelled.
* Bugfix: Make sure that delete_item task is only scheduled backwards and cart expiration time is set accordingly.
* Test: delete redundant test.
* Test: Next step to fix address tests, but still not working.
* Test: add mocking of soap client to the tests.

## Version 0.9.55 (2025021700)
* Improvement: use modal factory checkout manager.
* Improvement: apply taxes if owncountry.
* Bugfix: Display credit bookings correctly even when tax is enabled.
* Bugfix: Make sure combination of vat & taxes is applied correctly.
* Bugfix: Avoid error on empty itemlist.
* Bugfix: Fix some errors in vat checker.
* Bugfix: use cartstore cache for saving values for tax & vat calculation.
* Bugfix: fix option values external classes.
* Bugfix: Add addresses to ledger in all cases.
* Bugfix: Add missing params variable when creating invoice.
* Bugfix: zero price is correctly attached when switching between prepages.
* Bugfix: Fix reload_history webservice by making properties optional.
* Test: finalize behat adjustments to the new UI.
* Test: adjust behat (delays).
* Test: adjust betah to updated UI of vatnr form.
* Test: an attempt to adjust shopping_cart_taxes_VAT_addresses.feature to the UI changes.
* Test: an attempt to fix behat in the shopping_cart_taxes_VAT.feature.

## Version 0.9.54 (2025021102)
* Improvement: strings localized, mandatory icon.
* Improvement: Clean checkout template.
* Improvement: deselect address after deleting.
* Improvement: Show terms and conditions optionally.
* Improvement: Delete address.
* Improvement: Refactored class structure.
* Improvement: Body process implementation.
* Improvement: Add missing language string for cachedef.
* Improvement: vatnrchecker get cached data.
* Improvement: Cache address data.
* Improvement: vat nr checker update.
* Improvement: New item setup.
* Bugfix: No error on empty item_list.
* Bugfix: Render checkout manager page when not address is there.
* Bugfix: checkout button, render order.
* Bugfix: only with vat number setting.
* Bugfix: Avoid missing userid message when only showing success.
* Bugfix: Don't use $componentname when it's not defined.
* Bugfix: Make sure that task delete_item is not duplicated involuntarily.
* Bugfix: Don't delete items by duplicated task when we extend expiration time.
* Bugfix: Fix return value for verify purchase.
* Bugfix: Fix sorting of shopping cart history items on cashier.php.
* Bugfix: Don't apply usercredit when input was changed in last moment.
* Bugfix: Show correct items in checkout page.
* Test: setup test environment.

## Version 0.9.53 (2025021100)
* Bugfix: Show correct items in checkout page.

## Version 0.9.52 (2025020700)
* Improvement: Improvement: Add setting to always return status 1 in verify purchase
* Improvement: Add credit changes to shopping cart history for individual users #125

## Version 0.9.51 (2025012900)
* Improvement: Show cancel confirmations in shopping cart history. #124

## Version 0.9.50 (2025012200)
* New feature: Cancel confirmations in cash report (#122).
* Improvement: Allow to increase price MUSI-573 #112
* Improvement: Enable cashier to change value of downpayment MUSI-590 #113
* Improvement: Linting (php 7.4 compatibility).
* Bugfix: Don't use implicit int conversion #121
* Bugfix: Missing entity name could create a problem.
* Tests: new behat Scenario: Shopping Cart cashier: use installment and change downpayment (#121)
* Tests: adjust behat to test downpayment along with discount (#121)
* Tests: Add new unit test to test purchase and cancelation with discounts (to be extended).
* Tests: Fix behat and PHPDoc.

## Version 0.9.49 (2025011500)
* Improvement: When no credits, it's 0 instead of empty in invoice.
* Improvement: Do not hide receipts for cancelled items. #119
* Bugfix: Fix erpnext.yml.
* Bugfix: Correctly display time in receipt

## Version 0.9.48 (2024122000)
* Improvement: Add possibility to differentiate between receipts and extra receipts.
* Improvement: Add default html for extrareceiptshtml (#116).

## Version 0.9.47 (2024121800)
* New feature: Modify expiration of item in cart #114.
* Improvement: Add minutes & hour to coursestarttime.
* Bugfix: Fix grunting.

## Version 0.9.46 (2024121200)
* Improvement: add labels to placeholders in checkout success
* Bugfix: New placeholders [[semester]], [[semestername]], [[semestershort]] now really work
* Improvement: apply daily sums to report table

## Version 0.9.45 (2024121100)
* New feature: Create receipts for ledger records without identifier (for example for credits paid back)
* Improvement: New placeholder [[credits]]
* Improvement: Add new semester placeholders to invoices (receipts)

## Version 0.9.44 (2024120501)
* Bugfix: Make sure we always get rid of the old costcenter

## Version 0.9.43 (2024120500)
* Improvement: Save address infos correctly to ledger.
* Improvement: Display location of first session for options without global location.
* Improvement: Show merchantref and customorderid at the right places. (Wunderbyte-GmbH/moodle-paygw_payone#5)
* Improvement: Add semester placeholder (Wunderbyte-GmbH/moodle-mod_booking#660).
* Bugfix: Bugfixes for receipts.
* Bugfix: Fix lang string nopaymentaccountsdesc.
* Bugfix: Show address also in credit card checkout.
* Bugfix: Bugfix when wanting to consume credits with booking fee and non default costcenter #107.
* Bugfix: Fix SQL for customorderid that broke behat. (Wunderbyte-GmbH/moodle-paygw_payone#5)

## Version 0.9.42 (2024112500)
* Improvement: Add support for Moodle 4.5.
* Bugfix: Fix lang string.
* Bugfix: Fix bug with merchantref (#97).
* Bugfix: Store receipt correctly (#98).
* Bugfix: No 2x for money icon in navbar.

## Version 0.9.41 (2024112000)
* Improvement: Add dependency for new version 2024112000 of Wunderbyte Table.

## Version 0.9.40 (2024111900)
* Improvement: Add filter for serviceperiod and numbers in itemname to report (Wunderbyte-GmbH/moodle-mod_booking#665).
* Improvement: Add serviceperiod to download of report (Wunderbyte-GmbH/moodle-mod_booking#665).
* Improvement: Update item price for in pricecontainer.
* Improvement: When there is a merchantref, we show it instead of tid #91.
* Improvement: Aggregate all installment receipts in shopping cart history. #92.
* Bugfix: Fix for [[coursestarttime]] placeholder in invoice.
* Bugfix: Fix icons of installments (too big on checkout.php).
* Bugfix: Fix wrong paymentstatus for installments in shopping cart history #94.

## Version 0.9.39 (2024110601)
* Improvement: Add tests for costcenters
* Bugfix: Correct application of costcenters
* Bugfix: Return 0 quota if quota is disabled

## Version 0.9.38 (2024110600)
* Bugfix: Cast datatype in receipt
* Bugfix: Apply given quota to cancel all user function

## Version 0.9.37 (2024102500)
* Improvement: Support coursestarttime in receipt
* Bugfix: Use separate capability for searching users
* Bugfix: Don't change buyfor user when we are in a webservice environment

## Version 0.9.36 (2024102200)
* Improvement: Set back buyforuser on every page except cashier.
* Bugfix: Fix costcenter check with rebookingfee
* Bugfix: storedpaymentaccountid can not block on empty cart
* Bugfix: Make sure one can see other users receipt only with cashier capability

## Version 0.9.35 (2024101702)
* Bugfix: Function to check for rebookings not breaking checkout
* Bugfix: Correctly trigger item_notbought event

## Version 0.9.34 (2024101700)
* Improvement: Disable rebooking without new item and display modal about checkout failures
* Improvement: New phpunit test class to test credits

## Version 0.9.33 (2024100700)
* Improvement: Server callback verify_purchase improvement, triggers now transaction_complete
* Bugfix: User Serverside callback only for checking, not actual verification

## Version 0.9.32 (2024100400)
* Bugfix: Make sure that default payment account on new option is actually the default one.
* Bugfix: Fix image size on checkout page
* Bugfix: If no rebooking information is stored, we don't allow rebooking.

## Version 0.9.31 (2024100200)
* Bugfix: Enable correct language switching
* Bugfix: Fix localized navbar cart on page reload

## Version 0.9.30 (2024093000)
* Improvement: Use localized cart
* Bugfixes: for costcenters and credits

## Version 0.9.29 (2024092400)
* Bugfix: Refresh history after purchase in cashier.php
* Bugfix: Display credits for customers in shoppingcarthistory

## Version 0.9.28 (2024092000)
* Improvement: Make sure history list is correctly sorted
* Bugfix: Correct handling of used credits in multi ccostcenter environment.
* Bugfix: correctly attribute Credit after rebooking

## Version 0.9.27 (2024091900)
* Improvement: Fallback on empty costcenter
* Bugfix: Costcenterlabel
* Bugfix: Add missing variable
* Bugfix: Make sure costcenter also works with single costcenter

## Version 0.9.26 (2024091800)
* Bugfix: Don't show payback buttons to normal users

## Version 0.9.25 (2024091701)
* Bugfix: Correct paymentstatus for installmentpayments
* Bugfix: Switch between different costcenters led to wrong credit usage.

## Version 0.9.24 (2024091700)
* Improvement: Add description about coursestart used for installments
* Bugfix: Pay back via credit manager works again. Payback is streamlined.
* Bugfix: Fix bug when no costcenters are used
* Tests: Adapt behat tests to new creditmanager
* Tests: Fix behat test for new creditmanager functionality

## Version 0.9.23 (2024091600)
* Improvement: Add refund with cash and refund via transfer buttons to costcenter (sytling still needed)
* Bugfix: Handling for empty costcenter
* Bugfix: Number format for the credit on the cachier page

## Version 0.9.22 (2024091300)
* Improvement: Add example to costcenter strings
* Bugfix: Use credits of right costcenter

## Version 0.9.21 (2024091200)
* Bugfix: handle multiple costcenters

## Version 0.9.20 (2024091000)
* Bugfix: As the item at this place might come from the ledger, we need a fallback to retrieve the serviceperiod.

## Version 0.9.19 (2024090600)
* Improvement: Add verify purchase webservice

## Version 0.9.18 (2024090200)
* Improvement: use dynamic form for user selector
* Bugfix: syntax
* Bugfix: reload history has now optional values
* Bugfix: Small bugfixes in behat tests
* Linting

## Version 0.9.17 (2024082900)
* Feature: Show costcenters including translations
* GH-572 Add settings for costcenters
* GH-572 save costcenters for credits

## Versiion 0.9.16 (2024082600)
* Bugfix: Don't create invoices when they are not really turned on
* Bugfix: Protect js agains missing Dom elements
* GitHub: fix mustache template

## Version 0.9.15 (2024082000)
*  GitHub: linting & adjustments

## Version 0.9.14 (2024080600)
* Improvement: Add new field for costcenter in credits
* Improvement: Enable events for booking rules
* Improvement: Add support for Moodle 4.4

## Version 0.9.13 (2024070600)
* Bugfix: Add installmentdata to receipt

## Version 0.9.12 (2024070600)
* Improvement: Enable events for plugin mod_booking rules function
* Improvement: Sort strings in alphabetical order to meet moodle 4.4 code style regulations

## Version 0.9.11 (2024072400)
* Bugfix: Safe shopping_cart history when no vatntnumber provided

## Version 0.9.10 (2024071900)
* Bugfix: Avoid double credit reduction in some cases
* Bugfix: Default values for missing keys in webservice
* Bugfix: Remove condition leading to incorrect userid

## Version 0.9.9 (2024071800)
* Improvement: enable filtered text display in additionalcashiersection
* Improvement: Only show rebooking item if it's the same different costcenter
* Improvement: Only accept rebooking items with with the same costcenter like in the cart
* Improvement: Ajax reload for Shopping Cart history
* Improvement: Enable variable bookingfees depending on costcenter
* Bugfix: Fix different costcenter blocker

## Version 0.9.8 (2024070400)
* Improvement: Add more automatic tests

## Version 0.9.7 (2024062800)
* Feature: Add addresses for correct tax handling
* Feature: Add support for automatic Invoice creation via ERP Next
* Feature: Add VAt nr Checker

## Version 0.9.6 (2024061200)
* Bugfix: Small fix concerning paymentaccountid.

## Version 0.9.5 (2024061000)
* Improvement: Include billing and shipping adress.
* Improvement: Setting to use prices as gross or net.
* Improvement: Include tax categories and VAT checker.
* Improvement: Add possibility to change payment accounts for individual items.
* Bugfix: Price calculations for rebookings.
* Bugfix: Price calculations for subbookings.

## Version 0.9.3 (2024052101)
* Bugfix: Fix incomplete ledger records.

## Version 0.9.0 (2024052101)
* Bugfixes: Add creation date to ledger.
* Bugfixes: Installment checkbox.
* Bugfixes: Correct price calculation.
* Bugfixes: Duplicated treatment for rebooking.

## Version 0.8.9 (2024051501)
* Improvement: Add rebookingfee.
* Bugfixes: A couple of rebooking bugs.

## Version 0.8.8 (2024051500)
* Improvement: Add Installments.
* Bugfix: Missing ledger.

## Version 0.8.7 (2024050800)
* Bugfix: Show expirationdate correctly.
* Bugfix: Make sure we correctly replace placeholter numbers.

## Version 0.8.6 (2024043000)
* New Feature: Add placeholder [[cashandcards]] for sum of non-online payments to daily sum.

## Version 0.8.5 (2024042600)
* New feature: Unfinished new feature for installment payments (do not use yet!).
It has been added for testing purposes.
* Improvement: Big design change - we switched to new price modifiers to manipulate prices if needed.
* Improvement: Nice styling of installments page.
* Improvement: Better styling of testing page which has been renamed to demo.php.
* Improvement: Lots of new tests and improvements to overall code quality.

## Version 0.8.4 (2024041000)
* Improvement: Users in cashier can now be searched by ID also.
* Improvement: Allow search for full user ID on cashier's page.

## Version 0.8.3 (2024040200)
* New feature: Define your own HTML template for the daily sums PDF download.
* Improvement: Add function to check allowedcancel without db call.
* Improvement: Add possibility to fetch last historyitem information when no historyid is given.
* Improvement: Make add to cart button accessible via TAB.

## Version 0.8.2 (2024032500)
* Improvement: Keep itemid in ledger on rebooking #65.

## Version 0.8.1 (2024032000)
* Improvement: Fix Filter for new wunderbyte table api.
* Improvement: Add new schistorid column to ledger.
* Bugfix: Avoid "Limit" for oracle support.
* Bugfix: Fix for #63.
* Bugfix: Second fix for #63 - we need to get currency from config!
* Bugfix: Make sure we have booking fee when deleting rebooking item.

## Version 0.8.0 (2024031800)
* New feature: New setting to limit the cash report download file to a certain number of rows.
* Improvement: don't add booking fee on items with price 0.

## Version 0.7.9 (2024031401)
* Improvement: Show full terms and conditions.
* Improvement: Better styling of checkout page.

## Version 0.7.8 (2024031400)
* New feature: Booked items can now be marked for rebooking and be rebooked into other items.
This feature can be turned on by activating the setting 'local_shopping_cart | allowrebooking'.
* Improvement: Add missing language strings.
* Improvement: Better strings for free payments (when total price is 0).
* Bugfix: Add missing cache definitions.
* Bugfix: Fix some styles for images and icons.

## Version 0.7.7 (2024030600)
* Bugfix: Don't fail on space in img url for item picture.
* Bugfix: Set service period on null for booking option without date.

## Version 0.7.6 (2024022300)
* New Feature: Rebooking credit - If you activate rebooking credit, a user will get refunded the cancelation and booking fee
if (s)he cancels an item within the cancelation period and books another item.
* Improvement: Add "Item not bought" event when component feedback fails.
* Improvement: deliver_order on successfull payment if it failed for some reason.
* Improvement: Check Feedback with success.
* Bugfix: Function allow_add_item_to_cart needs to respect availability conditions.

## Version 0.7.5 (2024021900)
* New feature: Add possibility to custom user profile fields to receipt.
* Improvement: Use "usecredit" state even when cache was purged.
* Bugfix: Fix install.xml lines which were not compatible with upgrade.php.
* Bugfix: When cancelling from cashier, we want to stay on page for selected user.
* Bugfix: New wunderbyte table doesn't allow constructor.

## Version 0.7.4 (2024020900)
* Improvement: Slightly smaller addtocart button.
* Improvement: Show a modal when trying to book a fully booked item. Also reload the page after pressing the OK button.
* Bugfix: Fix cost center check and reinit() cart area when trying to book an item that is already in cart.

## Version 0.7.3 (2024013000)
* Bugfix: Make sure credit is always a float value and no string.

## Version 0.7.2 (2024012400)
* New feature: Add PDF download for daily sums, total sum and possibility to turn off daily sums and sums of current cashier.

## Version 0.7.1 (2024012200)
* Improvement: Localization, better description and default HTML for booking receipt.

## Version 0.7.0 (2024011900)
* Bugfix: Add setType to form.
* Bugfix: Avoid error because of missing param when cart is full.
* Improvement: Add placeholders for location and dayofweektime to receipt.
* Improvement: Better feedback when "isallowed" callback returns false.

## Version 0.6.10 (2024011600)
* Bugfix: Fix error when item is already in cart.

## Version 0.6.9 (2024011500)
* Improvement: New interface for transaction_complete and check if payment classes implement it.
* Bugfix: Fix call of transaction_complete so that it works with payunity, mpay24 and unigraz.
* Bugfix: When cashier cancels, the items of the selected user should be removed from cart.

## Version 0.6.8 (2024011000)
* Improvement: Empty cart after cashier cancels, layout improvements and new discount icon.
* Improvement: Improve Param types in external services, add context validation.
* Improvement: More distinct css.
* Improvement: Add privacy class #58.
* Improvement: Add setting to decide wether to delete ledger on deletion request.
* Improvement: Accessibility: Add aria-name for shopping cart icon.
* Improvement: Rename some ids for better code quality.
* Improvement: Add debugging modes to cash report.
* Bugfix: Fix a bug where we returned success instead of error.
* Bugfix: Fix css classes.

## Version 0.6.7 (2023120500)
* Improvement: Added support for Moodle 4.3 and PHP 8.2.
* Bugfix: All plugin constants must start with uppercase frankenstyle prefix.

## Version 0.6.6 (2023112200)
* New feature: Create receipt PDF directly from shopping cart history list.
* Improvement: Better receipts.
* Improvement: Better string for internal annotation when booking option is cancelled.
* Bugfix: Reintroduce lost textarea for receipthtml.

## Version 0.6.5 (2023111300)
* New feature: Add payment type to manual rebookings (will be added to annotation).
* Improvement: On removing an item from the cart, we reload wb tables, if there are any.
* Improvement: Code quality: Always use int and bool - never integer or boolean.
* Improvement: We hide the checkout button on checkout.php if the cart is empty.
* Bugfix: Fix namespaces.
* Bugfix: Fix keys that were wrongly required in webservices.
* Bugfix: Fix selector for items in cart, so disabling of addtocart button works correctly.

## Version 0.6.4 (2023110200)
* New feature: Introduce a new setting to avoid booking of items with different cost centers.

## Version 0.6.3 (2023102000)
* Improvement: Lots of new behat tests, fixes for GitHub actions etc.

## Version 0.6.2 (2023101300)
* Bugfix: Fix ERPNext invoice creation.
* Bugfix: Decide if we want to use credit when cached value already got lost.
* Bugfix: We cannot use singleton service in shopping cart. Use core_user::get_user instead.

## Version 0.6.1 (2023100900)
* New feature: Credits manager now supports individual credits corrections.
* Improvement: Add Event to see when new entries are created in shopping cart history (and a payment process is started).
* Bugfix: Fix FontAwesome6 issues.
* Bugfix: $cachedrawdata was always set to true, so delete_item_task was never created!
* Bugfix: Fix bugs with itemid 0 ledger records (cash transfer, cash transaction ...).
* Bugfix: Fix linting errors and refactor behat tests.
* Bugfix: Fix icons in cashier for Moodle 4.2.

## Version 0.6.0 (2023092700)
* New feature: Add new button to pay back credits via bank transfer.
* New feature: Credits manager to add or pay back individual amounts of credits.
* Improvement: Don't automatically hide notifications.
* Bugfix: Where exception in history via allowed to cancel to blocked booking.
* Bugfix: Make report pageable to allow very big requests.
* Bugfix: Avoid double payout of credits during very heavy server load.
* Bugfix: Make sure the cart does not expire during checkout process.
* Bugfix: Reloading an item a second time should not set back expiration time.
* Bugfix: We don't want to delete booking fee individually from cart, only in combination with other items.
* Bugfix: Prevent duplicates in shopping cart history.
* Bugfix: Make sure we have the fee before checkout.
* Bugfix: Add FontAwesome 6 compatibility for Moodle 4.2.
* Bugfix: Remove duplicated date in history_item.

## Version 0.5.10 (2023091800)
* Bugfix: By wrong browser date delete all items could be triggered in permanence.

## Version 0.5.9 (2023091501)
* Bugfix: Add rounding on cancel credits.

## Version 0.5.8 (2023091500)
* Test: Fix 2 scenarios (issue with notifications interception).
* Test: Allow test items to be canceled.
* Bugfix: Moodle exception: Exception - Class "local_shopping_cart\shopping_cart\context_system" not found.

## Version 0.5.7 (2023091401)
* Bugfix: Add missing implementation of allowed_to_cancel in service provider of shopping cart.

## Version 0.5.6 (2023091400)
* New feature: Create invoices via remote platform.
* New feature: Do not allow cancellation of items if the items themselves do not allow cancellation (via callback).
* Bugfix: Fix bugs in cash report.
* Bugfix: Make sure we have the buyforuserid.
* Bugfix: Cashier gets normal credit for her own cancelled bookings.
* Bugfix: Make sure to throw an error if the identifier is not in db or not correct.

## Version 0.5.5 (2023090600)
**New features:**
* New feature: Transfer cash from one cashier to another cashier.
* New feature: New setting to calculate consumation with fixed percentage and setting to apply only after service period start.

**Improvements:**
* Improvement: If setting 'cashreportshowcustomorderid' is active, then we also show the custom order ID in shopping cart history.

**Bugfixes:**
* Bugfix: Fix broken behat tests for green Github actions.
* Bugfix: Schistorycache has to be casted to array.

## Version 0.5.4 (2023083000)
**New features:**
* New feature: Add net/gross settings for item prices.

**Improvements:**
* Improvement: Better styling for terms and conditions.

**Bugfixes:**
* Bugfix: When using more than one gateway itemid (identifier) of openorders entry is not unique. So fix that in report SQL.
* Bugfix: When canceluntil date was missing, users could not book for themselves - also fixed strings.
* Bugfix: Fix cashing and identifier errors on checkout page.

## Version 0.5.3 (2023082300)
**New features:**
* New feature: Introduce a new setting to show custom orderid instead of gateway orderid.

**Improvements:**
* Improvement: Remove unnecessary event call of item_deleted at the wrong place.

**Bugfixes:**
* Bugfix: Fix bookingfee check if cashier books for other user.
* Bugfix: Daily sums need to sum up prices from local_shopping_cart_ledger (not local_shopping_cart_history).

## Version 0.5.2 (2023081100)
**New features:**
* New feature: Shopping cart now fully supports Mpay24 payment gateway.

**Bugfixes:**
* Bugfix: Bugfix: sql_cast_to_char is only supported from Moodle 4.1 onwards.
* Bugfix: Fix grunting js files.
* Bugfix: Bugfix: Fix broken cash report SQL.
* Bugfix: sql_cast_to_char is not supported for the first version of Moodle 4.1 so use ">" instead of ">=".
* Bugfix: Missing cache definitions.

**Tests and code quality:**
* Code quality: Lots of tiny improvements for GitHub actions.
* Code quality: Added small adjustments for Moodle 4.2 compatibility.
* Tests: New classes have been added in order to create payment gateway instances directly in DB for tests.
* Tests: Refactoring of all behat tests to use new generator class which creates payment gateway instance in DB.

## Version 0.5.1 (2023071200)
**Improvements:**
* Improvement: Improvement: Add ID to cashier's user selector and some layout improvements.

**Bugfixes:**
* Bugfix: 2-digit price format lost on page reload / checkout navigation.
* Bugfix: Prevent selection of deleted users by cashier.

**Tests:**
* Behat: Adjusting shopping_cart test (a) settings separated for better optimization; (b) to use 2-digits prices.
* Behat: fix: replace "Choose" with "Continue".
* Adjust github workflow to Moodle 401 (402) versions only.

## Version 0.5.0 (2023062300)
**Bugfixes:**
* Bugfix: Introduce new functions to convert prices to strings with 2 decimals right before rendering.
* Bugfix: Missing isset check for credits.
* Bugfix: Fix errors when payment gateway is missing or not supported and show a warning message if so.
* Bugfix: Mixed DE/EN strings.

## Version 0.4.9 (2023062200)
**Improvements:**
* Improvement: New money icon to directly access cashier's desk from navigation.
* Improvement: New config setting to activate manual rebooking at cashier's desk.
* Improvement: Add a check to prevent duplicates in ledger (cash report) table.
* Improvement: Switch cash report to wunderbyte table.
* Improvement: Add local_wunderbyte_table as dependency to the moodle-plugin-ci.yml.
* Improvement: Add gateway to fulltextsearch.
* Improvement: Force 2 decimal digits always visible in prices.

**Bugfixes:**
* Bugfix: Use new globalcurrency config setting instead of hardcoded 'EUR'.
* Bugfix: Correct way of manual rebooking.
* Bugfix: Add dependency for wunderbyte table.
* Bugfix: Fixes for PHP 8.1 compatibility.
* Bugfix: CSS - fix image size on cashier.php for cash payment confirmation.

## Version 0.4.8 (2023061603)
**New features:**
* New feature: New feature to allow manual rebooking for cashier (with annotation or order id).

**Improvements:**
* Improvement: Fix cashier typos.
* Improvement: New behat tests and fixes for Github actions.
* Improvement: Mustache - fix JSON according to code changes.
* Improvement: GitHub - add 3 templates to ignore list

**Bugfixes:**
* Bugfix: Remove call of function error_occured_for_identifier as it leads to missing items in deliver_order function of service_provider.php.

## Version 0.4.7 (2023060900)
**Improvements:**
* Improvement: Correctly store partly used credits in cash report (ledger table).
* Improvement: Show cash report above history on cashier page.
* Improvement: Introduce new global currency config setting.

**Bugfixes:**
* Bugfix: Do not write into credits table when credits are not used.
* Bugfix: Ledger table may never be updated, prevent duplicates.
* Bugfix: Commented out "flexcashpayment" as it was not implemented anyways.
* Bugfix: Fix sql to check for open orders.
* Bugfix: Avoid wasting identifiers in success routine of checkout.php.

## Version 0.4.6 (2023052400)
**Bugfixes:**
* Bugfix: Avoid duplicated entries in cash report.

## Version 0.4.5 (2023052400)
**Bugfixes:**
* Bugfix: Where cashier lost userid in combination with booking fee

## Version 0.4.3 (2023052200)
**Improvements:**
* Improvement: Add cancel without callback on component
* Improvement: Add unique identifier table for better configurability of cart identifier

## Version 0.4.2 (2023040600)
**Bugfixes:**
* Bugfix: ID was not unique in SQL because there can be multiple orderids for the same item.
* Bugfix: Only add event listener to cashout button if button is present.
* Bugfix: Add missing inserts to ledger table when shopping cart history gets updated.
* Bugfix: Add uniqueid to SQL in report.php so that we get no duplicates.
* Bugfix: Correctly update ledger table (prices only if successful).
* Bugfix: Fix a bug were credits were deduced twice which caused errors with balance checks.

**Improvements:**
* Improvement: Add missing modulename string.
* Improvement: Add class to cash report button.
* Improvement: Improved the way we retrieve balance and added validations.
* Improvement: Added area to log message of delete_item_task.

## Version 0.4.1 (2023032100)
**Bugfixes:**
* Bugfix: In shopping cart popover we always want black text even if navbar text color is set to white.

## Version 0.4.0 (2023032000)
**New features:**
* New feature: Booking fee can be activated either for each purchase process or only once per user.

## Version 0.3.9 (2023031300)
**Improvements:**
* Don't react on blocked item clicks.
* Disabled cart items can't be clicked to unload item anymore.
* No propagation stop on click on cart item.

## Version 0.3.8 (2023022000)
**Improvements:**
* First steps to support subbookings (in combination with Booking plugin).
* Behat tests and improved code style.
* Get rid of unnecessary functions.
* Added "nojs" (no JavaScript) functionality to template.
* Add unload item by click on disabled item.
* Use standard moodle autocomplete to search users.
* Layout: Add margin to cashout card.
* Add possibility to unload simultaneously connected cart items via service_provider.

**Bugfixes:**
* Avoid an error when user was not logged in.
* Fix error: when shortcode is used to call this function when not logged in.
* Several small fixes.
* Fix legacy problem of non area adhoc tasks.
* Fix js for shopping cart.
* Fix error which sometimes interrupted ad-hock task/delete_item_task.php.
* Fix name in services.php get_quota_consumed.

## Version 0.3.7 (2023011200)
**Improvements:**
* Lots of bugfixes.
* Improved tax support.
* Better code quality.
* Added cashout functionality.
* Behat tests.

## Version 0.3.6 (2022121500)
**Improvements:**
* Add area functionality for more than one item from every component
* Add function to calculate paid back price via consumption

## Version 0.3.5 (2022120500)
**Improvements:**
* Add index for local_shopping_cart_credits for better performance.
* Better string for submit button ("choose").
* Code quality (linting).

## Version 0.3.4 (2022112900)
**New features:**
* Added tax support.

## Version 0.3.3 (2022112300)
**Improvements:**
* Small design improvements and linting.

## Version 0.3.2 (2022111600)
**Improvements:**
* More robust js working

## Version 0.3.1 (2022103100)
**Improvements:**
* Speed Improvements
* Add and fix behat tests

## Version 0.2.4 (2022081400)
**Improvements:**
* Add discount functionality
