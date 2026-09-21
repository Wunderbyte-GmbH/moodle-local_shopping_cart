[Back to documentation index](../README.md)

# Legacy checkout

The checkout exists in two renderings. The current one builds every step as a dynamic form and
puts the page into a card with a tab for the purchase history. The **legacy checkout** is the
checkout of the USI release line, from before that rebuild.

Switch it on in the plugin settings with **Legacy checkout** (`legacycheckout`). It is off by
default, and it changes the rendering only — prices, taxes, credits, the payment itself and
everything the cashier sees are untouched.

## What changes

| | Current rendering | Legacy checkout |
|---|---|---|
| Address, VAT number, terms steps | dynamic forms | the former templates |
| Page frame | card with a checkout tab and a purchase history tab | one flat page |
| Purchase history | fetched when the tab is opened | printed below the checkout |
| Coupon field | shown when coupons are enabled | not shown |

## What is not available while it is on

The legacy checkout is a frozen state, so anything that was built into the form rendering after
the rebuild is absent:

- **Guest checkout.** The login panel and the add, edit and delete address actions of the current
  rendering are placed around the step form; without a form they are not rendered. Addresses can
  still be managed in the legacy step itself, but an anonymous visitor cannot take over a cart.
- **The coupon field in the checkout.** Coupons themselves keep working — a coupon applied
  elsewhere still reduces the price, and the cashier still sees it — but buyers cannot enter one
  on the checkout page.

The VAT number check is not affected: both renderings verify and store the number the same way,
including the non-European option and the export handling for Great Britain.

## When to use it

Only for sites whose users should keep the interface they know. New sites should stay on the
current rendering, which is the one that keeps getting features.
