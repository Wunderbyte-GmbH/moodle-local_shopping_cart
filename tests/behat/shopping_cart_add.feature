@local @local_shopping_cart @javascript

Feature: Test purchase process in shopping cart.
  In order to buy an item
  As a student
  I need to put an item in my cart and proceed to checkout

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | user1    | Username  | 1        |
      | user2    | Username  | 2        |
      | teacher  | Teacher   | 3        |
      | manager  | Manager   | 4        |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |

  @javascript
  Scenario: Add an item to the shopping cart
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "#item-local_shopping_cart-1" "css_element"
    And I reload the page
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "#item-local_shopping_cart-1" "css_element"
    And I click on "#gateways-modal-trigger-33" "css_element"
    Then I should see "my test item 1" in the "div.checkoutgrid" "css_element"

  @javascript
  Scenario: Delete item from the shopping cart
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    And I click on "#item-local_shopping_cart-1 i.fa.fa-trash-o" "css_element"
    Then I should not see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    And I reload the page
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should not see "my test item 1" in the "ul.shopping-cart-items" "css_element"
