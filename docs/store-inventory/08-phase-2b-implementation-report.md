# Phase 2B implementation report

Goods receipts, the stock ledger, and inventory visibility are implemented on the existing Laravel admin. Kitchen consumption, wastage, transfers, purchase orders, vendor payment settlements, and legacy grocery import were not started.

## Migrations and tables

`database/migrations/2026_11_03_120000_create_goods_receipt_and_stock_ledger_tables.php` creates:

- `goods_receipts`
- `goods_receipt_items`
- `inventory_stock_movements`

It also inserts `document_sequences.name = goods_receipt` at `next_number` 1. Existing `order`, `bill`, and `vendor` rows are not changed by the migration.

Applied on the DDEV development database `db` only. `order` stayed at 2647 and `bill` stayed at 2629.

MariaDB identifier names are limited to 64 characters, so the movement unique indexes are named `stock_movements_line_type_unique` and `stock_movements_reversal_unique`. This Laravel version has no `Blueprint::check()`. MariaDB gets `CHECK` constraints. SQLite, used by PHPUnit, gets equivalent `BEFORE INSERT` and `BEFORE UPDATE` triggers because SQLite cannot add a check to an existing table.

## Models and services

- Models: `GoodsReceipt`, `GoodsReceiptItem`, `InventoryStockMovement`
- Enums: `GoodsReceiptStatus`, `GoodsReceiptPaymentStatus`, `StockMovementType`
- `GoodsReceiptNumberAllocator` locks `document_sequences` for `goods_receipt` and formats `GRN-%06d`
- `InventoryMoney` uses bcmath. Taxable amount is quantity times the tax-exclusive unit price, rounded half away from zero to two decimals. GST is that taxable amount times the rate divided by 100, rounded the same way. Line total is taxable plus GST. Header totals are sums of the stored line amounts.
- `VendorBillNumber` trims the bill number, collapses consecutive whitespace to one space, and keeps punctuation. The unique key is the uppercase form of that display value.
- `GoodsReceiptService` creates, updates, and deletes drafts.
- `StockLock` locks inventory item rows in ascending id order.
- `GoodsReceiptPoster` and `GoodsReceiptVoider` follow that lock order inside one transaction.
- `StockBalanceService` calculates balance as `SUM(quantity_in) - SUM(quantity_out)`.

`DocumentNumberService` still accepts only order and bill numbers.

## Routes and controllers

Under the existing admin authentication group:

- `admin/inventory/receipts` — `GoodsReceiptController`
- `admin/inventory/stock`, `/movements`, and `/low` — `InventoryStockController`

Autocomplete routes are registered before the receipt id route. Banquet `admin/receipts` is unchanged.

## Blade pages

- `resources/views/admin/inventory/receipts/` — list, create, edit, show, print, and the shared form
- `resources/views/admin/inventory/stock/` — overview, movements, and low stock
- Sidebar children under Store & Inventory: Goods Receipts, Inventory Overview, Stock Movements, Low Stock

The form shows vendor autocomplete as `Vendor Name — Vendor Code`, low-stock and all-items search, read-only category, unit, stock, reorder, and maximum, plus a display of line and invoice totals. The server recalculates money before saving.

## Permissions

Granted to admin through `PermissionSeeder` and `RolePermissionSeeder`:

- `inventory.receipt.view`
- `inventory.receipt.create`
- `inventory.receipt.update`
- `inventory.receipt.post`
- `inventory.receipt.void`
- `inventory.stock.view`

Waiter, captain, and counter do not receive them. Both seeders were run on the development database.

## Receipt lifecycle

- Draft receipts do not create movements and may be edited or deleted.
- Posting locks the receipt, locks the item rows, recalculates money and maximum-stock warnings, inserts one inbound movement per line, and sets Posted.
- A posted receipt cannot be edited, deleted, or posted again. A repeated post returns the posted receipt.
- Voiding requires a reason and records the user and time. It inserts one reversal per original inbound movement. A repeated void returns the voided receipt.
- A void is rejected when the reversal would make any item balance negative.
- A voided receipt stays visible and cannot be posted, edited, or deleted.

