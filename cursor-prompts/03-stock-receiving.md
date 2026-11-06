# Cursor Agent Prompt 03 — Stock Receipts and ledger

PRECONDITION: approved master screens and audited schema. Read baseline/data contract/test checklist. Implement Stock Receipt (Goods Receipt) with vendor autocomplete (`Name — Code`, submit ID), one vendor bill header and multiple item lines. Payment status: **Paid / Unpaid / Partially Paid / Credit**, stored distinctly. Item autocomplete defaults to active low-stock (`current <= reorder`), with explicit All Items search; server pagination/debounce. Selecting item fills category, base UoM, reorder, maximum and current stock read-only. Each line supports quantity, UoM, cost, optional batch/expiry. Keep inventory cost separate from sales price.

Calculate projected stock and show a **nonblocking** warning when above max; server must not reject solely due to max. Revalidate item/vendor and dependent values server-side. Post receipt, lines and signed stock ledger entries atomically with idempotency and concurrency safety. No hard-delete of posted receipts: implement audited reversal/void according to existing conventions. Receipt posting must not depend on payment status. Support one central store only.

If purchase-to-base UoM conversion behavior is not approved, STOP before implementing ambiguous conversion logic and ask for decision. Do not auto-deduct inventory from Sales. Add feature tests for exact-threshold, all-items, credit, over-max, duplicate submission, rollback, concurrency and reversal. Stop for review.

Repository rules: inspect before editing; use existing project conventions; do not modify completed Sales module; do not invent existing schema/routes; avoid unnecessary dependencies. Provide file-by-file change report and tests. Stop at the requested gate.
