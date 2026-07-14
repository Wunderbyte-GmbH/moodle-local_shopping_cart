@local @local_shopping_cart @javascript
Feature: Price display on the checkout page with a decimal comma.
  In order to see correct prices as a user of a language with a decimal comma (e.g. German)
  the cent amounts on the checkout page must not be truncated
  when the prices are converted for rendering more than once.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                      |
      | user1    | Username1 | Test     | toolgenerator1@example.com |
    And the following "core_payment > payment accounts" exist:
      | name     |
      | Account1 |
    And the following "local_shopping_cart > payment gateways" exist:
      | account  | gateway | enabled | config                                                                                |
      | Account1 | paypal  | 1       | {"brandname":"Test paypal","clientid":"Test","secret":"Test","environment":"sandbox"} |
    And the following "local_shopping_cart > plugin setup" exist:
      | account  |
      | Account1 |

  @javascript
  Scenario: Prices with cents are not truncated on checkout page when the decimal separator is a comma
    Given the decimal separator has been set to ","
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    And I should see "Test item 2" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2" "css_element"
    ## Without the fix, the second price conversion truncated "20,30" to "20,00".
    Then I should see "20,30 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    And I should see "20,30 EUR" in the ".sc_totalprice" "css_element"
