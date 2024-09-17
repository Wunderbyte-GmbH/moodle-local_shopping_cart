@local @local_shopping_cart @javascript

Feature: Cashier manage credits in shopping cart
  In order to manage credits as a cashier I add / reduce / refund credits for students.

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
  Scenario: Shopping cart credits: cashier correct (add) credits for user
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    ## And I should not see "Credits" in the ".cashier-history-items" "css_element"
    And "//*[@class='credit_total']" "xpath_element" should not exist
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "15.35"
    And I set the field "Reason" to "add credits"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "15.35" in the ".cashier-history-items .credit_total" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "15.35" in the "#cash_report_table_r1" "css_element"
    And I should see "add credits" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart credits: cashier correct (reduce) credits for user
    Given the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 20     | EUR      |
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "20.00" in the ".cashier-history-items .credit_total" "css_element"
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "-10"
    And I set the field "Reason" to "reduce credits"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "10.00" in the ".cashier-history-items .credit_total" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-10.00" in the "#cash_report_table_r1" "css_element"
    And I should see "reduce credits" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart credits: cashier payback (cache) part of credits to user
    Given the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 25     | EUR      |
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "25.00" in the ".cashier-history-items .credit_total" "css_element"
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Pay back credits"
    And I set the field "Payment method" to "Credits paid back by cash"
    And I set the field "Reason" to "Pay back by cash"
    And I press "Save changes"
    And I wait until the page is ready
    # Credit element should not be there anymore.
    And I wait "2" seconds
    Then ".cashier-history-items .credit_total" "css_element" should not exist
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-25.00" in the "#cash_report_table_r1" "css_element"
    And I should see "Credits paid back by cash" in the "#cash_report_table_r1" "css_element"
    And I should see "Username1" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart credits: cashier payback (transfer) part of credits to user
    Given the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 25     | EUR      |
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "25.00" in the ".cashier-history-items .credit_total" "css_element"
    When I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Pay back credits"
    And I set the field "Payment method" to "Credits paid back by transfer"
    And I set the field "Reason" to "Pay back by transfer"
    And I press "Save changes"
    And I wait until the page is ready
    And I wait "2" seconds
    # Credit element should not be there anymore
    Then ".cashier-history-items .credit_total" "css_element" should not exist
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-25.00" in the "#cash_report_table_r1" "css_element"
    And I should see "Credits paid back by transfer" in the "#cash_report_table_r1" "css_element"
    And I should see "Username1" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart credits: cashier payback (transfer) all of credits to user
    Given the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 23     | EUR      |
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "23.00" in the ".cashier-history-items .credit_total" "css_element"
    When I click on "Refunded via transfer" "button"
    And I wait until the page is ready
    And I should see "This will set her credit to 0" in the ".modal-body" "css_element"
    ## And I press "Confirm"
    And I click on "button[data-action=\"save\"]" "css_element"
    And I wait until the page is ready
    ## Then I should not see "Credit" in the "ul.cashier-history-items" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-23.00" in the "#cash_report_table_r1" "css_element"
    And I should see "Credits paid back by transfer" in the "#cash_report_table_r1" "css_element"
    And I should see "Username1" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart credits: cashier payback (cache) all of credits to user
    Given the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 22     | EUR      |
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait until the page is ready
    And I should see "22.00" in the ".cashier-history-items .credit_total" "css_element"
    When I click on "Refunded with cash" "button"
    And I wait until the page is ready
    And I should see "This will set her credit to 0" in the ".modal-body" "css_element"
    ## And I press "Confirm"
    And I click on "button[data-action=\"save\"]" "css_element"
    And I wait until the page is ready
    ## Then I should not see "Credit" in the "ul.cashier-history-items" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-22.00" in the "#cash_report_table_r1" "css_element"
    And I should see "Credits paid back by cash" in the "#cash_report_table_r1" "css_element"
    And I should see "Username1" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist
    ## Force credits to 0 to avoid potential issues in other tests
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 0     | EUR      |
    And I log out
