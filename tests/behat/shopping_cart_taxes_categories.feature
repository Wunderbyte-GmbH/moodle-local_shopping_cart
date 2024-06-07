@local @local_shopping_cart @javascript

Feature: Admin tax actions with tax categories in shopping cart.
  In order buy for students
  As an admin
  I configure tax options with categories
  As a user
  I add items to the shopping cart and see taxes by categories.

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
    ## Enable Tax processing = 1
    ## Tax categories and their tax percentage = "A:15 B:10 C:0"
    ## Default tax category = "A"
    ## Prices for items are net prices: Add the tax = 1
    And the following "local_shopping_cart > plugin setup" exist:
      | account  | enabletax | defaulttaxcategory | taxcategories | itempriceisnet |
      | Account1 | 1         | A                  | A:15 B:10 C:0 | 1              |

  @javascript
  Scenario: Add single item to the shopping cart as user when tax categories enabled
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "div.shopping-cart-items" "css_element"
    And I should see "11.50 EUR" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "11.50" in the "div.sc_initialtotal" "css_element"

  @javascript
  Scenario: Add two items to the shopping cart as user when tax categories enabled
    Given I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#btn-local_shopping_cart-main-2" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "#item-local_shopping_cart-main-1" "css_element"
    And I should see "11.50 EUR" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the "#item-local_shopping_cart-main-2" "css_element"
    And I should see "22.33 EUR" in the "#item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "(20.30 EUR + 10%)" in the "#item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "Total Net:" in the "div.sc_initialtotal" "css_element"
    And I should see "30.30" in the "div.sc_initialtotal" "css_element"
    And I should see "Total:" in the "div.sc_initialtotal" "css_element"
    And I should see "33.83" in the "div.sc_initialtotal" "css_element"

  @javascript
  Scenario: Add three items to the shopping cart when tax categories enabled and goto checkout
    Given I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And Testitem "3" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    Then I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "11.50 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2" "css_element"
    And I should see "22.33 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "(20.30 EUR + 10%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "my test item 3" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-3" "css_element"
    And I should see "13.80 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "(13.80 EUR + 0%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "Total Net:" in the ".checkoutgrid.checkout .sc_price_label" "css_element"
    And I should see "44.10" in the ".checkoutgrid.checkout .sc_totalprice_net" "css_element"
    And I should see "Total:" in the ".checkoutgrid.checkout .sc_price_label" "css_element"
    And I should see "47.63" in the ".checkoutgrid.checkout .sc_totalprice" "css_element"
    And I should see "Checkout"
