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
    When I log in as "admin"
    And I navigate to "Payments > Payment accounts" in site administration
    Then I click on "PayPal" "link" in the "Account1" "table_row"
    And I set the field "Brand name" to "Test paypal"
    And I set the following fields to these values:
      | Brand name  | Test paypal |
      | Client ID   | Test        |
      | Secret      | Test        |
      | Environment | Sandbox     |
      | Enable      | 1           |
    And I press "Save changes"
    And I should see "PayPal" in the "Account1" "table_row"
    And I should not see "Not available" in the "Account1" "table_row"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Payment account" to "Account1"
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: Cashier buys three items when tax categories eanbled
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I wait until the page is ready
    And I set the field "Enable Tax processing" to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "" in the "#id_s_local_shopping_cart_taxcategories" "css_element"
    And I set the following fields to these values:
      | Tax categories and their tax percentage | A:15 B:10 C:0 |
      | Default tax category                    | A             |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Tax categories and their tax percentage" matches value "A:15 B:10 C:0"
    And the field "Default tax category" matches value "A"
    And I log out
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I wait until the page is ready
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#btn-local_shopping_cart-main-2" "css_element"
    And I click on "#btn-local_shopping_cart-main-3" "css_element"
    And I log out
    Given I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    And I wait "2" seconds
    Then I should see "my test item 1" in the "#shopping_cart-cashiers-section ul.shopping-cart-items" "css_element"
    And I should see "11.50 EUR" in the "#shopping_cart-cashiers-section ul.shopping-cart-items #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the "#shopping_cart-cashiers-section ul.shopping-cart-items #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the "#shopping_cart-cashiers-section ul.shopping-cart-items" "css_element"
    And I should see "22.33 EUR" in the "#shopping_cart-cashiers-section ul.shopping-cart-items #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "(20.30 EUR + 10%)" in the "#shopping_cart-cashiers-section ul.shopping-cart-items #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "my test item 3" in the "#shopping_cart-cashiers-section ul.shopping-cart-items" "css_element"
    And I should see "13.80 EUR" in the "#shopping_cart-cashiers-section ul.shopping-cart-items #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "(13.80 EUR + 0%)" in the "#shopping_cart-cashiers-section ul.shopping-cart-items #item-local_shopping_cart-main-3 .item-price" "css_element"
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
