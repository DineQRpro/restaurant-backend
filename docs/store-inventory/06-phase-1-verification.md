# Phase 1 verification

Date: 2 November 2026

Environment: DDEV project `restaurant-backend` at `http://restaurant-backend.ddev.site`. PHP 8.4, MariaDB 11.8.6, database `db` on the DDEV `db` host, `APP_ENV=local`. This is the development database. No production migration was run. `migrate:fresh`, `db:wipe`, and `migrate:refresh` were not used.

## Passed

### Database

- Docker Desktop was started, then `ddev start`. The web and database containers reported OK.
- `php artisan db:show` connected to MariaDB 11.8.6, database `db`.
- Pending migrations were reviewed. The only pending migration was `2026_11_02_120000_create_inventory_master_tables`.
- That migration creates `inventory_categories`, `units_of_measure`, `vendors`, and `inventory_items`. It inserts one new `document_sequences` row named `vendor`. It does not alter sales, banquet, menu, or legacy grocery tables.
- `php artisan migrate` applied that migration only. Before and after, `document_sequences` stayed `bill=2629` and `order=2647`. The new row is `vendor`, now `3` after two verification vendors were created and then deleted. Issued codes are not reused.
- There is no `tbl_vendor` table and no table whose name contains `grocery` in this database.
- `PermissionSeeder` and `RolePermissionSeeder` were run with `php artisan db:seed --class=...`. They use `firstOrCreate` and `syncWithoutDetaching`. After seeding, admin has `inventory.view`. Waiter, captain, and counter do not.

### Automated tests

After the model fix below, these commands passed:

| Command | Result |
| --- | --- |
| `php artisan test --filter=InventoryMasterTest` | 4 passed, 59 assertions |
| `php artisan test --filter=PermissionSeederTest` | 2 passed, 44 assertions |
| `php artisan test --filter=RolePermissionTest` | 9 passed, 46 assertions |

PHPUnit uses sqlite `:memory:` and does not write to the MariaDB development database.

The inventory tests cover guest redirect, waiter and counter denial, an admin without `inventory.view`, sidebar links, category and unit and item CRUD, duplicate names and codes, inactive category rejection, nonnegative reorder, maximum level greater than or equal to reorder, search, blocked deletion of a referenced category, generated vendor codes `VEN-000001` and `VEN-000002`, and a posted vendor code being ignored on create and update.

### Admin UI

Signed in with a temporary admin user, then removed that user. Existing staff passwords were not changed.

- Admin dashboard loads. The sidebar shows Store & Inventory with Item Categories, Units of Measure, Inventory Items, and Vendors.
- Created, edited, viewed, searched, deactivated, reactivated, and deleted an item category (`Phase1 Verify Dry Goods` / `P1DRY`).
- Created, viewed, and later deleted a unit (`Phase1 Kilogram` / `p1kg`, dimension Weight). The form states that units are not converted.
- Created and viewed an inventory item (`P1-RICE`, reorder `5.000`, maximum `5.000`). Search and the item list showed the category and unit. No stock balance is shown.
- Deleting the category and the unit while the item still referenced them stayed on the list and showed: "cannot be deleted because inventory items use it. Deactivate it instead."
- After the item was deleted, the category, unit, and both vendors were deleted from the UI. All four master tables are empty again.
- Created two vendors. The show pages reported `VEN-000001` and `VEN-000002`. The create form has no code field. The edit form shows the code as text and says it cannot be changed. Updating the first vendor name to `Phase1 Harbour Ltd` left the code as `VEN-000001`. Searching `VEN-000001` returned only that vendor.
- A guest request to `/admin/inventory/categories` redirected to `/auth/login`.
- A temporary waiter account cannot sign in to the web app. The login page says the account uses the staff application and does not have a web dashboard.
- A temporary counter account reached the counter dashboard, which does not show Store & Inventory. Opening `/admin/inventory/categories` returned 403: "You do not have access to the admin portal."
- Existing sales and banquet pages still render: admin dashboard with its existing order and table counts, menu items (377 results), order-wise report with View Bill links, and banquet receipts with the existing "independent of dine-in bills" description.

Temporary verification users and master rows were removed after the checks.

## Failed, then fixed

The first `InventoryMasterTest` run failed before any assertion because the development `APP_KEY` was four characters and not a valid cipher key. `php artisan key:generate` wrote a new local key in `.env`. `.env` is gitignored. Existing encrypted cookies from the old key were already unusable.

The next inventory test run failed in `test_admin_can_manage_categories_units_and_items`. Eloquent expected `unit_of_measures`, while the migration creates `units_of_measure`. `UnitOfMeasure` now sets `protected $table = 'units_of_measure'`. The same test file then passed, as shown above. The development database already had the correctly named table, so no further migration was required.

## Blocked

None of the requested checks remain blocked. Docker, the development database, migrations, seeders, PHPUnit, and the Admin UI were all available.

A hand-crafted vendor-code field was not posted from the browser. The edit screen has no code input, and `InventoryMasterTest` already posts a code on create and update and asserts that the stored code does not change.

## Not started

Stock receipts, stock balances, and the inventory ledger were not implemented.
