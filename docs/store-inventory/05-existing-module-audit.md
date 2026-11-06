# Phase 0 audit — existing Store module

Audit only. No application code, migrations, routes, views, or database objects were changed. The approved functional baseline is `docs/store-inventory/01-functional-baseline.md` (v1.2). The data contract in `docs/store-inventory/02-data-contract.md` is provisional. No separate v1.2 file exists beyond that baseline.

Live destination counts were not queried. The Docker daemon was not running, so DDEV could not connect. Counts below for legacy grocery tables are the published Phase 2 snapshot in `docs/legacy-migration/phase-2-mapping-review.md`, not a fresh read of `varsity_source`.

## 1. Framework, PHP, database, and conventions

| Item | Actual |
| --- | --- |
| PHP constraint | `composer.json` requires `php: ^8.3` |
| DDEV PHP | `.ddev/config.yaml` sets `php_version: "8.4"` |
| Laravel | `composer.lock` pins `laravel/framework` `v13.30.1` |
| Auth | Session web portals plus Sanctum for the API |
| Database | DDEV MariaDB `11.8`. PHPUnit uses sqlite `:memory:` (`phpunit.xml`) |
| Admin UI | Blade, Tailwind, Vite. Layout `resources/views/layouts/admin.blade.php`. Navigation `App\Support\AdminNavigation` |
| Permissions | `permission:{slug}` middleware. Seeded in `database/seeders/PermissionSeeder.php` and `RolePermissionSeeder.php` |
| Admin CRUD pattern | Controller + Form Request + service + `layouts.admin`. Example: `App\Http\Controllers\Admin\DiscountController` |
| Reports pattern | `App\Http\Controllers\Reports\BaseReportController`, catalog `App\Support\ReportCatalog`, shared Blade under `resources/views/reports/` |
| Number allocation | `document_sequences` plus `App\Services\Document\DocumentNumberService`, locked with `lockForUpdate()`. Names today: `order` and `bill` only |
| Store UI | No React Store module. Waiter/Captain stay on the React app |

There is no Laravel Store namespace, model, migration, route, permission, Blade view, or test.

## 2. Existing store-related tables

### Laravel destination

Migrations under `database/migrations/` create sales, menu, user, receipt, and report-support tables. None of these names exist:

`inventory_categories`, `units_of_measure`, `inventory_items`, `vendors`, `stock_receipts`, `stock_receipt_items`, `stock_movements`, `inventory_lots`

Searches of `app/`, `database/`, and `routes/` found no stock, grocery, reorder, or vendor-master models. Destination row counts were not read because Docker was unavailable.

The name `receipts` is already used. It is the banquet receipt book, not goods receipt. See section 6.

### Legacy source, not migrated

Phase 2 mapping marks these as legacy-only. They have no Laravel destination. Structures are from `docs/legacy-migration/varsity_database_schema.sql`. Snapshot counts are from the Phase 2 review.

| Legacy table | Snapshot rows | Keys in the schema dump | Columns that matter |
| --- | --- | --- | --- |
| `tbl_grocery_category` | 6 | Primary key `category_id` only | `category_name`, `status`, `deleted`, `created_by` |
| `tbl_grocery_quantity_types` | 4 | Primary key `quantity_type_id` only | `quantity_unit` (varchar 20), `description`, `status`, `deleted` |
| `tbl_grocery_items` | 9 | Primary key `grocery_id` only | `grocery_name`, `quantity_type_id`, `grocery_quantity` int, `reorder_level`, `max_limit`, `category_id`, `status`, `deleted` |
| `tbl_grocery_item_batch` | 8 | Primary key `id` only | `grocery_id`, `grocery_quantity` int, `price_per_unit`, `vendor_code` (stores vendor id), `batch_no`, `bill_no`, `bill_date`, `bill_payment_status` int, `expiry_datetime`, `status`, `deleted` |
| `tbl_grocery_item_order` | 0 | No primary key in the dump | Same bill/batch shape as a batch row. Purchase-order staging |
| `tbl_grocery_debit` | 5 | Primary key `id` only | `grocery_id`, `grocery_quantity` int, `status`, `deleted`, `created_by` |
| `tbl_vendor` | 0 | Primary key `vendor_id` only. No unique key on `vendor_code` | `vendor_name`, `vendor_code`, `vendor_desc`, `vendor_phone`, `vendor_address`, bank fields, `gst_no` |

`tbl_dineprovendor` is a gateway settlement log (1 snapshot row). It is not a supplier master. Do not import it.