## Ledger integrity

Movements are append-only through the services. Original receipt movements are not updated or deleted. Reversals point at the original movement. Unique keys prevent a second receipt movement or a second reversal for the same line. Stock is not stored on the inventory item.

## GST calculation policy

Unit prices are tax exclusive. Each line has its own optional GST rate from 0 to 100. There is one combined purchase GST amount per line, not the sales split of CGST and SGST. Historical posted lines keep the GST values stored at posting. This is not a GST return or input-tax-credit ledger.

## Payment validation policy

Paid amount is stored and outstanding is grand total minus paid amount. Payment status does not control stock posting.

- Paid: paid amount equals the grand total.
- Unpaid: paid amount is 0.00.
- Credit: paid amount is 0.00, and it is a different status from Unpaid.
- Partially Paid: paid amount is greater than 0.00 and less than the grand total.
- A zero-total invoice cannot be Partially Paid. Paid, Unpaid, and Credit are allowed only when the paid amount is 0.00.

A zero unit price is allowed. Posting it requires `confirm_zero_prices`. The quantity still creates a stock movement.

Vendor bill uniqueness applies to every remaining receipt row: draft, posted, and voided. Deleting a draft removes the row and releases the bill key. The same bill key may be used for a different vendor.

## Tests

Passed through DDEV:

- `tests/Unit/Inventory/InventoryMoneyTest.php` — 5 tests
- `tests/Feature/Admin/Inventory/GoodsReceiptTest.php` — 11 tests
- `tests/Feature/Admin/Inventory/InventoryMasterTest.php`
- `tests/Feature/Authorization/PermissionSeederTest.php`
- `tests/Feature/Authorization/RolePermissionTest.php`
- `tests/Feature/Admin/Receipts/ReceiptUiTest.php`
- `tests/Feature/Dashboard/ViewBillTest.php`
- `tests/Feature/Admin/AdminBillPaymentTest.php`

The goods receipt feature tests cover draft creation, sequential GRN numbers, multi-line receipts, duplicate bill rejection, the same bill for another vendor, payment constraints, GST rounding, zero-price confirmation, posting, a second post, void, a second void, negative-stock void rejection, edit denial, 403 responses, and low-stock ordering.

Failed: none in the suites above.

Blocked: none. The overlapping MariaDB checks are recorded under **Final Acceptance Verification**.

## Browser verification

On `http://restaurant-backend.ddev.site`, a temporary admin created a vendor and draft `GRN-000001` for Chana. The print view showed subtotal 20.00, GST 1.00, grand total 21.00, paid 0.00, and outstanding 21.00. Inventory overview showed Chana at balance 0.000 and low stock Yes. Low stock showed shortage 10.000 and a new-receipt shortcut. Movement history was empty, confirming the draft did not post stock. Banquet receipts and the order-wise report, including View Bill, still opened.

Post and void were not clicked in that first browser pass. Final acceptance posted and voided a separate receipt on an isolated database.

The temporary user, vendor, and draft receipt were deleted afterward. Allocated vendor code `VEN-000003` and receipt number `GRN-000001` were not reused.

## Deviations

- Purchase GST is one combined rate per line. Sales billing remains split CGST/SGST with its existing calculator.
- Database checks are native `CHECK` constraints on MariaDB and triggers on SQLite.
- The movement unique index names are shortened for MariaDB's 64-character limit.
- The receipt form shows running totals in the browser. Stored totals always come from `InventoryMoney`.

## Outstanding risks

- Deleting a vendor or item that is referenced by any receipt, including a draft, is blocked by foreign keys.
- Vendor and goods-receipt sequences advance even when the created row is later deleted.

## Final Acceptance Verification

