@local @local_shopping_cart @javascript

Feature: Admin tax actions with simple taxin shopping cart.
  In order buy as student
  As an admin I configure simple tax options
  As a user I select cart item and see price with tax

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
    ## Enable Tax processing = 1
    ## Default tax category = ""
    ## Tax categories and their tax percentage = 15
    ## Prices for items are net prices: Add the tax = 1
    And the following "local_shopping_cart > plugin setup" exist:
      | account  | enabletax | defaulttaxcategory | taxcategories | itempriceisnet |
      | Account1 | 1         |                    | 15            | 1              |

  @javascript
  Scenario: Add single item to the shopping cart as user when tax without categories enabled
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-4" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 4" in the "div.shopping-cart-items" "css_element"
    And I should see "13.94 EUR" in the "#item-local_shopping_cart-main-4 .item-price" "css_element"
    And I should see "(12.12 EUR + 15%)" in the "#item-local_shopping_cart-main-4 .item-price" "css_element"
    And I should see "13.94" in the "div.sc_initialtotal" "css_element"
