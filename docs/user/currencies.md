[Back to documentation index](../README.md)

# Currencies

The shopping cart currently supports **one currency per site**. Multi-currency setups are only supported to a limited extent.

## How it works today

- The currency of the shopping cart is set in the plugin settings (`globalcurrency`). Payments through a payment gateway are always charged in this currency.
- Items report their own currency, but the cart adds up the item prices without converting or comparing currencies. All items offered through the cart must therefore use the global currency.
- Credits store a currency, but a user can only hold credits in one currency. Credits in a second currency are refused with an error message.

## Coupons

- Percentage coupons work independently of the currency.
- Absolute coupons have a currency field. This field is stored, but it is **not** compared with the cart currency yet: a coupon of 5 USD deducts 5 units from a cart in EUR. Only create absolute coupons in the global currency. See [Wunderbyte-GmbH/moodle-local_shopping_cart#208](https://github.com/Wunderbyte-GmbH/moodle-local_shopping_cart/issues/208).
- The booking fee is never discounted by a coupon.

## Recommendation

Use a single currency for all items, credits and coupons of a site. If you need several currencies, please contact Wunderbyte before setting them up.
