@local @local_shopping_cart @javascript

Feature: Manage cash flow for cashiers
  In order to manage cash flowas a cashier I add / reduce / transfer cash.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                       |
      | user1    | Username1 | Test        | toolgenerator1@example.com  |
      | teacher  | Teacher   | Test        | toolgenerator2@example.com  |
      | manager1 | Manager1  | Test        | toolgenerator3@example.com  |
      | manager2 | Manager2  | Test        | toolgenerator4@example.com  |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
      | manager2 | manager | System       |           |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
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
  Scenario: Shopping cart: cashier cash flow - pay
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    ## When I click on "Cash transactions" "text"
    When I click on ".shopping-cart-cashout-button" "css_element"
    And I wait until the page is ready
    And I set the field "Amount of cash transation" to "30"
    And I set the field "Reason for the transaction" to "cash payment"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Cash transaction successful"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "30.00" in the "#cash_report_table_r1" "css_element"
    And I should see "cash payment" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart: cashier cash flow - cashback
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    ## When I click on "Cash transactions" "text"
    When I click on ".shopping-cart-cashout-button" "css_element"
    And I wait until the page is ready
    And I set the field "Amount of cash transation" to "-20"
    And I set the field "Reason for the transaction" to "cashback"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Cash transaction successful"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "-20.00" in the "#cash_report_table_r1" "css_element"
    And I should see "cashback" in the "#cash_report_table_r1" "css_element"
    And "//*[@id='cash_report_table_r2']" "xpath_element" should not exist

  @javascript
  Scenario: Shopping cart: cashier cash transfer
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    ## When I click on "Cash transactions" "text"
    When I click on ".shopping-cart-cashtransfer-button" "css_element"
    And I wait until the page is ready
    And I set the field "From cashier" to "Manager1 Test"
    And I set the field "To cashier" to "Manager2 Test"
    And I set the field "Amount of cash transfer" to "33"
    And I set the field "Reason for the cash transfer" to "cash transfer"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "Cash transfer successful"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "33.00" in the "#cash_report_table_r1" "css_element"
    And I should see "cash transfer" in the "#cash_report_table_r1" "css_element"
    And I should see "Manager2 Test" in the "#cash_report_table_r1" "css_element"
    And I should see "-33.00" in the "#cash_report_table_r2" "css_element"
    And I should see "cash transfer" in the "#cash_report_table_r2" "css_element"
    And I should see "Manager1 Test" in the "#cash_report_table_r2" "css_element"
    And "//*[@id='cash_report_table_r3']" "xpath_element" should not exist
