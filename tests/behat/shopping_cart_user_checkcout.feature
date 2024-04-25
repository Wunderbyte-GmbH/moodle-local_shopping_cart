@local @local_shopping_cart @javascript

Feature: User cancellation after cash payment on the checkout page.
  In order to cancel purchase as a user
  I buy test items, confirm cash payment as a cashier and cancel purchase on the checkout page.

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
    And the following "local_shopping_cart > user credits" exist:
      | user  | credits | currency | balance |
      | user1 | 50      | EUR      | 50      |
    And the following "local_shopping_cart > plugin setup" exist:
      | account  |
      | Account1 |

  @javascript
  Scenario: User select two items procedd to checkout cancel one than pay with credits
    Given I log in as "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    And I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2" "css_element"
    And I should see "20.30 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I click on ".checkoutgrid [data-itemid=\"2\"] i.fa.fa-trash-o" "css_element"
    And I wait "1" seconds
    And I should not see "my test item 2" in the "div.shopping-cart-items" "css_element"
    ## Price
    And I should see "10.00 EUR" in the ".sc_price_label .sc_initialtotal" "css_element"
    ## Used credit
    And I should see "Use credit: 50.00 EUR" in the ".sc_price_label .sc_credit" "css_element"
    ## Deductible
    And I should see "10.00 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    ## Remaining credit
    And I should see "40.00 EUR" in the ".sc_price_label .sc_remainingcredit" "css_element"
    And I should see "0 EUR" in the ".sc_totalprice" "css_element"
    Then I press "Checkout"
    And I wait "1" seconds
    And I press "Confirm"
    And I wait until the page is ready
    And I should see "Payment successful!"
    And I should see "my test item 1" in the ".payment-success ul.list-group" "css_element"
    And I should not see "my test item 2" in the ".payment-success ul.list-group" "css_element"
    And I log out
