@local @local_shopping_cart @javascript

Feature: Configure tax categories and using VAT to waive price.

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
    And the following "local_shopping_cart > plugin setup" exist:
      | account  | enabletax | defaulttaxcategory | taxcategories | showvatnrchecker | owncountrycode | ownvatnrnumber |
      | Account1 | 1         | A                  | A:15 B:5 C:0  | 1                | AT             | U74259768      |

  @javascript
  Scenario: Shopping Cart taxes: use VAT number to reduce net price of single item
    Given the following config values are set as admin:
      | config          | value | plugin              |
      | itempriceisnet  | 1     | local_shopping_cart | 
    And I log in as "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "11.50 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "11.50 EUR" in the ".sc_totalprice" "css_element"
    ## Provide an invalid VAT number 1st
    And I set the field "usevatnr" to "1"
    And I set the field "Select your country" to "Austria"
    And I set the field "Enter your VAT number" to "U1100"
    And I click on "Verify validity of VAT number" "button"
    And I wait "1" seconds
    And I should see "The given VAT number ATU1100 is invalid" in the ".form_vatnrchecker" "css_element"
    And I should see "11.50 EUR" in the ".sc_totalprice" "css_element"
    ## Provide a valid VAT number finally
    And I set the field "Enter your VAT number" to "U74259768"
    And I click on "Verify validity of VAT number" "button"
    And I wait "1" seconds
    And I should see "Wunderbyte GmbH" in the ".form_vatnrchecker" "css_element"
    And I should see "10.00 EUR" in the ".sc_totalprice" "css_element"
    ## Fall to invalid VAT will at this point will not change last valid VAT
    ## And it is intentional behavior - see https://github.com/Wunderbyte-GmbH/moodle-local_shopping_cart/issues/71#issuecomment-2144701017

  @javascript
  Scenario: Shopping Cart taxes: use VAT number to reduce gross price of single item
    Given the following config values are set as admin:
      | config          | value | plugin              |
      | itempriceisnet  |       | local_shopping_cart | 
    And I log in as "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(8.70 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "10.00 EUR" in the ".sc_totalprice" "css_element"
    ## Provide a valid VAT number
    And I set the field "usevatnr" to "1"
    And I set the field "Select your country" to "Austria"
    And I set the field "Enter your VAT number" to "U74259768"
    And I click on "Verify validity of VAT number" "button"
    And I wait "1" seconds
    And I should see "8.70 EUR" in the ".sc_totalprice" "css_element"
