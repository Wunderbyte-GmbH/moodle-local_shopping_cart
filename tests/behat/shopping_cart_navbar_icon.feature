@local @local_shopping_cart @javascript
Feature: Shopping cart icon in the navbar is available to all authenticated users
  In order to be able to buy items
  As any authenticated user (student, teacher, manager, ...)
  I need to see the shopping cart icon in the navigation bar

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | manager1 | Manager   | One      | manager1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "system role assigns" exist:
      | user     | role    |
      | manager1 | manager |

  @javascript
  Scenario Outline: The cart icon is visible to authenticated users regardless of their role
    Given I log in as "<user>"
    And I wait until the page is ready
    Then "#nav-shopping_cart-popover-container" "css_element" should exist

    Examples:
      | user     |
      | student1 |
      | teacher1 |
      | manager1 |

  @javascript
  Scenario: The cart icon is hidden when the authenticated user role lacks the capability
    Given the following "permission overrides" exist:
      | capability                            | permission | role | contextlevel | reference |
      | local/shopping_cart:canseecartnavitem | Prohibit   | user | System       |           |
    And I log in as "student1"
    And I wait until the page is ready
    Then "#nav-shopping_cart-popover-container" "css_element" should not exist
