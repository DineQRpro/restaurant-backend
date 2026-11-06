# Functional baseline — approved v1.2

## Navigation
Admin sidebar: Store & Inventory. Planned sections: Dashboard, Inventory Items, Stock Receipts, Stock Movements, Stock Alerts, Masters (Item Categories, Units of Measure, Inventory Items, Vendors), Reports. Show only completed screens. Design permissions for future roles.

## Masters (CRUD)
- Item Category Master: unique name within appropriate scope; optional code/description; active state.
- Unit of Measure Master: name, symbol, dimension, optional conversion metadata; reject incompatible dimensional conversions.
- Inventory Item Master (formerly Store Items Threshold Level and Max Limit): unique code, name, category, base UoM, reorder level, maximum level, active; require `0 <= reorder < maximum`.
- Vendor Master: name, contact/tax details as appropriate; server-generated immutable unique vendor code, e.g. `VEN-000001` (illustrative only).
- Prefer deactivation/soft deletion for referenced masters; audit changes.

## Receipts
- Replace legacy Add New Store Items with Stock Receipt / Goods Receipt.
- One vendor invoice header; multiple item lines.
- Vendor autocomplete searches name or code, displays `Vendor Name — Vendor Code`, submits vendor ID.
- Item autocomplete defaults to active items whose stock <= reorder; explicit All Items toggle; low stock ranked first.
- Item selection loads category, base unit, reorder, maximum, current stock as read-only fields.
- Line fields: received quantity, UoM, unit cost, optional batch and expiry. Last cost may suggest but not silently dictate cost.
- Header fields: vendor, bill number, bill date, payment status, optional notes.
- Payment status options: **Paid / Unpaid / Partially Paid / Credit**; Credit is distinct from Unpaid.
- Receipt posting updates stock independently of bill settlement.
- Projected stock above maximum: show current, incoming, projected and maximum in a nonblocking warning.
- Use idempotency token and transactional receipt + lines + ledger posting. Posted receipts cannot be hard deleted.

## Inventory accounting
Current stock = opening balance + sum of signed posted movements. Movement types include opening, receipt, issue/consumption, adjustment, waste, return/reversal as applicable. Maintain stock movement auditability. Batch/expiry belong to lots/receipt lines. Keep menu selling price separate from inventory cost.

## Approved scope
One central store. No transfers, kitchen consumption, purchase orders, recipe/BOM, Sales deduction, or detailed Accounts Payable integration in this phase.

## Unresolved
1. Are purchase units ever different from base stock units (carton to pieces, etc.)? Inspect legacy behavior; request approval before implementing conversions.
2. Which legacy tables/models/data must be preserved? Requires actual repository/database audit.
