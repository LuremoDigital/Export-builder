# Craft Commerce Refund Source Path Map

Verified on 2026-07-07 against the Craft Commerce 5.6.7 source package. No client installation or client data was used.

Refunds are Commerce transaction records, not direct order fields. A refund is a child transaction with `type = refund`; accounting exports should use successful refund transactions only.

| Column | Field path | Status |
|---|---|---|
| Refund Amounts | `transactions.refund.success.paymentAmount` | Stable |
| Refund Dates | `transactions.refund.success.dateCreated` | Stable |
| Refund References | `transactions.refund.success.reference` | Stable |
| Refund Currency | `transactions.refund.success.paymentCurrency` | Stable |
| Refund Notes | `transactions.refund.success.note` | Stable, optional |
| Refund Parent Transaction IDs | `transactions.refund.success.parentId` | Stable, optional audit link |

Use `settings.separator = " | "` for multi-refund order rows. Use `settings.decimalPlaces = 2` for refund amounts.

## Decisions

- Use `paymentAmount` for the default refund amount. Commerce sends and validates refund requests in the payment currency.
- Include `paymentCurrency` when refund amounts are exported.
- Use `amount` only if a future preset needs base/order-currency reconciliation alongside gateway-payment amounts.
- Use `dateCreated`, not `dateUpdated`, for the refund date. `dateCreated` is when Commerce records the refund transaction.
- Filter to `success` in the field path. Commerce can persist failed refund attempts with `type = refund`; those should not count as bookkeeper-facing refund amounts.
- Prefer sibling refund columns on the existing order-row preset when the refund-aware v2 gate is met. A separate one-row-per-refund export needs a transaction element source, which this plugin does not currently expose.

## Source Evidence

- `craft\commerce\records\Transaction` defines `TYPE_REFUND = "refund"` and `STATUS_SUCCESS = "success"`.
- Commerce's install migration stores transaction `orderId`, `parentId`, `type`, `status`, `paymentAmount`, `paymentCurrency`, `amount`, `reference`, `note`, and `dateCreated`.
- `craft\commerce\elements\Order::getTransactions()` exposes transactions to order field paths.
- `craft\commerce\elements\db\OrderQuery::withTransactions()` eager-loads transactions for order exports.
- `craft\commerce\services\Payments::_refund()` creates a child refund transaction, sets `paymentAmount`, `amount`, and `note`, then stores the gateway `reference` and `status`.
- `craft\commerce\services\Transactions::refundableAmountForTransaction()` sums only successful refund `paymentAmount` values for the parent transaction.

## Deferred Preset Columns

The Commerce accounting preset does not include refund columns in v1. If the refund-aware v2 gate is met, append these after `Currency`:

| Label | Path | Settings |
|---|---|---|
| Refund Amounts | `transactions.refund.success.paymentAmount` | `separator: " | "`, `decimalPlaces: 2` |
| Refund Dates | `transactions.refund.success.dateCreated` | `separator: " | "` |
| Refund References | `transactions.refund.success.reference` | `separator: " | "` |
| Refund Currency | `transactions.refund.success.paymentCurrency` | `separator: " | "` |

## Open Check

Run a disposable Craft/Commerce fixture with one successful refund and one failed refund attempt before shipping a refund-aware preset. The expected golden CSV should include only the successful refund in the amount/date/reference columns.