The schema dump contains `CREATE TABLE` only. It does not include the category names, unit labels, or item rows. Those labels were not readable in this audit.

## 3. Existing models and relationships

Laravel has no inventory Eloquent models and no relationships from `MenuItem`, `OrderItem`, `Bill`, or `BillItem` to a store item.

Legacy PHP models, present only under `/Users/magiyv/Projects/VarsityKitchen`:

| Legacy class | Table | Behavior |
| --- | --- | --- |
| `app/models/Storeitems.php` | items + batches | Creates a batch row. Updates the item category. Edit rewrites the batch quantity and price in place. Delete sets `tbl_grocery_item_batch.deleted = 1` |
| `app/models/Storeitemsthreshold.php` | items + quantity types | Lists non-deleted items. Does not calculate stock from movements |
| `app/models/Storequantitytype.php` | quantity types | CRUD on a unit label and description. Soft-deletes with `deleted = 1` |
| `app/models/Vendor.php` | `tbl_vendor` | Inserts a vendor. Update does not change `vendor_code`. Delete is a hard `DELETE` |
| `app/models/Product.php` | batch + debit | Product search decreases a batch quantity and inserts `tbl_grocery_debit` |
| `app/models/Report.php` | several | Can add to `tbl_grocery_items.grocery_quantity` and to a batch quantity. Purchase-order update deletes the staging row |

There is no conversion-factor column and no code that converts one unit into another. Quantity is an integer in the item's single `quantity_type_id`.

## 4. Existing routes, controllers, requests, views, and JS

Laravel:

- No `/admin/store` routes in `routes/web.php`.
- No Store Form Requests. Classes named `Store*Request` are ordinary create-form requests for menu, users, tables, discounts, orders, customers, and bill payments.
- No Store Blade views and no Store JavaScript. Admin pages use `layouts.admin` and inline page scripts where a screen needs them.
- `App\Support\AdminNavigation` has no Store & Inventory group. Completed links today include Dashboard, Menu, Users, Tables, Discounts, Reports, and Receipts. Orders, KOT, Billing, Payments, and Settings are present but `available: false`.

Legacy admin store role (`$role == 5` in `VarsityKitchen/app/views/layout/sidebar.php`) links to:

- `storeitems/index`
- `product/index`
- `storeitemsthresholdlevel/index`
- `store/daily_report`, `store/out_stock_report`, `store/store_threshold_level`
- `store/vendor_payment_report`, `store/purchase_order_report`
- `report/grocerydebit`

Controllers: `StoreitemsController`, `StoreitemsThresholdController`, plus store actions on `ReportController` and `CounterReportController`.

## 5. Vendor codes and bill status values, including Credit

### Vendor codes

`Vendor::create()` sets `vendor_code` to `V` plus four random hex characters (`random_bytes`, not a sequence). The column is `varchar(255)` and is not unique in the schema dump. Update never changes the code. The Phase 2 snapshot had zero vendor rows, so there is no live code series to preserve in Laravel. `AUTO_INCREMENT=6` on the dump only shows that ids had been allocated in that schema export.

`vendor_code` on a grocery batch is the vendor id, despite the column name. `Storeitems::getvendorcode()` selects `tbl_vendor` by `vendor_id`.

### Sales bill status — do not reuse for stock receipts

`App\Enums\BillStatus` is only `open`, `settled`, and `cancelled`.

Credit on a customer bill is not a `BillStatus` case. It is:

- Seeded payment method slug `credit`, name `Credit`, `is_active` false, described as historical and not offered for new settlements (`PaymentMethodSeeder`).
- Historical flags: `bills.historical_pay_method = 6` or `bills.historical_pay_status = 2` (`App\Services\Reports\BillPaymentTotals`). Those bills stay `status = open`.

Report filters expose a `credit` bill-status option. That filter classifies sales bills. It is not a stock-receipt payment status.

### Legacy store payment status

`resources` in `VarsityKitchen/app/views/storeitems/create.php` and `edit.php` offer only:

| Stored int | Label |
| --- | --- |
| 1 | Paid |
| 2 | Credit |

The index echoes those two labels. Unpaid and Partially Paid are not options on that screen. There is no enum cast. The integer sits on each batch row, so payment status is not a separate settlement document.

## 6. Stock quantity and posting

Legacy on-hand quantity is a mutable integer, not a ledger.

