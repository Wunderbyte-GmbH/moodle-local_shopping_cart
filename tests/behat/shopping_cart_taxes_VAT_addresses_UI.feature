@local @local_shopping_cart @javascript
Feature: Configure tax categories and use VAT and testing address UI.

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
      | account  | enabletax | defaulttaxcategory  | showvatnrchecker |
      | Account1 | 1         | A                   | 1                |
    And the following "local_shopping_cart > user addresses" exist:
      | user  | name     | state | address     | city   | zip  |
      | user1 | company2 | PT    | Brovarna 53 | Lisboa | l123 |
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
  Scenario: Shopping Cart taxes: verify CRUD of address UI
    Given the following config values are set as admin:
      | config              | value     | plugin              |
      | itempriceisnet      | 1         | local_shopping_cart |
      | owncountrycode      | DE        | local_shopping_cart |
      | ownvatnrnumber      | 812526315 | local_shopping_cart |
      ## Mercedes-Bentz :)
      | onlywithvatnrnumber |           | local_shopping_cart |
      ## optional settings not affecting the test
      ##| showdisabledcheckoutbutton                        | 1         | local_shopping_cart |
    And VAT mock data is configured as:
      | countrycode | vatnumber   | response                                             |
      | AT          | U74259768   | {"valid": true, "name": "Wunderbyte", "address": ""} |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I should see "Test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "(10.00 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "11.50 EUR" in the ".sc_totalprice" "css_element"
    And I should see "Lisboa" in the ".local-shopping_cart-requiredaddress" "css_element"
    ## Add another address, select it and verify taxes
    And I follow "Enter new address"
    And I set the field "Name" to "user1"
    And I set the field "Company Name" to "company1"
    And I set the field "Country" to "Austria"
    And I set the field "Address" to "Brovarna 23"
    And I set the field "City" to "Wien"
    And I set the field "Zip" to "w123"
    And I press "Add address"
    And I should see "Wien" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I click on "Wien" "text" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I should see "12.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 20%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    ## Switch to previoue address, edit it and verify taxes
    And I should see "Lisboa" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I click on "Lisboa" "text" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I follow "Edit selected address"
    And I set the field "Address" to "Commerco 100"
    And I press "Save address"
    And I should see "Commerco 100" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I should not see "Brovarna 53" in the ".local-shopping_cart-requiredaddress" "css_element"
    ## Delete the selected address
    And I follow "Delete selected address"
    And I press "Submit deletion"
    And I should not see "Lisboa" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I should see "Wien" in the ".local-shopping_cart-requiredaddress" "css_element"
    ## Switch to remaining address and verify taxes again
    And I click on "Wien" "text" in the ".local-shopping_cart-requiredaddress" "css_element"
    And I should see "12.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10.00 EUR + 20%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
