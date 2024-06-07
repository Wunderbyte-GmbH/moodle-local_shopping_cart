@local @local_shopping_cart @javascript

Feature: Configure tax categories and using VAT and addressess to waive price.

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
      | account  | enabletax | defaulttaxcategory  | showvatnrchecker | owncountrycode | ownvatnrnumber |
      | Account1 | 1         | A                   | 1                | AT             | U74259768      |
    And the following "local_shopping_cart > user addresses" exist:
      | user  | name   | state | address     | city   | zip  |
      | user1 | User 1 | AT    | Brovarna 23 | Wienn  | w123 |
      | user2 | User 2 | DE    | Brovarna 23 | Berlin | b123 |
    And I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "id_s_local_shopping_cart_addresses_required_billing" to "1"
    And I set the field "Tax categories and their tax percentage" to multiline:
    """
    AT A:20 B:10 C:0
    DE A:19 B:9 C:0
    default A:15 B:5 C:0
    """
    And I press "Save changes"
    And I log out

  @javascript
  Scenario: Shopping Cart taxes: use VAT number and Austrian address to reduce net price of single item
    Given the following config values are set as admin:
      | config          | value | plugin              |
      | itempriceisnet  | 1     | local_shopping_cart |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "11.50 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "11.50 EUR" in the ".sc_totalprice" "css_element"
    ## Select billing address
    And I follow "Continue to address"
    And I wait until the page is ready
    And I should see "Wienn" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I click on "Wienn" "text" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I press "Choose selected address"
    And I wait until the page is ready
    And I should see "12.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 20%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    ## Provide a valid VAT number and verify price
    And I set the field "usevatnr" to "1"
    And I set the field "Select your country" to "Austria"
    And I set the field "Enter your VAT number" to "U74259768"
    And I click on "Verify validity of VAT number" "button"
    And I wait "1" seconds
    And I should see "Wunderbyte GmbH" in the ".form_vatnrchecker" "css_element"
    And I should see "10.00 EUR" in the ".sc_totalprice" "css_element"

  @javascript
  Scenario: Shopping Cart taxes: use VAT number and German address to reduce net price of single item
    Given the following config values are set as admin:
      | config          | value | plugin              |
      | itempriceisnet  | 1     | local_shopping_cart |
    And I log in as "user2"
    And Shopping cart has been cleaned for user "user2"
    And Testitem "1" has been put in shopping cart of user "user2"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "11.50 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "11.50 EUR" in the ".sc_totalprice" "css_element"
    ## Select billing address
    And I follow "Continue to address"
    And I wait until the page is ready
    And I should see "Berlin" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I click on "Berlin" "text" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I press "Choose selected address"
    And I wait until the page is ready
    And I should see "11.90 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 19%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    ## Provide a valid VAT number and verify price
    And I set the field "usevatnr" to "1"
    And I set the field "Select your country" to "Austria"
    And I set the field "Enter your VAT number" to "U74259768"
    And I click on "Verify validity of VAT number" "button"
    And I wait "1" seconds
    And I should see "Wunderbyte GmbH" in the ".form_vatnrchecker" "css_element"
    And I should see "10.00 EUR" in the ".sc_totalprice" "css_element"

  @javascript
  Scenario: Shopping Cart taxes: use VAT number and German address to reduce gross price of single item
    Given the following config values are set as admin:
      | config          | value | plugin              |
      | itempriceisnet  | 0     | local_shopping_cart |
    And I log in as "user2"
    And Shopping cart has been cleaned for user "user2"
    And Testitem "1" has been put in shopping cart of user "user2"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(8.70 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "10.00 EUR" in the ".sc_totalprice" "css_element"
    ## Select billing address
    And I follow "Continue to address"
    And I wait until the page is ready
    And I should see "Berlin" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I click on "Berlin" "text" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I press "Choose selected address"
    And I wait until the page is ready
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(8.40 EUR + 19%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    ## Provide a valid VAT number and verify price
    And I set the field "usevatnr" to "1"
    And I set the field "Select your country" to "Austria"
    And I set the field "Enter your VAT number" to "U74259768"
    And I click on "Verify validity of VAT number" "button"
    And I wait "1" seconds
    And I should see "Wunderbyte GmbH" in the ".form_vatnrchecker" "css_element"
    And I should see "8.40 EUR" in the ".sc_totalprice" "css_element"
