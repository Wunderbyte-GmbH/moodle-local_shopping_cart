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
      | account  | cancelationfee |
      | Account1 | 1              |

  @javascript
  ## when step Testitem "1" has been purchased by user "user2" become used in test - this file should be removed
  Scenario: Shopping cart custom steps demo3: cashier buy two items for user2
    Given I log in as "admin"
    And Testitem "1" has been purchased by user "user2"
    And Testitem "2" has been purchased by user "user2"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username2"
    And I should see "Username2 Test"
    When I click on "Continue" "button"
    And I wait "1" seconds
    Then I should see "Test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "Test item 2" in the "ul.cashier-history-items" "css_element"
