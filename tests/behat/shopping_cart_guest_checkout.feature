@local @local_shopping_cart @javascript
Feature: Guest checkout with inline registration during checkout.
  In order to buy items without an existing account
  As an anonymous visitor
  I get an auto-created guest account and register during checkout.

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
    And the following config values are set as admin:
      | config                     | value   | plugin              |
      | guestoncheckout            | 1       | local_shopping_cart |
      | guestautocreateenabled     | 1       | local_shopping_cart |
      | guestautocreatepatterns    | /       | local_shopping_cart |
      | addresses_required         | billing | local_shopping_cart |
      | showdisabledcheckoutbutton | 1       | local_shopping_cart |

  @javascript
  Scenario: Guest registers, adds an address inline and the checkout button activates
    Given I am on site homepage
    And Testitem "1" has been put in shopping cart of the guest checkout user
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    Then I should see "Your contact details"
    And I should see "Register & check out"
    And I should see "Test item 1" in the ".checkoutgrid.checkout" "css_element"
    And the "Checkout" "button" should be disabled
    ## Fill in the guest registration fields.
    When I set the field "guest_firstname" to "Maxi"
    And I set the field "guest_lastname" to "Muster"
    And I set the field "guest_email" to "maxi.muster@example.com"
    And I wait "1" seconds
    ## Add a new address inline - it must appear WITHOUT a page reload.
    And I press "Enter new address"
    And I set the field "Name" to "Maxi Muster"
    And I set the field "Country" to "Austria"
    And I set the field "Address" to "Musterweg 1"
    And I set the field "City" to "Wien"
    And I set the field "Zip" to "1010"
    And I press "Add address"
    And I wait "1" seconds
    Then I should see "Musterweg 1" in the ".local-shopping_cart-addressselection" "css_element"
    ## The new address is auto-selected and registration is valid: button activates.
    And the "Checkout" "button" should be enabled
    ## The state survives a page reload (checkout cache).
    When I reload the page
    And I wait until the page is ready
    Then I should see "Musterweg 1" in the ".local-shopping_cart-addressselection" "css_element"
    And the "Checkout" "button" should be enabled

  @javascript
  Scenario: Guest registration with an already registered e-mail shows an error and blocks checkout
    Given I am on site homepage
    And Testitem "1" has been put in shopping cart of the guest checkout user
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I press "Enter new address"
    And I set the field "Name" to "Maxi Muster"
    And I set the field "Country" to "Austria"
    And I set the field "Address" to "Musterweg 1"
    And I set the field "City" to "Wien"
    And I set the field "Zip" to "1010"
    And I press "Add address"
    And I wait "1" seconds
    ## Register with the e-mail of an existing real account.
    When I set the field "guest_firstname" to "Maxi"
    And I set the field "guest_lastname" to "Muster"
    And I set the field "guest_email" to "toolgenerator1@example.com"
    And I wait "1" seconds
    Then I should see "An account with this e-mail address already exists." in the ".shopping-cart-checkout-manager-alert-container" "css_element"
    And the "Checkout" "button" should be disabled
    ## Switching to a fresh e-mail recovers without a reload.
    When I set the field "guest_email" to "maxi.muster@example.com"
    And I wait "1" seconds
    Then the "Checkout" "button" should be enabled
