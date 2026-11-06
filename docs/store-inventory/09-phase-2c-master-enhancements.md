# Phase 2C master code standardization and vendor details

Phase 2C adds automatic codes for the four store masters and extra vendor fields. Goods receipt posting, voiding, receipt numbering, the stock ledger, sales, banquet receipts, and the menu were left as they were. Phase 3 was not started.

## Existing code audit

Checked on the DDEV development database `db` before the migration:

| Sequence | next_number |
|---|---|
| order | 2647 |
| bill | 2629 |
| vendor | 4 |
| goods_receipt | 2 |

Master rows at that time:

- Item category `Grains` had a null code. There was no issued category code to preserve.
- Unit `Kilo Gram` (`KG`) had no code column.
- Inventory item `Chana` already had code `CH1234`. That value does not match `ITM-000001`. It was left unchanged.
- Vendors, goods receipts, receipt lines, and stock movements were empty.

`CH1234` does not collide with `ITM-######`, so new items can start at `ITM-000001` without rewriting history.

## Schema changes

`database/migrations/2026_11_03_180000_standardize_inventory_master_codes_and_vendor_details.php`:

- Adds `units_of_measure.code`, fills any missing unit code, then makes it required and unique.
- Fills any category whose code is null, then makes `inventory_categories.code` required. The existing unique index stays.
- Adds nullable vendor columns: `shop_name`, `poc_name`, `bank_name`, `bank_account_number` (`varchar(34)`), and `ifsc_code` (`varchar(11)`).
- Inserts `document_sequences` rows `inventory_category`, `unit_of_measure`, and `inventory_item` when they are missing.

The migration does not update `order`, `bill`, `vendor`, or `goods_receipt`. Rolling it back drops the new vendor columns and the unit code, makes the category code nullable again, and deletes only the three new sequence rows. It does not rewrite `CH1234` or drop master tables.

Applied with `php artisan migrate` on `db` only. After it ran:

| Record | Code | Sequence next_number |
|---|---|---|
| Grains | CAT-000001 | inventory_category 2 |
| Kilo Gram | UOM-000001 | unit_of_measure 2 |
| Chana | CH1234 | inventory_item 1 |
| vendor | unchanged | 4 |
| goods_receipt | unchanged | 2 |
| order | unchanged | 2647 |
| bill | unchanged | 2629 |

## Code generation

`MasterCodeAllocator` locks the matching `document_sequences` row, increments `next_number`, and returns `PREFIX-000001`. The transaction retries up to five times. A number is consumed when it is issued, including when a later insert fails. Issued codes are not reused.

| Master | Sequence | Prefix |
|---|---|---|
| Item category | inventory_category | CAT |
| Unit of measure | unit_of_measure | UOM |
| Inventory item | inventory_item | ITM |
| Vendor | vendor | VEN |

`VendorCodeAllocator` now delegates to that allocator. Vendor numbering is still the existing `vendor` sequence, so the next vendor on `db` is `VEN-000004`.

`DocumentNumberService` still accepts only order and bill numbers.

Codes are not in `$fillable`. Create and update form requests drop a submitted `code`. Services assign a code only when creating a row and ignore `code` on update. Category, unit, and item codes follow the same rule that vendor codes already used.

Create pages show “Auto-generated on save”. Edit and view pages show the stored code as read-only text. There is no `name="code"` input.

## Vendor fields

`name` remains the vendor column and the primary display name. Shop name and POC name are required on create and on update, so an older vendor cannot be saved until those fields are filled. The columns themselves are nullable, so the migration does not invent shop or contact names. There were no vendor rows on `db`.

Bank name, account number, and IFSC are optional. The account is stored as text, so leading zeros stay. A provided IFSC is trimmed, uppercased, and checked against `^[A-Z]{4}0[A-Z0-9]{6}$`.

Bank details stay off the vendor list, goods receipt autocomplete, inventory stock pages, and printed receipts. The vendor detail page, which already requires `inventory.view`, shows the account masked to the last four digits. The edit form, which requires `inventory.update`, shows the full account so it can be corrected. No new permission was added. Account numbers are not written to logs by this change.

Goods receipt autocomplete still returns `id`, `name`, `code`, and `label` as `Vendor Name — Vendor Code`.

## Tests and verification

PHPUnit, through DDEV, on SQLite memory:

`tests/Feature/Admin/Inventory`, `tests/Unit/Inventory`, permission tests, banquet `ReceiptUiTest`, `ViewBillTest`, `AdminBillPaymentTest`, and `ReportAuthorizationTest`.

90 tests passed, 640 assertions. Coverage includes generated codes, ignored forged codes, preserved `CH1234`, required shop and POC names, IFSC normalization, a leading-zero account, masked account on the vendor page, goods receipt autocomplete without shop or bank fields, and existing receipt post and void behavior.

MariaDB overlap used a disposable database `phase2c_accept`, then that database was dropped. Two connections locked `inventory_category`. One lock wait was observed. The sequence advanced by 2 and the two issued codes were `CAT-000001` and `CAT-000002`. The development database `db` was not used for that check. `order`, `bill`, `vendor`, and `goods_receipt` on `db` were unchanged.

The admin sign-in click in the browser was blocked by the browser tool approval flow, so the four forms were not clicked through in the browser. The create and edit pages were checked through the feature tests: create pages show “Auto-generated on save” and do not render a code input; the category edit page shows the issued code as read-only. A temporary admin created for the blocked browser pass was removed. No inventory master was created in the browser, so the live sequences above were not advanced by that attempt.

## Unresolved

- `Chana` remains `CH1234` until a separate decision rewrites it.
- Shop name and POC name are enforced by the form, not by a database `NOT NULL` constraint, so a row inserted outside the admin form can still omit them.
- A code is spent when allocation commits even if the master row is later deleted.
- The browser walkthrough of create, edit, and vendor masking was not completed in the live admin UI.

## Changed files

- `app/Models/DocumentSequence.php`
- `app/Models/InventoryCategory.php`
- `app/Models/InventoryItem.php`
- `app/Models/Vendor.php`
- `app/Services/Inventory/MasterCodeAllocator.php`
- `app/Services/Inventory/VendorCodeAllocator.php`
- `app/Services/Inventory/InventoryCategoryService.php`
- `app/Services/Inventory/InventoryItemService.php`
- `app/Services/Inventory/UnitOfMeasureService.php`
- `app/Services/Inventory/VendorService.php`
- `app/Http/Requests/Admin/Inventory/InventoryCategoryRequest.php`
- `app/Http/Requests/Admin/Inventory/InventoryItemRequest.php`
- `app/Http/Requests/Admin/Inventory/UnitOfMeasureRequest.php`
- `app/Http/Requests/Admin/Inventory/VendorRequest.php`
- `database/migrations/2026_11_03_180000_standardize_inventory_master_codes_and_vendor_details.php`
- `resources/views/admin/inventory/_code-field.blade.php`
- category, unit, item, and vendor Blade forms, show pages, and the unit and vendor indexes
- `tests/Feature/Admin/Inventory/InventoryMasterTest.php`
- `tests/Feature/Admin/Inventory/GoodsReceiptTest.php`
- `tests/acceptance/phase2b_mariadb.php` fixture creation, so that script still builds masters after codes stopped being mass-assignable
- `tests/acceptance/phase2c_codes.php`
