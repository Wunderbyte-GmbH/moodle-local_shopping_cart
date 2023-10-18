@local @local_shopping_cart @javascript

Feature: Admin configures shopping cart to use simple taxes or tax categories.

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
    And the following "local_shopping_cart > plugin setup" exist:
      | account  |
      | Account1 |

  @javascript
  Scenario: Shopping Cart settings: enable tax processing without categories
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I set the field "Enable Tax processing" to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "" in the "#id_s_local_shopping_cart_taxcategories" "css_element"
    And I set the following fields to these values:
      | Tax categories and their tax percentage | 15 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Tax categories and their tax percentage" matches value "15"
    And the field "Default tax category" matches value ""

  @javascript
  Scenario: Shopping Cart settings: enable tax processing with categories
    Given I log in as "admin"
    And I visit "/admin/category.php?category=local_shopping_cart"
    And I wait until the page is ready
    And I set the field "Enable Tax processing" to "checked"
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "" in the "#id_s_local_shopping_cart_taxcategories" "css_element"
    And I should see "" in the "#id_s_local_shopping_cart_defaulttaxcategory" "css_element"
    And I set the following fields to these values:
      | Tax categories and their tax percentage      | A:15 B:10 C:0 |
      | Default tax category                         | A             |
      | Prices for items are net prices: Add the tax | checked       |
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "Tax categories and their tax percentage" matches value "A:15 B:10 C:0"
    And the field "Default tax category" matches value "A"
