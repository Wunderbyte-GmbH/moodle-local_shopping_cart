@local @local_shopping_cart @javascript

Feature: Cashier manage credits in shopping cart
  In order to manage credits as a cashier I add / reduce / refund credits for students.

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
    And the following "local_shopping_cart > payment gateways" exist:
      | account  | gateway | enabled | config                                                                                |
      | Account1 | paypal  | 1       | {"brandname":"Test paypal","clientid":"Test","secret":"Test","environment":"sandbox"} |
    And I log in as "admin"
    And I set the following administration settings values:
      | Payment account                                    | Account1 |
    And I log out

  @javascript
  Scenario: Shopping cart credits: cashier attempts to refund non-existing credits
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    ##And I should not see "Credits" in the ".cashier-history-items" "css_element"
    And "//*[@class='credit_total']" "xpath_element" should not exist
    When I click on "Credits manager" "button"
    And I wait "1" seconds
    ## Dynamicfields - step-by-step proceeding required
    And I set the field "What do you want to do?" to "Pay back credits"
    And I set the field "Correction value or credits to pay back" to "5"
    And I set the field "Payment method" to "Credits paid back by cash"
    And I set the field "Reason" to "reduce non-exist"
    And I press "Save changes"
    Then I should see "Not enough credits available"