Acceptance used a disposable MariaDB database, `phase2b_accept`, on the DDEV MariaDB 11.8 server. The restaurant database `db` was not migrated, wiped, or posted into. The web app was pointed at `phase2b_accept` only for the browser pass, then pointed back at `db`.

### Fix found during the overlap check

Overlapping draft creation could fail one of the two transactions with MariaDB error 1020, "Record has changed since last read in table 'document_sequences'". Laravel reports that as a deadlock. `GoodsReceiptService::create()` now retries the whole draft transaction up to 5 times. Receipt numbering, bill uniqueness, and stock rules are unchanged. After the retry, two overlapping creates committed `GRN-000002` and `GRN-000003` and advanced the isolated `goods_receipt` sequence from 2 to 4.

An earlier poll queried `performance_schema.data_lock_waits`, which this server does not have, and used a second database connection as the hold barrier. Those runs completed with the right receipt and stock rows but recorded `lock_waits_observed: 0`, so they were not accepted. The script now holds the lock and waits on a file, and counts `information_schema.innodb_trx` rows in state `LOCK WAIT`.

### Concurrency

Command, after migrating only `phase2b_accept`:

`ddev exec env DB_DATABASE=phase2b_accept php tests/acceptance/phase2b_mariadb.php`

The script refuses to run unless the connected database is `phase2b_accept`. It forks separate PHP processes, each with its own MariaDB connection.

Official result, all four checks `passed`:

- Overlapping post of one two-line draft: `lock_waits_observed` 2, child exits `[0, 0, 0]`, receipt stayed `GRN-000001` and `posted`, exactly 2 `receipt` movements, balances rice `2.000` and dal `1.500`.
- Overlapping receipt numbers: `lock_waits_observed` 2, numbers `GRN-000002` and `GRN-000003`, sequence 2 to 4, no duplicate number.
- Overlapping void: `lock_waits_observed` 2, status `voided`, exactly 2 reversals, original movement quantities unchanged, balances returned to rice `2.000` and dal `1.500`.
- Negative-stock void: rejected with "Voiding this receipt would make stock negative.", status stayed `posted`, movement ids unchanged.

No check was blocked.

### Browser posting and voiding

Logged in as the temporary admin `phase2b-accept` at `http://restaurant-backend.ddev.site`. Created vendor "Browser Accept Vendor" (`VEN-000001`) and item "Browser Sugar" (`BRW-SUGAR`). Before the receipt, Inventory Overview showed Browser Sugar balance `0.000`.

Saved draft `GRN-000007`, vendor bill `BRW-100`, Unpaid, paid `0.00`:

- Browser Sugar, quantity `2.000`, price `10.00`, GST 5%
- Accept Rice, quantity `1.500`, price `4.00`, GST 0%

The draft had no stock movements. Sugar and rice balances stayed `0.000`.

Posted from the receipt page with the Post receipt button. The page changed to Posted · Unpaid. Overview then showed Browser Sugar `2.000` and Accept Rice `1.500`. Stock Movements for `GRN-000007` showed one Receipt row per line: sugar in `2.000`, rice in `1.500`.

Print showed: Subtotal 26.00, GST 1.00, Grand total 27.00, Paid 0.00, Outstanding 27.00, Posted, Unpaid.

The posted page no longer offers Post. A second POST to the same receipt URL from that admin session left the status `posted` and left the two original movements, ids 10 and 11, as the only inbound rows.

Voided from the page with reason "Acceptance void of browser receipt". The page showed Voided, the reason, the user, and the timestamp. Movements 10 and 11 were unchanged. Two reversal rows were added, quantity out `2.000` and `1.500`. Sugar and rice balances returned to `0.000`. Movement history for receipt 7 showed two Receipt rows and two Reversal rows.

The void form is removed after voiding. A second POST to the void URL did not add reversals. The receipt stayed `voided`, with 2 receipt movements and 2 reversal movements.

### Regression

