# Provisional data contract — NOT an approved migration

Suggested entities **only if not already represented compatibly**:
- `inventory_categories`: name, code?, description?, active, audit timestamps.
- `units_of_measure`: name, symbol, dimension, conversion metadata only if approved.
- `inventory_items`: code, name, category FK, base UoM FK, reorder level, maximum level, active.
- `vendors`: immutable unique vendor code, name, contact/tax metadata, active.
- `stock_receipts`: vendor FK, vendor bill reference/date, payment status, notes, lifecycle status, idempotency key, creator/poster.
- `stock_receipt_items`: receipt FK, item FK, quantity, UoM, unit cost, batch/expiry, cost snapshot.
- `stock_movements`: item FK, signed quantity in base UoM, movement type, source reference, posting timestamp, actor; reversal references as appropriate.
- Optional `inventory_lots` if existing system needs lot-level balances.

## Invariants
- Server owns authoritative IDs, codes, prices used for receipt posting, and stock balance.
- Vendor code: unique index, generated transactionally with a concurrency-safe strategy; never updated.
- Stock writes are atomic and race-safe; use row locks / atomic balance handling as supported by actual database.
- No direct editable `current_stock` field without ledger reconciliation.
- Posted receipts: append-only movements; correction by audited reversal.
- Invoice uniqueness: agree policy after auditing existing vendor invoices (e.g. vendor + bill number), not globally assumed.
- Preserve historical records and avoid destructive migration.
- `Credit` must not be mapped to `Unpaid` by enum casts, validation or UI.

## API / UI contracts (conceptual)
- Item autocomplete: query, scope `low_stock` (default) or `all`, paginated results with item ID/code/name, current stock, reorder, maximum, category, UoM.
- Vendor autocomplete: query -> ID, name, immutable code.
- Server revalidates all dependent values on submission; do not trust read-only browser fields.

Actual schema, route names and controller names must be determined in Phase 0 audit.
