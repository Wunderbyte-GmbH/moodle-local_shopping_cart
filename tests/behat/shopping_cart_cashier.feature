@local @local_shopping_cart @javascript

Feature: Cashier actions in shopping cart.
  In order buy for students
  As a cashier
  I buy for a student, add discount and so on.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | user1    | Username1  | Test        |
      | user2    | Username2  | Test        |
      | teacher  | Teacher    | Test        |
      | manager  | Manager    | Test        |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |

  @javascript
  Scenario: Add an item for user to the shopping cart
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"

  @javascript
  Scenario: Cashier adds discount
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the following fields to these values:
            | s_local_shopping_cart_rounddiscounts | 0 |
    And I press "Save changes"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.fa-eur" "css_element"
    And I set the following fields to these values:
            | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.5 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"

  @javascript
  Scenario: Cashier adds discount without rounding
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.fa-eur" "css_element"
    And I set the following fields to these values:
            | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"

  @javascript
  Scenario: Cashier buys discounted item
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the following fields to these values:
            | s_local_shopping_cart_rounddiscounts | 0 |
    And I press "Save changes"

    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.fa-eur" "css_element"
    And I set the following fields to these values:
            | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.5 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the ".notifications" "css_element"

  @javascript
  Scenario: Cashier gives refund
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the following fields to these values:
            | s_local_shopping_cart_rounddiscounts | 0 |
    And I press "Save changes"

    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart [data-item=\"shopping_cart_item\"] i.fa-eur" "css_element"
    And I set the following fields to these values:
            | discountabsolute | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.5 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the ".notifications" "css_element"
    And I reload the page
    And I press "Cancel purchase"
    And I set the following fields to these values:
            | cancelationfee | 2 |
    And I press "Save changes"
    Then I should see "3.5" in the "ul.cashier-history-items span.credit_total" "css_element"
    And I press "Refunded"
    And I press "Confirm"
    Then I should see "Credit paid back" in the ".notifications" "css_element"
    Then I should not see "Credit" in the "ul.cashier-history-items" "css_element"
