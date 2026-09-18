@local @local_shopping_cart @local_shopping_cart_coupons @javascript

Feature: Users redeem coupon codes on the checkout page.
  In order to get a discount
  As a user
  I enter a coupon code on the checkout page, pay and see the coupon on the receipt.

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
      | config            | value | plugin              |
      | couponenabled     | 1     | local_shopping_cart |
      | bookingfee        | 0     | local_shopping_cart |
      | rounddiscounts    | 0     | local_shopping_cart |
    And the following "local_shopping_cart > coupons" exist:
      | coupon  | discountpercentage | maxnumber | maxnumberperuser | coupontype   |
      | BEHAT10 | 10                 | 0         | 1                | couponoptout |
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 50     | EUR      |

  Scenario: Coupon redeem: wrong code, valid code, payment, receipt and per-user limit
    Given I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "30.30 EUR" in the ".sc_price_label .sc_initialtotal" "css_element"
    ## A code that does not exist is refused.
    When I set the field "couponcode" to "NOSUCHCODE"
    And I click on "[data-id=\"applycoupon\"]" "css_element"
    And I wait "1" seconds
    Then I should see "Coupon code \"NOSUCHCODE\" could not be applied." in the ".sc_couponmessage" "css_element"
    And ".sc_coupondiscount" "css_element" should not exist
    ## A valid code discounts both items.
    When I set the field "couponcode" to "BEHAT10"
    And I click on "[data-id=\"applycoupon\"]" "css_element"
    And I wait "1" seconds
    Then I should see "Coupon code \"BEHAT10\" applied successfully." in the ".sc_couponmessage" "css_element"
    And I should see "3.03 EUR" in the ".sc_price_label .sc_coupondiscount" "css_element"
    And I should see "30.30 EUR" in the ".sc_price_label .sc_initialtotal" "css_element"
    And I should see "27.27 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    And I should see "22.73 EUR" in the ".sc_price_label .sc_remainingcredit" "css_element"
    And I should see "0 EUR" in the ".sc_totalprice" "css_element"
    ## Pay with credits, the receipt names the coupon.
    When I press "Checkout"
    And I wait "1" seconds
    And I press "Confirm"
    And I wait until the page is ready
    Then I should see "Payment successful!"
    And I should see "Coupon code \"BEHAT10\" was used for this order."
    ## The coupon is used up for this user and does not carry over.
    Given Testitem "3" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And ".sc_coupondiscount" "css_element" should not exist
    And I should see "13.80 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    When I set the field "couponcode" to "BEHAT10"
    And I click on "[data-id=\"applycoupon\"]" "css_element"
    And I wait "1" seconds
    Then I should see "You have already used this coupon code the maximum number of times." in the ".sc_couponmessage" "css_element"
    And ".sc_coupondiscount" "css_element" should not exist
    And I should see "13.80 EUR" in the ".sc_price_label .sc_deductible" "css_element"

  Scenario: Coupon redeem: remove an applied coupon
    Given I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    When I set the field "couponcode" to "BEHAT10"
    And I click on "[data-id=\"applycoupon\"]" "css_element"
    And I wait "1" seconds
    Then I should see "1.00 EUR" in the ".sc_price_label .sc_coupondiscount" "css_element"
    And I should see "9.00 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    And I should see "Coupon code \"BEHAT10\" applied successfully." in the ".sc_couponmessage" "css_element"
    ## Switching credits off and on keeps the applied coupon.
    When I click on ".sc_price_label .sc_credit label" "css_element"
    And I wait "1" seconds
    Then I should see "1.00 EUR" in the ".sc_price_label .sc_coupondiscount" "css_element"
    And I should see "9.00 EUR" in the ".sc_price_label .sc_totalprice" "css_element"
    When I click on ".sc_price_label .sc_credit label" "css_element"
    And I wait "1" seconds
    Then I should see "1.00 EUR" in the ".sc_price_label .sc_coupondiscount" "css_element"
    And I should see "0 EUR" in the ".sc_price_label .sc_totalprice" "css_element"
    When I set the field "couponcode" to ""
    And I click on "[data-id=\"applycoupon\"]" "css_element"
    And I wait "1" seconds
    Then I should see "Coupon code \"BEHAT10\" removed successfully." in the ".sc_couponmessage" "css_element"
    And ".sc_coupondiscount" "css_element" should not exist
    And I should see "10.00 EUR" in the ".sc_price_label .sc_deductible" "css_element"

  Scenario: Coupon redeem: a typed code is only applied with the apply button
    Given I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    When I set the field "couponcode" to "BEHAT10"
    And I click on ".sc_price_label .sc_credit label" "css_element"
    And I wait "1" seconds
    Then ".sc_coupondiscount" "css_element" should not exist
    And I should see "10.00 EUR" in the ".sc_price_label .sc_totalprice" "css_element"
    When I set the field "couponcode" to "BEHAT10"
    And I click on "[data-id=\"applycoupon\"]" "css_element"
    And I wait "1" seconds
    Then I should see "1.00 EUR" in the ".sc_price_label .sc_coupondiscount" "css_element"
    And I should see "9.00 EUR" in the ".sc_price_label .sc_totalprice" "css_element"
