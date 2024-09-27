@local @local_shopping_cart @javascript

Feature: Cashier actions in shopping cart with consumption enabled
  In order to cancel purchase and provide refund for students
  As a cashier
  I buy for a student, cancel purchase and give a refund with consumption enabled.

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

  @javascript
  Scenario: Cashier cancel purchase when rounding of discounts disabled but consumption enabled
    Given I log in as "admin"
    ## Credit on cancelation minus already consumed value = 1
    ## Cancelation fee = 0
    ## Round discounts = ""
    And the following "local_shopping_cart > plugin setup" exist:
      | account  | cancelationfee | calculateconsumation | rounddiscounts |
      | Account1 | 0              | 1                    |                |
    And the following "local_shopping_cart > user purchases" exist:
      | user  | testitemid |
      | user1 | 1          |
      | user1 | 2          |
      | user1 | 3          |
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I click on "Continue" "button"
    And I should see "Test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "10.00 EUR" in the "ul.cashier-history-items" "css_element"
    And I should see "Test item 2" in the "ul.cashier-history-items" "css_element"
    And I should see "20.30 EUR" in the "ul.cashier-history-items" "css_element"
    And I should see "Test item 3" in the "ul.cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the "ul.cashier-history-items" "css_element"
    When I click on "[data-quotaconsumed=\"0.67\"]" "css_element"
    And I wait "1" seconds
    Then I should see "67%" in the ".modal-dialog .progress-bar" "css_element"
    And the field "Amount to pay back" matches value "3.3"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    And I should see "3.3" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"0\"]" "css_element"
    And I wait "1" seconds
    And the field "Amount to pay back" matches value "20.30"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    And I should see "23.6" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"1\"]" "css_element"
    And I wait "1" seconds
    And I should see "The user has already consumed the whole article" in the ".modal-content" "css_element"
    And the field "Amount to pay back" matches value "0"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    And I should see "23.6" in the "ul.cashier-history-items span.credit_total" "css_element"

  @javascript
  Scenario: Cashier cancel purchase when consumption and rounding of discounts enabled
    Given I log in as "admin"
    ## Credit on cancelation minus already consumed value = 1
    ## Cancelation fee = 0
    ## Round discounts = 1
    And the following "local_shopping_cart > plugin setup" exist:
      | account  | cancelationfee | calculateconsumation | rounddiscounts |
      | Account1 | 0              | 1                    | 1              |
    And the following "local_shopping_cart > user purchases" exist:
      | user  | testitemid |
      | user1 | 1          |
      | user1 | 2          |
      | user1 | 3          |
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I click on "Continue" "button"
    And I should see "Test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "10.00 EUR" in the "ul.cashier-history-items" "css_element"
    And I should see "Test item 2" in the "ul.cashier-history-items" "css_element"
    And I should see "20.30 EUR" in the "ul.cashier-history-items" "css_element"
    And I should see "Test item 3" in the "ul.cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the "ul.cashier-history-items" "css_element"
    When I click on "[data-quotaconsumed=\"0.67\"]" "css_element"
    And I wait "1" seconds
    Then I should see "67%" in the ".modal-dialog .progress-bar" "css_element"
    And the field "Amount to pay back" matches value "3.3"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    And I should see "3" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I should not see "3.3" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"0\"]" "css_element"
    And I wait "1" seconds
    And the field "Amount to pay back" matches value "20.30"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    And I should see "23" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I should not see "23.6" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"1\"]" "css_element"
    And I wait "1" seconds
    And I should see "The user has already consumed the whole article" in the ".modal-content" "css_element"
    And the field "Amount to pay back" matches value "0"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    And I should see "23" in the "ul.cashier-history-items span.credit_total" "css_element"
