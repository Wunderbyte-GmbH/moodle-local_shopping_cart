@local @local_shopping_cart @javascript

Feature: Cashier manage credits with costcenters enabled in shopping cart
  In order to manage credits with costcenters enabled as a cashier I add / reduce / refund credits for students.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname     | email                       |
      | user1    | Username1  | Test        | toolgenerator1@example.com  |
      | user2    | Username2  | Test        | toolgenerator2@example.com  |
      | teacher  | Teacher    | Test        | toolgenerator3@example.com  |
      | manager  | Manager    | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "core_payment > payment accounts" exist:
      | name           |
      | Account1       |
    And the following "local_shopping_cart > payment gateways" exist:
      | account  | gateway | enabled | config                                                                                |
      | Account1 | paypal  | 1       | {"brandname":"Test paypal","clientid":"Test","secret":"Test","environment":"sandbox"} |
    And the following "local_shopping_cart > plugin setup" exist:
      | account  |
      | Account1 |

  @javascript
  Scenario: Shopping cart costcenter credits: cashier correct (add) credits for user and refund some
    Given the following config values are set as admin:
      | config                      | value       | plugin              |
      | samecostcenterforcredits    | 1           | local_shopping_cart |
      | defaultcostcenterforcredits | CostCenter1 | local_shopping_cart |
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency | costcenter  |
      | user1 | 21     | EUR      |             |
      | user1 | 32     | EUR      | CostCenter1 |
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "21" in the "[data-costcenter=\"No costcenter\"].costcenterlabel .credit_total" "css_element"
    And I should see "32" in the "[data-costcenter=\"CostCenter1\"].costcenterlabel .credit_total" "css_element"
    ## Add credits to the CostCenter1
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "16.35"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter1"
    And I set the field "Reason" to "add credits CostCenter1"
    And I press "Save changes"
    And I wait until the page is ready
    ## Add "no costcenter" credits
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "17"
    And I set the field "Costcenter to which the credit is assigned to" to ""
    And I set the field "Reason" to "add no costcenter credits"
    And I press "Save changes"
    And I wait until the page is ready
    ## Add credits to the CostCenter2
    And I click on "Credits manager" "button"
    And I wait until the page is ready
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "25.53"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter2"
    And I set the field "Reason" to "add credits CostCenter2"
    And I press "Save changes"
    And I wait until the page is ready
    ## Verify credits per costcenters
    And I should see "38.00" in the "[data-costcenter=\"No costcenter\"].costcenterlabel .credit_total" "css_element"
    And I should see "48.35" in the "[data-costcenter=\"CostCenter1\"].costcenterlabel .credit_total" "css_element"
    And I should see "25.53" in the "[data-costcenter=\"CostCenter2\"].costcenterlabel .credit_total" "css_element"
    ## Payback credits of the CostCenter2 by cache via credit manager
    And I click on "Credits manager" "button"
    And I wait until the page is ready
    And I set the field "What do you want to do?" to "Pay back credits"
    And I set the field "Payment method" to "Credits paid back by cash"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter2"
    And I set the field "Reason" to "Pay back by cash CostCenter2"
    And I press "Save changes"
    And I wait until the page is ready
    ## Payback credits of the "No costcenter" by thansfer directly
    And I click on "Refunded via transfer" "button" in the "[data-costcenter=\"No costcenter\"].shopping_cart_history_payback_buttons" "css_element"
    And I wait until the page is ready
    And I should see "This will set her credit to 0" in the ".modal-body" "css_element"
    And I click on "button[data-action=\"save\"]" "css_element"
    And I wait until the page is ready
    ## Verify credits per costcenters and report
    Then I should see "48.35" in the "[data-costcenter=\"CostCenter1\"].costcenterlabel .credit_total" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-38.00" in the "#cash_report_table_r1" "css_element"
    And I should see "Credits paid back by transfer" in the "#cash_report_table_r1" "css_element"
    And I should see "-25.53" in the "#cash_report_table_r2" "css_element"
    And I should see "Credits paid back by cash" in the "#cash_report_table_r2" "css_element"
    And I should see "25.53" in the "#cash_report_table_r3" "css_element"
    And I should see "add credits CostCenter2" in the "#cash_report_table_r3" "css_element"
    And I should see "17" in the "#cash_report_table_r4" "css_element"
    And I should see "add no costcenter credits" in the "#cash_report_table_r4" "css_element"
    And I should see "16.35" in the "#cash_report_table_r5" "css_element"
    And I should see "add credits CostCenter1" in the "#cash_report_table_r5" "css_element"
    And "//*[@id='cash_report_table_r6']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart costcenter credits: cashier correct (reduce) credits for user and refund some
    Given the following config values are set as admin:
      | config                      | value       | plugin              |
      | samecostcenterforcredits    | 1           | local_shopping_cart |
      | defaultcostcenterforcredits | CostCenter1 | local_shopping_cart |
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency | costcenter  |
      | user1 | 30     | EUR      | CostCenter1 |
      | user1 | 40     | EUR      | CostCenter2 |
      | user1 | 50     | EUR      |             |
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "30.00" in the ".cashier-history-items [data-costcenter=\"CostCenter1\"] .credit_total" "css_element"
    And I should see "40.00" in the ".cashier-history-items [data-costcenter=\"CostCenter2\"] .credit_total" "css_element"
    And I should see "50.00" in the ".cashier-history-items [data-costcenter=\"No costcenter\"] .credit_total" "css_element"
    ## Reduce "no costcenter" credits
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "-8"
    And I set the field "Costcenter to which the credit is assigned to" to ""
    And I set the field "Reason" to "reduce no costcenter credits"
    And I press "Save changes"
    And I wait until the page is ready
    ## Reduce credits to the CostCenter1
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "-11"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter1"
    And I set the field "Reason" to "cc1 reduce credits"
    And I press "Save changes"
    And I wait until the page is ready
    ## Reduce credits to the CostCenter2
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "-12"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter2"
    And I set the field "Reason" to "cc2 reduce credits"
    And I press "Save changes"
    And I wait until the page is ready
    ## Verify credits per costcenters
    And I should see "19.00" in the ".cashier-history-items [data-costcenter=\"CostCenter1\"] .credit_total" "css_element"
    And I should see "28.00" in the ".cashier-history-items [data-costcenter=\"CostCenter2\"] .credit_total" "css_element"
    And I should see "42.00" in the ".cashier-history-items [data-costcenter=\"No costcenter\"] .credit_total" "css_element"
    ## Payback credits of the "No costcenter" by cache via credit manager
    And I click on "Credits manager" "button"
    And I wait until the page is ready
    And I set the field "What do you want to do?" to "Pay back credits"
    And I set the field "Payment method" to "Credits paid back by cash"
    And I set the field "Costcenter to which the credit is assigned to" to ""
    And I set the field "Reason" to "Pay back no costcenter by cash"
    And I press "Save changes"
    And I wait until the page is ready
    ## Payback credits of the "CostCenter1" by thansfer directly
    And I click on "Refunded via transfer" "button" in the "[data-costcenter=\"CostCenter1\"].shopping_cart_history_payback_buttons" "css_element"
    And I wait until the page is ready
    And I should see "This will set her credit to 0" in the ".modal-body" "css_element"
    And I click on "button[data-action=\"save\"]" "css_element"
    And I wait until the page is ready
    ## Verify credits per costcenters and report
    Then I should see "28.00" in the "[data-costcenter=\"CostCenter2\"].costcenterlabel .credit_total" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-19.00" in the "#cash_report_table_r1" "css_element"
    And I should see "Credits paid back by transfer" in the "#cash_report_table_r1" "css_element"
    And I should see "-42.00" in the "#cash_report_table_r2" "css_element"
    And I should see "Credits paid back by cash" in the "#cash_report_table_r2" "css_element"
    And I should see "-12.00" in the "#cash_report_table_r3" "css_element"
    And I should see "cc2 reduce credits" in the "#cash_report_table_r3" "css_element"
    And I should see "-11.00" in the "#cash_report_table_r4" "css_element"
    And I should see "cc1 reduce credits" in the "#cash_report_table_r4" "css_element"
    And I should see "-8.00" in the "#cash_report_table_r5" "css_element"
    And I should see "reduce no costcenter credits" in the "#cash_report_table_r5" "css_element"
    And "//*[@id='cash_report_table_r6']" "xpath_element" should not exist

  @javascript
  Scenario: User selects three items than proceed to checkout with credits enforced per costcenter
    Given the following config values are set as admin:
      | config                      | value       | plugin              |
      | samecostcenterforcredits    | 1           | local_shopping_cart |
      | defaultcostcenterforcredits | CostCenter1 | local_shopping_cart |
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency | costcenter  |
      | user1 | 31     | EUR      | CostCenter1 |
      | user1 | 41     | EUR      | CostCenter2 |
      | user1 | 51     | EUR      |             |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "6" has been put in shopping cart of user "user1"
    And Testitem "7" has been put in shopping cart of user "user1"
    And Testitem "8" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    And I should see "(CostCenter1)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-6" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-6 .item-price" "css_element"
    And I should see "(CostCenter2)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-7" "css_element"
    And I should see "20.30 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-7 .item-price" "css_element"
    And I should see "(CostCenter3)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-8" "css_element"
    And I should see "13.80 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-8 .item-price" "css_element"
    ## Price
    And I should see "44.10 EUR" in the ".sc_price_label .sc_initialtotal" "css_element"
    ## Used credit - should be all from unnamed costcenter!
    And I should see "Use credit: 51.00 EUR" in the ".sc_price_label .sc_credit" "css_element"
    ## Deductible
    And I should see "44.10 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    ## Remaining credit
    And I should see "6.90 EUR" in the ".sc_price_label .sc_remainingcredit" "css_element"
    And I should see "0 EUR" in the ".sc_totalprice" "css_element"
    When I press "Checkout"
    And I wait "1" seconds
    And I press "Confirm"
    And I wait until the page is ready
    Then I should see "Payment successful!"
    And I should see "Test item 6" in the ".payment-success ul.list-group" "css_element"
    And I should see "Test item 7" in the ".payment-success ul.list-group" "css_element"
    And I should see "Test item 8" in the ".payment-success ul.list-group" "css_element"
    ## Verify by admin
    And I log out
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "6.90" in the ".cashier-history-items [data-costcenter=\"No costcenter\"] .credit_total" "css_element"
    And I should see "41.00" in the ".cashier-history-items [data-costcenter=\"CostCenter2\"] .credit_total" "css_element"
    And I should see "31.00" in the ".cashier-history-items [data-costcenter=\"CostCenter1\"] .credit_total" "css_element"
    And I log out

  @javascript
  Scenario: User selects two items than proceed to checkout with credits enforced per costcenter
    Given the following config values are set as admin:
      | config                      | value       | plugin              |
      | samecostcenterforcredits    | 1           | local_shopping_cart |
      | defaultcostcenterforcredits | CostCenter1 | local_shopping_cart |
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency | costcenter  |
      | user1 | 32     | EUR      | CostCenter1 |
      | user1 | 42     | EUR      | CostCenter2 |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "6" has been put in shopping cart of user "user1"
    And Testitem "7" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    And I should see "(CostCenter1)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-6" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-6 .item-price" "css_element"
    And I should see "(CostCenter2)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-7" "css_element"
    And I should see "20.30 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-7 .item-price" "css_element"
    ## Price
    And I should see "30.30 EUR" in the ".sc_price_label .sc_initialtotal" "css_element"
    ## Used credit - should be all from unnamed costcenter!
    And I should see "Use credit: 74.00 EUR" in the ".sc_price_label .sc_credit" "css_element"
    ## Deductible
    And I should see "30.30 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    ## Remaining credit
    And I should see "43.70 EUR" in the ".sc_price_label .sc_remainingcredit" "css_element"
    And I should see "0 EUR" in the ".sc_totalprice" "css_element"
    When I press "Checkout"
    And I wait "1" seconds
    And I press "Confirm"
    And I wait until the page is ready
    Then I should see "Payment successful!"
    And I should see "Test item 6" in the ".payment-success ul.list-group" "css_element"
    And I should see "Test item 7" in the ".payment-success ul.list-group" "css_element"
    ## Verify by admin
    And I log out
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "22.00" in the ".cashier-history-items [data-costcenter=\"CostCenter1\"] .credit_total" "css_element"
    And I should see "21.70" in the ".cashier-history-items [data-costcenter=\"CostCenter2\"] .credit_total" "css_element"
    And "cashier-history-items [data-costcenter=\"No costcenter\"]" "css_element" should not exist
    And I log out
