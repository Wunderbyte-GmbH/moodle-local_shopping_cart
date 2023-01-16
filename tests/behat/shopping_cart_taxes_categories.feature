@local @local_shopping_cart @javascript

Feature: Admin tax actions with categories in shopping cart.
  In order buy for students
  As an admin
  I configure tax options with categories
  As a cashier
  I buy for a student and see taxes.

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

  @javascript
  Scenario: Enable tax processing with categories
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Enable Tax processing" to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "" in the "#id_s_local_shopping_cart_taxcategories" "css_element"
    And I should see "" in the "#id_s_local_shopping_cart_defaulttaxcategory" "css_element"
    And I set the following fields to these values:
      | Tax categories and their tax percentage | A:15 B:10 |
      | Default tax category | A |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Tax categories and their tax percentage" matches value "A:15 B:10"
    And the field "Default tax category" matches value "A"

  @javascript
  Scenario: Add single item for user to the shopping cart when tax categories enabled
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Enable Tax processing" to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "" in the "#id_s_local_shopping_cart_taxcategories" "css_element"
    And I set the following fields to these values:
      | Tax categories and their tax percentage | A:15 B:10 |
      | Default tax category | A |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Tax categories and their tax percentage" matches value "A:15 B:10"
    And the field "Default tax category" matches value "A"
    And I log out
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    And I should see "11.5 EUR" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10 EUR + 15%)" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "11.5" in the "li.sc_initialtotal" "css_element"

  @javascript
  Scenario: Add two items for user to the shopping cart when tax categories enabled
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Enable Tax processing" to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "" in the "#id_s_local_shopping_cart_taxcategories" "css_element"
    And I set the following fields to these values:
      | Tax categories and their tax percentage | A:15 B:10 |
      | Default tax category | A |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Tax categories and their tax percentage" matches value "A:15 B:10"
    And the field "Default tax category" matches value "A"
    And I log out
    Given I log in as "user1"
    And I visit "/local/shopping_cart/test.php"
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#btn-local_shopping_cart-main-2" "css_element"
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "#item-local_shopping_cart-main-1" "css_element"
    And I should see "11.5 EUR" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10 EUR + 15%)" in the "#item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the "#item-local_shopping_cart-main-2" "css_element"
    And I should see "22.33 EUR" in the "#item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "(20.3 EUR + 10%)" in the "#item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "Total Net:" in the "li.sc_initialtotal" "css_element"
    And I should see "30.3" in the "li.sc_initialtotal" "css_element"
    And I should see "Total:" in the "li.sc_initialtotal" "css_element"
    And I should see "33.83" in the "li.sc_initialtotal" "css_element"

  @javascript
  Scenario: Add three items to the shopping cart when tax categories enabled and goto checkout
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
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
    And I click on "#btn-local_shopping_cart-main-1" "css_element"
    And I click on "#btn-local_shopping_cart-main-2" "css_element"
    And I click on "#btn-local_shopping_cart-main-3" "css_element"
    And I wait "2" seconds
    And I click on "#nav-shopping_cart-popover-container" "css_element"
    And I click on "Proceed to checkout" "link"
    And I wait "2" seconds
    And I should see "Your shopping cart"
    Then I should see "my test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "11.5 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "(10 EUR + 15%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "my test item 2" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2" "css_element"
    And I should see "22.33 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "(20.3 EUR + 10%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "my test item 3" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-3" "css_element"
    And I should see "13.8 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "(13.8 EUR + 0%)" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-3 .item-price" "css_element"
    And I should see "Total Net:" in the ".checkoutgrid.checkout .sc_price_label" "css_element"
    And I should see "44.1" in the ".checkoutgrid.checkout .sc_totalprice_net" "css_element"
    And I should see "Total:" in the ".checkoutgrid.checkout .sc_price_label" "css_element"
    And I should see "47.63" in the ".checkoutgrid.checkout .sc_totalprice" "css_element"
    And I should see "Checkout"
