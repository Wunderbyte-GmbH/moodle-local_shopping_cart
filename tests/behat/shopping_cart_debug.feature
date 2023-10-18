@local @local_shopping_cart @javascript

Feature: As admin I debug custom steps in shopping cart

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
    And the following "local_shopping_cart > user purchases" exist:
      | user  | testitemid |
      | user1 | 1          |
      | user1 | 3          |

  @javascript
  Scenario: Shopping cart custom steps demo1: put item in my cart than view
    Given I log in as "admin"
    ## Put intem in cart 1st than view page - because of caching.
    And I put testitem "3" in my cart
    ## Also working OK
    ## And I put testitem "3" in shopping cart of user "admin"
    And I visit "/local/shopping_cart/test.php"
    And I wait until the page is ready
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    And I wait "1" seconds
    And I should see "my test item 3" in the "ul.shopping-cart-items" "css_element"

  @javascript
  Scenario: Shopping cart custom steps demo2: cashier put item in shopping cart for user
    Given I log in as "admin"
    And I put testitem "2" in shopping cart of user "user2"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username2"
    And I should see "Username2 Test"
    And I click on "Continue" "button"
    Then I should see "my test item 2" in the "#shopping_cart-cashiers-cart" "css_element"

  @javascript
  Scenario: Shopping cart custom steps demo3: cashier buy two items for myself
    Given I log in as "admin"
    And I buy testitem "1"
    And I buy testitem "2"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "admin"
    And I should see "Admin User"
    And I click on "Continue" "button"
    And I wait "1" seconds
    Then I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "my test item 2" in the "ul.cashier-history-items" "css_element"

  @javascript
  Scenario: Shopping cart custom steps demo4: cashier view items purchased by user via DB
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I wait "1" seconds
    Then I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "my test item 3" in the "ul.cashier-history-items" "css_element"