- `tbl_grocery_items.grocery_quantity` is overwritten or incremented (`Report::groceryitemupdate()` adds the received quantity).
- `tbl_grocery_item_batch.grocery_quantity` is inserted on receipt and can be updated in place, including `grocery_quantity = grocery_quantity + ?`.
- Consumption in `Product::update()` subtracts from the batch and inserts `tbl_grocery_debit`. The debit row stores a positive quantity, not a signed movement, and it does not reference the batch id. The bind uses the batch id as `grocery_id`.
- `Storeitems::create()` does not add the received quantity onto `tbl_grocery_items.grocery_quantity`. It only updates category and inserts a batch. Stock behavior depends on which legacy screen was used.
- Batches can be soft-deleted. Posted history can also be edited.
- No idempotency key, no row lock, no opening-balance row, and no reversal document.
- `tbl_grocery_item_order` is purchase-order staging. The Phase 2 snapshot had zero rows. v1.2 excludes purchase orders.

Laravel has none of this. Menu `price` is the selling price. Order and bill item prices are snapshots of that sale. Nothing posts stock when an order or bill is saved.

### Receipts that already exist

`database/migrations/2026_09_28_100000_create_receipts_tables.php` creates banquet receipts:

- `receipts`: date, customer name, mobile, program, instructions, pax, advance, balance, GST flag.
- `receipt_items`: free-text name, quantity, rate, amount. `cascadeOnDelete`.

Permissions are `receipts.view`, `receipts.create`, `receipts.update`, `receipts.print`. Routes are `admin.receipts.*`. Phase 2 imported two historical banquet receipts. These records must stay independent of stock receipts. Do not add vendor or stock columns to this table.

## 7. Reports sidebar, layout, and permissions

Admin and Counter both gate the whole report group with `reports.view` (`routes/web.php`). Children come from `ReportCatalog`: item summary, bill summary, bill status, transaction, payment mode, waiter, order-wise, item-wise, and customer. Counter uses a shorter label set and does not include the thermal summaries in that catalog.

There is no stock, vendor-payment, threshold, or grocery-debit report in Laravel.

Admin receives every permission slug that exists when `RolePermissionSeeder` runs (`Permission::pluck('slug')`). A new inventory permission is not granted until that seeder mapping includes it. Counter, waiter, and captain must not receive it unless a later phase says so. The v1.2 sidebar is Admin only.

## 8. Dependencies on Sales, orders, menu, and other modules

| Area | Coupling to store |
| --- | --- |
| Menu categories and items | None. Selling price stays on `menu_items.price` |
| Orders and order items | None. No recipe or BOM |
| Bills, bill items, payments | None. Customer Credit stays on the sales payment method and historical flags |
| Banquet receipts | Name collision only. Separate tables and permissions |
| Document sequences | Reuse the locking pattern. Do not consume the `order` or `bill` sequences for vendor codes |
| Reports | Read sales bills. They must not start reading stock tables in Phase 1 |
| Legacy product search | Deducts grocery batches. That behavior is not ported and v1.2 forbids Sales deduction in this phase |

Completed Sales modules should not be edited to add inventory foreign keys.

## 9. Historical data and compatibility risks

- Grocery tables were intentionally left behind. Building new tables does not rewrite orders, bills, menu items, or banquet receipts.
- Importing the snapshot into the new ledger is not a column copy. Items have a mutable quantity, batches have their own quantity, and debits are not signed and do not point at a batch. A naive sum would double-count.
- Vendor snapshot count is 0. Generating `VEN-000001` style codes does not collide with imported vendor codes. The legacy generator is not concurrency-safe and is not unique at the database.
- Batch `bill_no` is not unique. Several lines can share a bill number, and there is no receipt header. Do not assume one row is one invoice.
- `tbl_grocery_item_order` has no primary key in the dump. Do not migrate it in Phase 1.
- Quantity is an integer. A later conversion to decimal must not be applied by altering legacy integers in place.
- Soft-deleted legacy rows (`deleted = 1`) must not be dropped if a later import is approved. The snapshot counts above are the published totals, not the non-deleted subset.
- Customer-bill Credit must stay queryable as it is today. A stock-receipt status named Credit must be a different column and enum.

## 10. Concept to existing entity

| Approved concept | Decision | Why |
| --- | --- | --- |
| Item category | New table | `menu_categories` is the food menu. `tbl_grocery_category` was not migrated |
| Unit of measure | New table | Legacy unit is a label only. No dimension or conversion factor |
| Inventory item | New table | Do not extend `menu_items` or `tbl_grocery_items` in place |
| Vendor | New table | `tbl_vendor` was not migrated and has no unique code constraint |
| Stock receipt header and lines | New tables | Do not extend banquet `receipts` / `receipt_items` |
| Stock movement ledger | New table | Legacy quantity columns are editable balances, not a posted ledger |
| Lots | New only if batch and expiry must be balanced per lot | Legacy batch is the receipt line itself. v1.2 can store batch and expiry on the receipt line first |
| Current stock | Derived | Do not add an editable `current_stock` column |
| Payment status Paid / Unpaid / Partially Paid / Credit | New column on the stock receipt | Do not reuse `BillStatus` or the inactive sales `credit` payment method |
| Vendor code | New sequence using the existing lock pattern | Legacy `V` + random hex is not the approved immutable sequence |
| Purchase orders, transfers, recipes, sales deduction | Out of scope | Legacy PO and product-search debit stay unported |

