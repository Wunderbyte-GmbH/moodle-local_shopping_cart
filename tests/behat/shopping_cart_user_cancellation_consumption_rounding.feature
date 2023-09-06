@local @local_shopping_cart @javascript

Feature: User cancellation after cash payment with consumption and discount rounding enabled and cancellation fee given.
  In order to cancel purchase as a user
  I buy test items, confirm cash payment as a cashier and cancel purchase with consumption and discount rounding enabled and cancellation fee given.

  Background:
    Given the following "users" exist:
      | username | firstname  | lastname    | email                       |
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
    And I log in as "admin"
    And I set the following administration settings values:
      | Payment account | Account1 |
    And I log out

  @javascript
  Scenario: User buys three items and cancel purchase when consumption and discount rounding enabled and cancellation fee given
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Credit on cancelation minus already consumed value" to "checked"
    And I set the field "Round discounts" to "checked"
    And I set the following fields to these values:
      | Cancelation fee | 1 |
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
    And I click on "Continue" "button"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    And I wait "2" seconds
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"
    And I log out
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".cashier-history-items" "css_element"
    And I should see "10.00 EUR" in the ".cashier-history-items" "css_element"
    And I should see "my test item 2" in the ".cashier-history-items" "css_element"
    And I should see "20.30 EUR" in the ".cashier-history-items" "css_element"
    And I should see "my test item 3" in the ".cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the ".cashier-history-items" "css_element"
    And I click on "[data-quotaconsumed=\"0.67\"]" "css_element"
    And I wait "1" seconds
    And I should see "67%" in the ".modal-dialog .progress-bar" "css_element"
    And I should see "Original price" in the ".show .modal-content" "css_element"
    And I should see "10 EUR" in the ".show .modal-content" "css_element"
    And I should see "Cancellation fee" in the ".show .modal-content" "css_element"
    And I should see "- 1 EUR" in the ".show .modal-content" "css_element"
    And I should see "Credit" in the ".show .modal-content" "css_element"
    And I should see "= 2 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    ## Then I should see "2" in the ".cashier-history-items span.credit_total" "css_element"
    Then I should see "2.3" in the ".cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"0\"]" "css_element"
    And I wait "1" seconds
    And I should see "Original price" in the ".show .modal-content" "css_element"
    And I should see "20 EUR" in the ".show .modal-content" "css_element"
    And I should see "Cancellation fee" in the ".show .modal-content" "css_element"
    And I should see "- 1 EUR" in the ".show .modal-content" "css_element"
    And I should see "Credit" in the ".show .modal-content" "css_element"
    And I should see "= 19 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    Then I should see "21.6" in the ".cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"1\"]" "css_element"
    And I wait "1" seconds
    And I should see "You have already consumed the whole article and won't get any refund of the price paid: 14 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    ## Then I should see "21" in the ".cashier-history-items span.credit_total" "css_element"
    Then I should see "21.6" in the ".cashier-history-items span.credit_total" "css_element"