`ddev exec php artisan test tests/Feature/Admin/Inventory tests/Unit/Inventory tests/Feature/Authorization/PermissionSeederTest.php tests/Feature/Authorization/RolePermissionTest.php tests/Feature/Admin/Receipts/ReceiptUiTest.php tests/Feature/Dashboard/ViewBillTest.php tests/Feature/Admin/AdminBillPaymentTest.php tests/Feature/Reports/ReportAuthorizationTest.php`

88 tests passed, 587 assertions. These suites use SQLite in memory and do not write the restaurant database.

### Restaurant database

After the browser pass and after dropping `phase2b_accept`:

| Sequence | next_number |
| --- | --- |
| order | 2647 |
| bill | 2629 |
| vendor | 4 |
| goods_receipt | 2 |

`db.goods_receipts` is 0. `db.inventory_stock_movements` is 0. `db.vendors` is 0. `db.inventory_items` is 1. No document sequence was reset.

### Cleanup

`DROP DATABASE phase2b_accept` removed the disposable fixture, including its posted and voided movements. The restaurant database was not dropped or refreshed. The temporary password file and the lock-probe script were deleted. `tests/acceptance/phase2b_mariadb.php` remains as the overlap check.

The web container was restarted with its normal database setting. `php artisan db:show` reports database `db`.

Phase 3 was not started. Nothing was deployed.

## Changed files for Phase 2B

- `app/Enums/GoodsReceiptPaymentStatus.php`
- `app/Enums/GoodsReceiptStatus.php`
- `app/Enums/StockMovementType.php`
- `app/Http/Controllers/Admin/Inventory/GoodsReceiptController.php`
- `app/Http/Controllers/Admin/Inventory/InventoryStockController.php`
- `app/Http/Requests/Admin/Inventory/GoodsReceiptIndexRequest.php`
- `app/Http/Requests/Admin/Inventory/GoodsReceiptRequest.php`
- `app/Http/Requests/Admin/Inventory/PostGoodsReceiptRequest.php`
- `app/Http/Requests/Admin/Inventory/StockIndexRequest.php`
- `app/Http/Requests/Admin/Inventory/VoidGoodsReceiptRequest.php`
- `app/Models/DocumentSequence.php`
- `app/Models/GoodsReceipt.php`
- `app/Models/GoodsReceiptItem.php`
- `app/Models/InventoryItem.php`
- `app/Models/InventoryStockMovement.php`
- `app/Models/Vendor.php`
- `app/Services/Inventory/GoodsReceiptNumberAllocator.php`
- `app/Services/Inventory/GoodsReceiptPoster.php`
- `app/Services/Inventory/GoodsReceiptService.php`
- `app/Services/Inventory/GoodsReceiptVoider.php`
- `app/Services/Inventory/InventoryMoney.php`
- `app/Services/Inventory/StockBalanceService.php`
- `app/Services/Inventory/StockLock.php`
- `app/Services/Inventory/VendorBillNumber.php`
- `app/Support/AdminNavigation.php`
- `database/migrations/2026_11_03_120000_create_goods_receipt_and_stock_ledger_tables.php`
- `database/seeders/PermissionSeeder.php`
- `docs/store-inventory/03-test-checklist.md`
- `docs/store-inventory/07-phase-2-schema-ledger-design.md`
- `docs/store-inventory/08-phase-2b-implementation-report.md`
- `resources/views/admin/inventory/receipts/`
- `resources/views/admin/inventory/stock/`
- `resources/views/admin/partials/alerts.blade.php`
- `routes/web.php`
- `tests/Feature/Admin/Inventory/GoodsReceiptTest.php`
- `tests/Feature/Admin/Inventory/InventoryMasterTest.php`
- `tests/Feature/Authorization/PermissionSeederTest.php`
- `tests/Feature/Authorization/RolePermissionTest.php`
- `tests/Unit/Inventory/InventoryMoneyTest.php`
- `tests/acceptance/phase2b_mariadb.php`
