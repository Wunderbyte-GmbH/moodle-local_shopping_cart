@local @local_shopping_cart @javascript

Feature: As admin I debug1

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
  Scenario: Shopping cart debug1 -view than put item in my cart
    Given I log in as "admin"
    And I visit "/local/shopping_cart/test.php"
    And I wait until the page is ready
    And I put item "my test item" in my cart
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    And I wait "10" seconds
    And I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"

@javascript
  Scenario: Shopping cart debug2 - put item in my cart than view
    Given I log in as "admin"
    And I put item "my test item" in my cart
    And I visit "/local/shopping_cart/test.php"
    And I wait until the page is ready
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    And I wait "1" seconds
    And I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"

  @javascript
  Scenario: Cashier debug1 - put item in shopping cart for user
    Given I log in as "admin"
    And I put item in shopping cart in behalf of user "user1"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-cart" "css_element"

  @javascript
  Scenario: Cashier debug2 - buy two items for myself
    Given I log in as "admin"
    And I buy two items
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "admin"
    And I should see "Admin User"
    And I click on "Continue" "button"
    And I wait "10" seconds
    Then I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
