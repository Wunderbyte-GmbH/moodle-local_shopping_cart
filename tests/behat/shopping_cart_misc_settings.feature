@local @local_shopping_cart @javascript

Feature: Admin configures shopping cart to use various settings.

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
    When I log in as "admin"
    And I navigate to "Payments > Payment accounts" in site administration
    Then I click on "PayPal" "link" in the "Account1" "table_row"
    And I set the following fields to these values:
      | Brand name  | Test paypal |
      | Client ID   | Test        |
      | Secret      | Test        |
      | Environment | Sandbox     |
      | Enable      | 1           |
    And I press "Save changes"
    And I should see "PayPal" in the "Account1" "table_row"
    And I set the following administration settings values:
      | Payment account | Account1 |
    And I log out

  @javascript
  Scenario: Shopping Cart settings: enable terms and conditions
    Given I log in as "admin"
    And I set the following administration settings values:
      | Require accpetance of terms and conditions | 1                              |
      | Terms & Conditions                         | Are you agree with conditions? |
    And I log out
    When I log in as "user1"
    And I wait until the page is ready
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    And I click on "Proceed to checkout" "link"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".shopping-cart-checkout-items-container" "css_element"
    And the "Checkout" "button" should be disabled
    And I should see "Are you agree with conditions?" in the ".form_termsandconditions" "css_element"
    ## Access by "name" attribute because "id" does not work for some strange reasons
    When I set the field "accepttermsnandconditions" to "checked"
    Then the "Checkout" "button" should be enabled
