@local @local_shopping_cart @javascript

Feature: Cashier actions in shopping cart.
  In order buy for students
  As a cashier
  I buy for a student, add discount and so on.

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
    And the following "local_shopping_cart > plugin setup" exist:
      | account  |
      | Account1 |

  @javascript
  Scenario: Cashier rewievs an item the shopping cart of user
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I should see "10.00 EUR" in the "#shopping_cart-cashiers-cart .item-price" "css_element"

  @javascript
  Scenario: Cashier adds discount without rounding for user cart item
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I set the following administration settings values:
      | Round discounts | |
    When I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.shoppingcart-discount-icon" "css_element"
    And I set the following fields to these values:
      | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.50 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"

  @javascript
  Scenario: Cashier adds discount with rounding for user cart item
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I set the following administration settings values:
      | Round discounts | 1 |
    When I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.shoppingcart-discount-icon" "css_element"
    And I set the following fields to these values:
      | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.00 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"

  @javascript
  Scenario: Cashier buys discounted item (without rounding) for user with cash
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I set the following administration settings values:
      | Round discounts | |
    When I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.shoppingcart-discount-icon" "css_element"
    And I set the following fields to these values:
      | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.50 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    And I wait "40" seconds
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"

  @javascript
  Scenario: Cashier buys discounted item (with rounding) for user with cash
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I set the following administration settings values:
      | Round discounts | 1 |
    When I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.shoppingcart-discount-icon" "css_element"
    And I set the following fields to these values:
      | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.00 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"

  @javascript
  Scenario: Cashier buys item for user with cash and cancel purchase with cancellation fee
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "10.00 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"
    And I reload the page
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I press "Cancel purchase"
    And I set the following fields to these values:
      | cancelationfee | 2 |
    And I press "Save changes"
    Then I should see "8" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "Canceled" in the "ul.cashier-history-items" "css_element"

  @javascript
  Scenario: Cashier buys discounted item for user with cash and cancel purchase with cancellation fee
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the following fields to these values:
      | s_local_shopping_cart_rounddiscounts | 0 |
    And I press "Save changes"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.shoppingcart-discount-icon" "css_element"
    And I set the following fields to these values:
      | discountabsolute | 2.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "7.50 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"
    And I reload the page
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I press "Cancel purchase"
    And I set the following fields to these values:
      | cancelationfee | 2 |
    And I press "Save changes"
    Then I should see "5.50" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "Canceled" in the "ul.cashier-history-items" "css_element"