Suggested new names in `02-data-contract.md` do not collide with current Laravel tables. They are still proposals until Phase 1 is approved. `receipts` must not be reused.

## 11. Proposed additive migrations (not executed)

Phase 1, if approved, should only add tables, indexes, permissions, and a vendor-code sequence. It should not alter `menu_items`, `orders`, `order_items`, `bills`, `bill_items`, `bill_payments`, `receipts`, or `receipt_items`.

Proposed shape, matching the provisional contract:

- `inventory_categories`, `units_of_measure`, `inventory_items`, `vendors`
- Unique vendor code, unique item code, and category name uniqueness within the single store
- `reorder_level >= 0` and `reorder_level < maximum_level` enforced in validation. Database checks only if the project already uses them; current migrations mostly rely on application validation
- `document_sequences` row name such as `vendor`, allocated inside the same transaction as the vendor insert, code immutable after insert
- No stock-receipt or movement tables until the receiving phase. Masters do not need a balance column
- No backfill in the first migration. Historical grocery rows stay in the legacy database until a separate approved import

Indexes to decide before that later receiving migration, not now: unique (`vendor_id`, vendor bill number) if the business wants one invoice per vendor, and a non-unique bill number if legacy duplicates must be allowed.

## 12. Unknowns, risks, decisions, and tests

### Decisions required before Phase 1

1. Purchase units. Legacy never converts. Recommendation: Phase 1 stores one base unit per item and posts quantities in that unit. Do not build carton-to-piece conversion until a later approval.
2. Historical grocery import. Recommendation: start the new masters empty. Import of 6 categories, 4 units, 9 items, 8 batches, and 5 debits needs its own mapping because quantities are not a ledger. Vendors in that snapshot: 0.
3. Vendor code format. Recommendation: server sequence, unique, immutable. Do not copy the legacy random `V` + 4 hex generator.
4. Invoice uniqueness. Legacy does not enforce it. Decide `vendor + bill number` versus allowing duplicates before the receiving phase. Not required to create masters.
5. Admin-only navigation. Recommendation: new permissions, granted to admin through `RolePermissionSeeder`, hidden from waiter, captain, and counter. Show a sidebar link only after that screen exists.
6. Deactivation. Legacy uses a `deleted` flag and, for vendors, a hard delete. Recommendation: `is_active` and no hard delete once a row is referenced.

### Risks if Phase 1 ignores this audit

- Putting stock fields on banquet receipts mixes two documents and the cascade delete would remove posted lines.
- Mapping stock Credit onto `BillStatus` or the sales `credit` payment method would change report classification.
- Copying `grocery_quantity` into a balance column would freeze a number the legacy screens do not calculate consistently.
- Porting `Product::update()` would deduct sales stock, which v1.2 excludes.

### Test strategy for later phases

No tests were run. Docker was unavailable, and this phase does not change behavior.

When implementation starts, use the checklist in `docs/store-inventory/03-test-checklist.md` and add:

- Feature tests on admin routes for each master, including permission denial for waiter, captain, and counter
- Concurrent vendor-code test around `lockForUpdate()`
- Regression: existing report, billing, and banquet receipt tests stay green
- Migration rollback reviewed on sqlite and noted for MariaDB
- Receiving tests only after masters are approved: Credit distinct from Unpaid, idempotency, rollback, and non-blocking maximum warning

## 13. GO / NO-GO

**Conditional GO for Phase 1 masters only.**

GO if Phase 1 adds new inventory master tables, a vendor sequence, permissions, and Admin screens, and does not import legacy grocery rows yet.

**NO-GO** for any of the following in Phase 1:

- Editing Sales, orders, menu, bills, payments, or banquet receipts
- Treating legacy `grocery_quantity` as the new ledger
- Reusing `BillStatus` or the sales Credit payment method for stock-receipt status
- Enabling unit conversion, purchase orders, transfers, or sales deduction
- Showing unfinished Store links in the sidebar

Stop here until those decisions are approved.
