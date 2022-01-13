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
    When I am on "Course 1" course homepage
    And I visit "http://webserver/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I wait "1" seconds
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item"
    And I wait "1" seconds
    And I reload the page
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item"

  @javascript
  Scenario: Add an item to the shopping cart and delete it again
    Given I log in as "user1"
    When I am on "Course 1" course homepage
    And I visit "http://webserver/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-1" "css_element"
    And I wait "1" seconds
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item"
    And I click on "#item-local_shopping_cart-1 i.fa.fa-trash-o" "css_element"
    Then I should not see "my test item"
