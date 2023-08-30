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
* Bugfix: CSS - fix image size on cashier.php for cache payment confirmation.

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
