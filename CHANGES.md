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
