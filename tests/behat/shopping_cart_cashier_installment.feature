@local @local_shopping_cart @javascript
Feature: Configure curtom installment settings (down payment, discount, etc).

  Background:
    Given the following "users" exist:
      | username | firstname | lastname    | email                 |
      | student1 | Student1  | Test        | student1@example.com  |
      | student2 | Student2  | Test        | student2@example.com  |
      | teacher  | Teacher   | Test        | teacher@example.com   |
      | manager  | Manager   | Test        | manager@example.com   |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
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
    ## And the following "local_shopping_cart > plugin setup" exist:
    ##  | account  | enabletax | defaulttaxcategory | taxcategories | showvatnrchecker | owncountrycode | ownvatnrnumber |
    ##  | Account1 | 1         | A                  | A:15 B:5 C:0  | 1                | DE             | 812526315      |
    ## Mercedes-Bentz VAT :)

  @javascript
  Scenario: Shopping Cart cashier: use installment and change downpayment
    Given the following config values are set as admin:
      | config              | value | plugin              |
      | enableinstallments  | 1     | local_shopping_cart |
      | timebetweenpayments | 2     | local_shopping_cart |
      | reminderdaysbefore  | 1     | local_shopping_cart |
    ##And I log in as "admin"
    And Shopping cart has been cleaned for user "student1"
    And Testitem "5" has been put in shopping cart of user "student1"
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Student1"
    And I should see "Student1 Test"
    And I click on "Continue" "button"
    And I click on "#shopping_cart-cashiers-section #checkout-btn" "css_element"
    And I wait until the page is ready
    Then I should see "Test item 5" in the "#shopping_cart-cashiers-cart" "css_element"
    ## Validate default installment 1st.
    And I set the field "Use installment payments" to "1"
    And I wait "1" seconds
    And I should see "Down payment for Test item 5:"
    And I should see "20 EUR instead of 42.42 EUR"
    And I should see "2" occurrences of "11.21 EUR on" in the ".sc_installments .furtherpayments" "css_element"
    And I should see "20.00 EUR" in the ".sc_totalprice" "css_element"
    ## Modify down payment and Validate installment.
    And I click on "#shopping_cart-cashiers-cart .shoppingcart-discount-icon" "css_element"
    And the field "Down payment" matches value "20"
    And I set the field "Down payment" to "10"
    And I press "Save changes"
    And I should not see "Down payment for Test item 5:"
    And I should see "42.42 EUR" in the ".sc_totalprice" "css_element"
    And I set the field "Use installment payments" to "1"
    And I wait "1" seconds
    And I should see "Down payment for Test item 5:"
    And I should see "10 EUR instead of 42.42 EUR"
    And I should see "2" occurrences of "16.21 EUR on" in the ".sc_installments .furtherpayments" "css_element"
    And I should see "10.00 EUR" in the ".sc_totalprice" "css_element"
    ## Modify down payment for the 2nd time and Validate installment again.
    And I click on "#shopping_cart-cashiers-cart .shoppingcart-discount-icon" "css_element"
    And the field "Down payment" matches value "20"
    And I set the field "Down payment" to "15"
    And I press "Save changes"
    And I should not see "Down payment for Test item 5:"
    And I should see "42.42 EUR" in the ".sc_totalprice" "css_element"
    And I set the field "Use installment payments" to "1"
    And I wait "1" seconds
    And I should see "Down payment for Test item 5:"
    And I should see "15 EUR instead of 42.42 EUR"
    And I should see "2" occurrences of "13.71 EUR on" in the ".sc_installments .furtherpayments" "css_element"
    And I should see "15.00 EUR" in the ".sc_totalprice" "css_element"
