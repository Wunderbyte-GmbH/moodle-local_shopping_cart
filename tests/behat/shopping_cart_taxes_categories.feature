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
    And I set the following fields to these values:
            | s_local_shopping_cart_taxcategories | A:15 B:10 |
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "A:15 B:10" in the "#id_s_local_shopping_cart_taxcategories" "css_element"