# Cursor Agent Prompt 00 — Audit ONLY

You are working in the Restaurant ERP Laravel repository. Read `docs/store-inventory/01-functional-baseline.md`, `02-data-contract.md`, `03-test-checklist.md`, and the approved v1.2 specification if present.

**Do not write implementation code, migrations, routes, or views.** Audit the repository and any safely accessible schema metadata. Locate existing Store/stock/vendor/category/quantity models, migrations, routes, controllers, requests, Blade views, JS, permissions, sidebar and tests. Inspect Reports module patterns and current Sales boundaries. Check how stock is calculated, whether receipts already exist, historical data dependencies, and whether bill statuses include Credit. Identify whether unit conversions are already supported. Do not assume table names from the proposal.

Produce `docs/store-inventory/05-existing-module-audit.md` using `04-audit-template.md`. Include a concept-to-existing-table mapping, risks, recommended reuse, non-destructive migration strategy, test plan, and questions requiring approval. You may read files and run read-only inspection commands, but do not modify application code or production data. Stop and request approval before Phase 1.

Repository rules: inspect before editing; use existing project conventions; do not modify completed Sales module; do not invent existing schema/routes; avoid unnecessary dependencies. Provide file-by-file change report and tests. Stop at the requested gate.
