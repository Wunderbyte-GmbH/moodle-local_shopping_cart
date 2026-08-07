[Back to user documentation](README.md)

# VAT number check in checkout

This guide explains the VAT number step of the checkout process: when it is shown, how VAT numbers are validated, what the **non-European** option does, and how the selection affects the taxes applied to the cart.

---

## Table of Contents

1. [What the step does](#1-what-the-step-does)
2. [Settings](#2-settings)
3. [Country selection](#3-country-selection)
4. [Validation per region](#4-validation-per-region)
5. [Non-European customers](#5-non-european-customers)
6. [Effect on taxes](#6-effect-on-taxes)
7. [Troubleshooting](#7-troubleshooting)

---

## 1. What the step does

During checkout, business customers can enter their VAT number together with the country that issued it. A valid cross-border EU VAT number activates the **reverse charge** treatment (no VAT is added to net prices). The entered number is stored with the purchase and appears on the ledger and invoice data.

## 2. Settings

All settings live under *Site administration → Plugins → Local plugins → Shopping Cart*:

| Setting | Effect |
|---------|--------|
| `showvatnrchecker` | Shows the VAT number step in checkout. |
| `onlywithvatnrnumber` | Makes the step mandatory: checkout is only possible after this step is valid. |
| `owncountrycode` | Your own country (ISO2). Customers from this country never get reverse charge. |
| `ownvatnrnumber` | Your own VAT number. Entering it as a customer is rejected. |

## 3. Country selection

The dropdown lists, in this order:

1. **No VAT number** (`novatnr`) — default; for consumers and customers without a VAT registration.
2. **Non-European (outside the EU)** (`noneu`) — for customers outside the EU VAT area, see [section 5](#5-non-european-customers).
3. The EU member states, **EU** and **GB**.

The **EU** entry is for VAT numbers with the literal `EU` prefix: non-EU businesses registered in the EU One-Stop-Shop (non-Union scheme) receive such numbers.

The step only shows what applies to the selection: for **No VAT number** neither a number input nor a verification appears; for **Non-European** a number input without a Verify button; for EU countries and GB the number input plus the Verify button.

## 4. Validation per region

| Selection | Validation |
|-----------|-----------|
| EU member state | Online check against the official [VIES](https://ec.europa.eu/taxation_customs/vies/) service of the European Commission, triggered by the **Verify** button. |
| GB | Offline format and checksum validation (UK numbers are no longer part of VIES), triggered by the **Verify** button. |
| Non-European | **No validation** — no Verify button is shown for this selection, see below. |

A technical failure of the online check (service unavailable, rate limit) is reported as such and is not treated as "invalid number".

## 5. Non-European customers

Customers from outside the EU VAT area select **Non-European (outside the EU)** at the top of the dropdown. For this selection:

- **No VAT check is performed — and none is pretended.** Choosing *Non-European* rebuilds the step **without a Verify button**: there is no service we could verify such numbers against, so the UI does not offer a validation. The choice is saved automatically (like selecting an address on the address step), and the optional number is saved as soon as it is entered — no button to click. Switching back to an EU country rebuilds the step and brings the Verify button back for a real check.
- Whatever is entered as the number (for example a Swiss `CHE-…` UID or a US EIN) is stored **as entered, unchecked**, and shown on the ledger.
- **The number is optional.** The step also passes without any number — important when `onlywithvatnrnumber` makes the step mandatory, because non-EU customers usually have no number the EU systems could verify.
- **It does not change the tax country.** Taxes keep following the country of the billing address (see next section). The selection never triggers the EU reverse charge logic.

## 6. Effect on taxes

The tax rate for the cart is resolved in this order:

1. **EU reverse charge:** a *valid* EU VAT number from a country other than `owncountrycode` sets the VAT of the cart to 0%.
2. **Per-country tax matrix:** otherwise the country of the **billing address** is looked up in the tax matrix (*Shopping Cart settings → Tax categories*). A line such as `Ch A:0 B:0 C:0` gives Swiss customers 0% in all categories.
3. **Default line:** countries without their own matrix line fall back to the `default` line of the matrix.

The **Non-European** selection deliberately does not interfere with this order: export tax treatment is configured through the tax matrix lines of the respective countries, not through the VAT step.

## 7. Troubleshooting

- *"The VAT number could not be verified right now"* — the VIES service is temporarily unavailable or rate-limited; try again later. This is not a statement about the number itself.
- *Own VAT number rejected* — entering the site operator's own VAT number (`ownvatnrnumber`) is blocked by design.
- With developer debugging enabled, the step shows a diagnostic trace of the last check (region detection, validator used, raw response).
