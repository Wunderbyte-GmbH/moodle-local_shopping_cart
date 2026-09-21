@local @local_shopping_cart @local_shopping_cart_legacy_checkout @javascript

Feature: The checkout renders as on the USI release line when the legacy checkout is switched on.
  In order to keep the checkout our customers know
  As a site administrator
  I switch the legacy checkout on and get the former page and the former steps.

  Background:
    Given the following "users" exist:
      | username | firstname  | lastname    | email                       |
      | user1    | Username1  | Test        | toolgenerator1@example.com  |
    And the following "core_payment > payment accounts" exist:
      | name           |
      | Account1       |
    And the following "local_shopping_cart > payment gateways" exist:
      | account  | gateway | enabled | config                                                                                |
      | Account1 | paypal  | 1       | {"brandname":"Test paypal","clientid":"Test","secret":"Test","environment":"sandbox"} |
    And the following "local_shopping_cart > plugin setup" exist:
      | account  |
      | Account1 |
    And the following config values are set as admin:
      | couponenabled | 1 | local_shopping_cart |

  @javascript
  Scenario: The legacy checkout shows the former page frame and no coupon field
    Given the following config values are set as admin:
      | legacycheckout | 1 | local_shopping_cart |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    Then I should see "Test item 1" in the ".checkoutgrid.checkout" "css_element"
    And ".sc-checkout-modern" "css_element" should not exist
    And "input[data-id=\"couponcode\"]" "css_element" should not exist

  @javascript
  Scenario: The current checkout shows the new page frame and the coupon field
    Given the following config values are set as admin:
      | legacycheckout | 0 | local_shopping_cart |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    Then I should see "Test item 1" in the ".checkoutgrid.checkout" "css_element"
    And ".sc-checkout-modern" "css_element" should exist
    And "input[data-id=\"couponcode\"]" "css_element" should exist

  @javascript
  Scenario: The legacy terms step blocks the checkout until the conditions are accepted
    Given the following config values are set as admin:
      | legacycheckout            | 1                  | local_shopping_cart |
      | accepttermsandconditions  | 1                  | local_shopping_cart |
      | termsandconditions        | <p>Our terms</p>   | local_shopping_cart |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    When I press "Checkout"
    And I wait until the page is ready
    Then "#accepttermsandconditions" "css_element" should exist
    And I should see "Our terms"
    And I click on "#accepttermsandconditions" "css_element"
    And I wait "1" seconds
    And the "disabled" attribute of ".shopping-cart-checkout-manager-nextbutton" "css_element" should not be set
