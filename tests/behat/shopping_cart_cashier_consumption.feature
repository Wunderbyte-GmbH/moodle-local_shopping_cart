@local @local_shopping_cart @javascript

Feature: Cashier actions in shopping cart with consumption enabled.
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
    When I log in as "admin"
    And I navigate to "Payments > Payment accounts" in site administration
    Then I click on "PayPal" "link" in the "Account1" "table_row"
    And I set the field "Brand name" to "Test paypal"
    And I set the following fields to these values:
      | Brand name  | Test paypal |
      | Client ID   | Test        |
      | Secret      | Test        |
      | Environment | Sandbox     |
      | Enable      | 1           |
    And I press "Save changes"
    And I should see "PayPal" in the "Account1" "table_row"
    And I should not see "Not available" in the "Account1" "table_row"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Payment account" to "Account1"
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: Cashier buys three items and cancel purchase when consumption enabled
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Credit on cancelation minus already consumed value." to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I log out
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#btn-local_shopping_cart-main-2" "css_element"
    And I click on "#btn-local_shopping_cart-main-3" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Choose" "button"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    And I wait "2" seconds
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"
    And I reload the page
    And I wait "1" seconds
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Choose" "button"
    And I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "10.00 EUR" in the "ul.cashier-history-items" "css_element"
    And I should see "my test item 2" in the "ul.cashier-history-items" "css_element"
    And I should see "20.30 EUR" in the "ul.cashier-history-items" "css_element"
    And I should see "my test item 3" in the "ul.cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the "ul.cashier-history-items" "css_element"
    And I click on "[data-quotaconsumed=\"0.67\"]" "css_element"
    And I wait "1" seconds
    And I should see "67%" in the ".modal-dialog .progress-bar" "css_element"
    And the field "Amount to pay back" matches value "3.3"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    Then I should see "3.3" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"0\"]" "css_element"
    And I wait "1" seconds
    And the field "Amount to pay back" matches value "20.30"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    Then I should see "23.6" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"1\"]" "css_element"
    And I wait "1" seconds
    And I should see "The user has already consumed the whole article" in the ".modal-content" "css_element"
    And the field "Amount to pay back" matches value "0"
    And the field "Cancelation fee" matches value "0"
    And I press "Save changes"
    Then I should see "23.6" in the "ul.cashier-history-items span.credit_total" "css_element"
