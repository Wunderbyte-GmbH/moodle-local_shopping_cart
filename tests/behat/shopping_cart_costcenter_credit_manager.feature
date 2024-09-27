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
  Scenario: Shopping cart costcenter credits: cashier correct (add) credits for user
    Given the following config values are set as admin:
      | config                      | value       | plugin              |
      | samecostcenterforcredits    | 1           | local_shopping_cart |
      | defaultcostcenterforcredits | CostCenter1 | local_shopping_cart |
    And I log in as "admin"
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
    And I set the field "Correction value or credits to pay back" to "16.35"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter1"
    And I set the field "Reason" to "add credits CostCenter1"
    And I press "Save changes"
    And I wait until the page is ready
    And I click on "Credits manager" "button"
    And I wait until the page is ready
    ## Dynamic fields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Correct credits"
    And I set the field "Correction value or credits to pay back" to "25.53"
    And I set the field "Costcenter to which the credit is assigned to" to "CostCenter2"
    And I set the field "Reason" to "add credits CostCenter2"
    And I press "Save changes"
    And I wait until the page is ready
    Then I should see "16.35" in the ".cashier-history-items [data-costcenter=\"CostCenter1\"] .credit_total" "css_element"
    And I should see "25.53" in the ".cashier-history-items [data-costcenter=\"CostCenter2\"] .credit_total" "css_element"
    And I follow "Cash report"
    And I wait until the page is ready
    And I should see "25.53" in the "#cash_report_table_r1" "css_element"
    And I should see "add credits" in the "#cash_report_table_r1" "css_element"
    And I should see "16.35" in the "#cash_report_table_r2" "css_element"
    And I should see "add credits" in the "#cash_report_table_r2" "css_element"
    And "//*[@id='cash_report_table_r3']" "xpath_element" should not exist
