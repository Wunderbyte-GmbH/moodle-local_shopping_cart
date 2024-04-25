@local @local_shopping_cart @javascript

Feature: User cancellation after cash payment with consumption and discount rounding enabled and cancellation fee given
  In order to cancel purchase as a user
  I buy test items, confirm cash payment as a cashier and cancel purchase with consumption and discount rounding enabled and cancellation fee given.

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

  @javascript
  Scenario: User buys items and cancel purchase when consumption and discount rounding enabled and cancellation fee given
    ## Credit on cancelation minus already consumed value = 1
    ## Cancelation fee = 0
    ## Round discounts = 1
    Given the following "local_shopping_cart > plugin setup" exist:
      | account  | cancelationfee | calculateconsumation | rounddiscounts |
      | Account1 | 1              | 1                    | 1              |
    And the following "local_shopping_cart > user purchases" exist:
      | user  | testitemid |
      | user1 | 1          |
      | user1 | 2          |
      | user1 | 3          |
    When I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".cashier-history-items" "css_element"
    And I should see "10.00 EUR" in the ".cashier-history-items" "css_element"
    And I should see "my test item 2" in the ".cashier-history-items" "css_element"
    And I should see "20.30 EUR" in the ".cashier-history-items" "css_element"
    And I should see "my test item 3" in the ".cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the ".cashier-history-items" "css_element"
    And I click on "[data-quotaconsumed=\"0.67\"]" "css_element"
    And I wait "1" seconds
    And I should see "67%" in the ".modal-dialog .progress-bar" "css_element"
    And I should see "10 EUR" in the ".show .modal-content" "css_element"
    And I should see "- 7 EUR" in the ".show .modal-content" "css_element"
    And I should see "- 1 EUR" in the ".show .modal-content" "css_element"
    And I should see "= 2 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    ## Then I should see "2" in the ".cashier-history-items span.credit_total" "css_element"
    Then I should see "2" in the ".cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"0\"]" "css_element"
    And I wait "1" seconds
    And I should see "the costs of your purchase (20 EUR)" in the ".show .modal-content" "css_element"
    And I should see "minus a cancelation fee (1 EUR)" in the ".show .modal-content" "css_element"
    And I should see "as credit (19 EUR) for your next purchase" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    And I should see "21" in the ".cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"1\"]" "css_element"
    And I wait "1" seconds
    And I should see "You have already consumed the whole article and won't get any refund of the price paid: 14 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    And I should see "21" in the ".cashier-history-items span.credit_total" "css_element"

  @javascript
  Scenario: User buys items and cancel purchase when rounding of discounts disabled but consumption enabled and cancellation fee given
    ## Credit on cancelation minus already consumed value = 1
    ## Cancelation fee = 0
    ## Round discounts = ""
    Given the following "local_shopping_cart > plugin setup" exist:
      | account  | cancelationfee | calculateconsumation | rounddiscounts |
      | Account1 | 1              | 1                    |                |
    And the following "local_shopping_cart > user purchases" exist:
      | user  | testitemid |
      | user1 | 1          |
      | user1 | 2          |
      | user1 | 3          |
    When I log in as "user1"
    And I visit "/local/shopping_cart/demo.php"
    And I wait until the page is ready
    And I should see "my test item 1" in the ".cashier-history-items" "css_element"
    And I should see "10.00 EUR" in the ".cashier-history-items" "css_element"
    And I should see "my test item 2" in the ".cashier-history-items" "css_element"
    And I should see "20.30 EUR" in the ".cashier-history-items" "css_element"
    And I should see "my test item 3" in the ".cashier-history-items" "css_element"
    And I should see "13.80 EUR" in the ".cashier-history-items" "css_element"
    And I click on "[data-quotaconsumed=\"0.67\"]" "css_element"
    And I wait "1" seconds
    And I should see "67%" in the ".modal-dialog .progress-bar" "css_element"
    And I should see "You will receive 2.30 EUR as credit" in the ".show .modal-content" "css_element"
    And I should see "10.00 EUR" in the ".show .modal-content" "css_element"
    And I should see "- 6.70 EUR" in the ".show .modal-content" "css_element"
    And I should see "- 1.00 EUR" in the ".show .modal-content" "css_element"
    And I should see "= 2.30 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    Then I should see "2.3" in the ".cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"0\"]" "css_element"
    And I wait "1" seconds
    And I should see "the costs of your purchase (20.30 EUR)" in the ".show .modal-content" "css_element"
    And I should see "minus a cancelation fee (1.00 EUR)" in the ".show .modal-content" "css_element"
    And I should see "as credit (19.30 EUR) for your next purchase" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    And I should see "21.6" in the ".cashier-history-items span.credit_total" "css_element"
    And I click on "[data-quotaconsumed=\"1\"]" "css_element"
    And I wait "1" seconds
    And I should see "You have already consumed the whole article and won't get any refund of the price paid: 13.80 EUR" in the ".show .modal-content" "css_element"
    ## And I press "Cancel purchase"
    And I click on ".show .modal-dialog .modal-footer .btn-primary" "css_element"
    And I should see "21.6" in the ".cashier-history-items span.credit_total" "css_element"
