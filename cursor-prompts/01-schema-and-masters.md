# Cursor Agent Prompt 01 — Schema and master backend (approval required)

PRECONDITION: approved `05-existing-module-audit.md` and explicit GO for Phase 1. If absent, STOP and run Prompt 00 instead. Read baseline, data contract, and tests. Implement only the minimum compatible **additive** schema/model/backend work for Item Categories, Units of Measure, Inventory Items and Vendors. Reuse legacy models/tables when approved; do not create parallel masters. Implement Form Requests, service layer if existing architecture uses it, permissions/policies, safe soft deletion/deactivation, and feature tests. Add Category to Inventory Item. Validate `0 <= reorder < max` and unique item/category constraints consistent with audit.

Vendor code: generate server-side, immutable on update, database unique; use concurrency-safe sequence/locking or an approved equivalent. Never use `MAX(code)+1` without concurrency protection. If existing vendor codes conflict with new format, preserve them and document migration rules.

Do not implement receipt posting, Blade pages or Sales integration yet. Do not run destructive migrations or modify production data. Report exact migration plan, models/routes created or reused, tests and blockers. Stop for review.

Repository rules: inspect before editing; use existing project conventions; do not modify completed Sales module; do not invent existing schema/routes; avoid unnecessary dependencies. Provide file-by-file change report and tests. Stop at the requested gate.
