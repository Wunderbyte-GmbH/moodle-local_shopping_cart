@local @local_shopping_cart @javascript

Feature: Test purchase process in shopping cart.
  In order to buy an item
  As a student
  I need to put an item in my cart and proceed to checkout

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
    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"
    And I click on "#shopping_cart-cashiers-cart #item-local_shopping_cart-1 i.fa-eur" "css_element"
    And I set the following fields to these values:
            | discountabsolut | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.5 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"

  @javascript
  Scenario: Cashier buys discounted item
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
    And I click on "#shopping_cart-cashiers-cart #item-local_shopping_cart-1 i.fa-eur" "css_element"
    And I set the following fields to these values:
            | discountabsolut | 4.5 |
    And I press "Save changes"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    Then I should see "5.5 EUR" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful"
    And I wait "10" seconds

  @javascript
  Scenario: Cashier gives refund
    Given I log in as 'user1'
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the following fields to these values:
            | user | user |
    And I click on "#li_test_username1" "css_element"
    And I press "submit"