@local @local_shopping_cart @javascript

Feature: Cashier actions in shopping cart with tax categories enabled.
  In order buy for students
  As a cashier
  I buy for a student, with tax categories enabled.

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
  Scenario: Cashier buys three items when tax categories eanbled
    Given I log in as "admin"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And Testitem "3" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    And I wait "2" seconds
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-section div.shopping-cart-items" "css_element"
    And I should see "11.50 EUR" in the "#shopping_cart-cashiers-section div.shopping-cart-items #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the "#shopping_cart-cashiers-section div.shopping-cart-items #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the "#shopping_cart-cashiers-section div.shopping-cart-items" "css_element"
    And I should see "22.33 EUR" in the "#shopping_cart-cashiers-section div.shopping-cart-items #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "(20.30 EUR + 10%)" in the "#shopping_cart-cashiers-section div.shopping-cart-items #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "my test item 3" in the "#shopping_cart-cashiers-section div.shopping-cart-items" "css_element"
    And I should see "13.80 EUR" in the "#shopping_cart-cashiers-section div.shopping-cart-items #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "(13.80 EUR + 0%)" in the "#shopping_cart-cashiers-section div.shopping-cart-items #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "44.10" in the "#shopping_cart-cashiers-section .sc_totalprice_net" "css_element"
    And I should see "47.63" in the "#shopping_cart-cashiers-section .sc_totalprice" "css_element"
    And I click on "#shopping_cart-cashiers-section .btn_cashpayment" "css_element"
    Then I should see "Payment successful" in the "div.payment_message_result" "css_element"
    And I reload the page
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I should see "my test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "11.50 EUR" in the "ul.cashier-history-items" "css_element"
    ## And I should see "10.00 EUR + 15%" in the "ul.cashier-history-items div.item-price" "css_element"
    And I should see "my test item 2" in the "ul.cashier-history-items" "css_element"
    And I should see "22.33 EUR" in the "ul.cashier-history-items" "css_element"
    ## And I should see "(20.30 EUR + 10%)" in the "ul.cashier-history-items div.item-price" "css_element"
    And I should see "my test item 3" in the "ul.cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the "ul.cashier-history-items" "css_element"
    ## And I should see "(13.80 EUR + 0%)" in the "ul.cashier-history-items div.item-price" "css_element"
