# Craft Commerce Accounting Source Path Map

Verified on 2026-07-02 against a disposable Craft CMS 5.10.8.1 installation with Craft Commerce 5.7.x-dev. The fixture order used two custom line items, discount/shipping/tax adjustments, a billing address, and a completed-order timestamp.

No client installation or client data was used.

| Column | Field path | Live value | Status |
|---|---|---:|---|
| Order Number | `number` | `ACC-1001` | Stable |
| Order Date | `dateOrdered` | `2026-06-15 12:00:00` | Stable |
| Customer Email | `email` | `demo@example.test` | Stable |
| Order Status | `orderStatus.handle` | `new` | Stable |
| Billing Name | `billingAddress.fullName` | `Ada Lovelace` | Stable |
| Billing Country | `billingAddress.countryCode` | `NL` | Stable |
| Billing City | `billingAddress.locality` | `Amsterdam` | Stable |
| Payment Status | `paidStatus` | `unpaid` | Stable |
| Subtotal | `itemSubtotal` | `40` | Stable |
| Discount Total | `totalDiscount` | `-5` | Stable |
| Shipping Total | `totalShippingCost` | `4` | Stable |
| Tax Total | `totalTax` | `8.19` | Stable |
| Grand Total | `totalPrice` | `47.19` | Stable |
| Currency | `paymentCurrency` | `USD` | Stable |
| Total Quantity | `totalQty` | `3` | Stable |
| Line Item SKUs | `lineItems.sku` | `RING-01, CHAIN-02` | Stable; preset overrides separator to ` | ` |
| Line Item Titles | `lineItems.description` | `Silver Ring, Gold Chain` | Stable; preset overrides separator to ` | ` |
| Line Item Quantities | `lineItems.qty` | `2, 1` | Stable; preset overrides separator to ` | ` |
| Line Item Totals | `lineItems.total` | `20, 20` | Stable; preset overrides separator to ` | ` |

## Decisions

- Use `dateOrdered`, not `dateCreated`. A Commerce order can exist as a cart before checkout; `dateOrdered` is set when the order is completed.
- Use `paidStatus`, which resolves to Commerce's `unpaid`, `partial`, `paid`, or `overpaid` status.
- Keep discount totals signed. Commerce represents discounts as negative adjustments.
- Export Commerce's recorded aggregate totals. The preset does not recalculate or certify tax.
- All four must-have fields resolved: Order Number, Order Date, Customer Email, and Grand Total.
- All 15 independent non-line-item fields resolved, so the spike exit criterion passes.

## Runtime Evidence

```text
number='ACC-1001'
dateOrdered='2026-06-15 12:00:00'
email='demo@example.test'
orderStatus.handle='new'
billingAddress.fullName='Ada Lovelace'
billingAddress.countryCode='NL'
billingAddress.locality='Amsterdam'
paidStatus='unpaid'
itemSubtotal=40.0
totalDiscount=-5.0
totalShippingCost=4.0
totalTax=8.19
totalPrice=47.19
paymentCurrency='USD'
totalQty=3
lineItems.sku='RING-01, CHAIN-02'
lineItems.description='Silver Ring, Gold Chain'
lineItems.qty='2, 1'
lineItems.total='20, 20'
```

## CI Decision

Commerce integration coverage uses a disposable local Craft/Commerce installation and is mandatory before release. CI automation remains optional until a Commerce CI license and durable database service are configured.
