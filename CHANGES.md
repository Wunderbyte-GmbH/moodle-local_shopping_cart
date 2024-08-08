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
