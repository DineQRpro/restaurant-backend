# Phase 1 legacy master-data mapping review

This review is based on:

- `docs/legacy-migration/varsity_database_schema.sql`
- `docs/legacy-migration/laravel_database_schema.sql`
- VarsityKitchen application code that writes and reads these tables
- Current Laravel models, migrations, and seeders

No source or destination data was modified. Neither schema dump was executed.

Phase 1 does not migrate orders, order items, bills, bill items, bill payments, receipts, combos, happy hours, grocery, vendors, or product images.

## Preserved destination records

The destination is not emptied. Existing Laravel rows stay in place.

These records are reused, not recreated:

- `roles` seeded as `admin`, `waiter`, `captain`, `counter`
- `permissions` and `role_permissions`
- `payment_methods` seeded as `cash`, `upi`, `card`, `split`, `non-chargeable`
- `dine_types`, `document_sequences`, and other application configuration

A legacy row that would collide with an existing unique value is recorded as an error and is not imported. Examples: `users.username`, `users.email`, `users.mobile`, `tables.number`, `tables.code`, `discounts` names are not unique in Laravel, so discount name collisions are allowed.

`captain` has no legacy role code. It is left unchanged.

## Source connection

A separate `legacy` connection will read through `DB::connection('legacy')` using:

- `LEGACY_DB_HOST`
- `LEGACY_DB_PORT`
- `LEGACY_DB_DATABASE`
- `LEGACY_DB_USERNAME`
- `LEGACY_DB_PASSWORD`

The source user must be read-only. Example for MariaDB, to be run by an administrator on the source server. Do not run this against the Laravel database.

```sql
CREATE USER 'legacy_reader'@'%' IDENTIFIED BY 'choose-a-password';
GRANT SELECT ON varsity_database.* TO 'legacy_reader'@'%';
FLUSH PRIVILEGES;
```

Replace `varsity_database` with the real source schema name. The migration code will not issue `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, or `ALTER` on that connection.

## Roles and users

### Legacy role codes

`tbl_users.role` comment says Admin 1, Kitchen 2, Counter 3, Staff 4.

`AuthController::verify()` uses those codes and also handles role `5`:

| Code | Legacy login destination | Laravel candidate |
| --- | --- | --- |
| 1 | Admin dashboard | existing `admin` |
| 2 | Kitchen dashboard | existing `waiter` |
| 3 | Counter dashboard | existing `waiter` |
| 4 | Waiter tables. The staff form labels this option "Waiter" | existing `waiter` |
| 5 | Store items. The staff form labels this option "Store" | no Laravel role |

Legacy permissions are not a table. They are hard-coded route checks. Imported users receive the permissions already attached to the matched Laravel role. Only legacy role 1 receives Admin permissions. Roles 2, 3, and 4 receive Waiter permissions. Store users are not given Admin, Waiter, Captain, or Counter permissions.

### `tbl_users` → `users`

| Legacy | Laravel | Rule |
| --- | --- | --- |
| `user_id` | `users.id` and `legacy_migration_id_maps.source_id` | The Laravel id matches the legacy id when that id is free. A taken id is not overwritten; the map records the new id and a warning. |
| `fullname` | `name` | Required after trim. Empty fails |
| `username` | `username` | Required after trim. Duplicate against destination or the same batch fails |
| `email` | `email` | A valid unique email is copied. A missing, invalid, or duplicate email becomes `legacy-user-{id}@invalid.example`. The original value stays in the ID-map metadata. The row is imported with a warning. |
| `phone` | `mobile` | A unique 10-digit number is copied. `91` followed by 10 digits, or a leading `0` before 10 digits, is normalized. A missing, invalid, or duplicate number becomes `NULL`. The original value stays in the ID-map metadata. The row is imported with a warning. |
| `password` | `password` | Not copied. See password strategy |
| `role` | `role_id` | Resolved through an approved role map to an existing `roles.id` |
| `user_status` | `is_active` | `1` active, `2` inactive. Any other value fails |
| `created_at`, `updated_at` | `created_at`, `updated_at` | Copied when they are valid timestamps. Invalid timestamps fail the row |
| `users_restreg_id`, `user_subscribe_status`, `user_subscribe_expiry`, `last_login`, `logo` | — | Not migrated. No destination columns |

### Password strategy

Specified for DDEV development only:

- Configurable shared password, default `test123`, through `LEGACY_MIGRATION_DEV_PASSWORD`.
- Stored only as a Laravel hash (`Hash` facade). Plaintext is not written to the database or logs.
- Add `users.must_change_password` (boolean, default false). Imported users are set to true.
- Web admin login, web counter login, and `POST /api/v1/login` reject a normal session until the password is changed.
- This mode runs only when `APP_ENV=local`. Any other environment refuses to start a user import that would apply the shared password.

## Menu categories and items

### Category relationship

The menu create form uses two different fields:

- `product_main_category` is the dietary select: `1` Vegetarian, `2` Non Vegetarian, `3` Eggetarian.
- `product_category` is the cuisine select populated from `tbl_category.category_id`.

`Menu` joins `tbl_category` on `tp.product_category = tc.category_id`. The counter menu filters cuisine with `category_title` and diet with `product_main_category`.

So:

- `tbl_category` → `menu_categories`
- `tbl_products.product_category` → `menu_items.category_id` through the ID map
- `tbl_products.product_main_category` → `menu_items.dietary_type`

`product_main_category` is not a foreign key to `tbl_category`.

### Menu types

The destination has no menu-type table today. Categories and items are one flat menu. The approved model is:

```text
menu_types
    └── menu_categories.menu_type_id
            └── menu_items.category_id
```

An item's menu type is derived through its category. `menu_items` does not get `menu_type_id`.

Stable records, created idempotently by slug:

| Slug | Name | sort_order | Starting contents |
| --- | --- | --- | --- |
| `food` | Food Menu | 1 | Every existing destination category, plus every imported legacy category |
| `hot-beverages` | Hot Beverages Menu | 2 | Empty. Categories and items are created later in the admin UI |

Legacy products are imported only into those Food Menu categories. Order items keep pointing at `menu_items.id`, so adding the category foreign key does not rewrite historical order lines.

### `tbl_category` → `menu_categories`

| Legacy | Laravel | Rule |
| --- | --- | --- |
| `category_id` | ID map | New id allocated by Laravel |
| `category_title` | `name` | Required after trim. Empty fails |
| `category_desc` | `description` | Trim. Empty becomes `NULL` |
| `category_image` | `image` | Trim. Empty becomes `NULL`. The file itself is not copied in Phase 1 |
| — | `menu_type_id` | Food Menu (`slug = food`). Required. Never Hot Beverages |
| — | `sort_order` | `0`. Legacy categories have no sort column |
| — | `is_active` | `true`. Legacy categories have no active flag |
| `cat_created_at`, `cat_updated_at` | `created_at`, `updated_at` | Copied when valid |

Duplicate category names are allowed. Laravel `name` is not unique.

### `tbl_products` → `menu_items`

| Legacy | Laravel | Rule |
| --- | --- | --- |
| `product_id` | ID map | New id allocated by Laravel |
| `product_title` | `name` | Required after trim |
| `product_desc` | `description` | Empty becomes `NULL` |
| `product_category` | `category_id` | Must exist in the category ID map. Missing or unmapped reference fails. No substitute category |
| `product_main_category` | `dietary_type` | `1` → `vegetarian`, `2` → `non_vegetarian`, `3` → `eggetarian`. `NULL` stays `NULL`. Any other value fails |
| `product_original_price` | `price` | Required numeric, `>= 0`, stored as `decimal(10,2)`. Null or non-numeric fails |
| `product_avail` | `is_available` | `1` available, `2` not available. Any other value fails |
| `chef_special` | `is_chef_special` | `1` true. `NULL` or `0` false. Any other value fails |
| `priority` | `sort_order` | Integer `>= 0`. Default in legacy is `4` |
| `product_spicy` | `spicy_level` | See decision D6 |
| `created_at`, `updated_at` | `created_at`, `updated_at` | Copied when valid |

`tbl_product_images` is not imported. `menu_items` has no image column.

## Floors and tables

There is no legacy floor table. The restaurant operates as one floor, Ground Floor, and every imported physical table must use `floor_id = 1`.

`tables.floor_id` stays nullable for the rest of the application. The importer does not write null.

Before any table row is inserted, query `floors`:

1. If a row with `id = 1` is named Ground Floor, reuse it. Match the name case-insensitively after trim. Its code stays as stored.
2. If `id = 1` is unused and no Ground Floor exists, insert Ground Floor with `id = 1`, `name = Ground Floor`, and unique `code = ground-floor`.
3. If `id = 1` belongs to a different floor, stop the floor step and the table import. Do not rename or overwrite that row.
4. If Ground Floor exists at an id other than 1, stop and ask for reconciliation. Do not insert a second Ground Floor.
5. Repeating the check does not create a second row.

`FloorSeeder` currently calls `Floor::updateOrCreate(['code' => 'MAIN'], ['name' => 'Main Floor', ...])`. `Floor` does not import that class, and `code` and `description` are not fillable. On a database where that seeder did succeed, Main Floor may already occupy id 1. Rule 3 then stops the import. That conflict is reported. It is not repaired automatically.

Physical master rows are the rows `Tables::getAll()` already uses:

```sql
SELECT * FROM tbl_tables WHERE tables_session_id IS NULL
```

Waiter seating, counter takeaway, and customer sessions insert extra `tbl_tables` rows with a session id. Those rows are not master data and are excluded. Occupancy fields are not copied onto the master row:

- `tables_reserved_chair`
- `tables_customer_name`
- `tables_chair_numbers`
- `tables_mobile_number`
- `tables_session_id`
- `order_segment`
- `tables_user_status`

`tbl_tables_status` is operational state and is not migrated. Imported tables start as `status = 0` (available) and `is_active = true`.

### `tbl_tables` master rows → `tables`

| Legacy | Laravel | Rule |
| --- | --- | --- |
| `tables_id` | ID map `source_id` | New `tables.id` |
| `tbl_tbid` | ID map metadata | Kept for Phase 2. Not a `tables` column. Empty is allowed and stored as null metadata |
| `tables_number` | `number` | Required after trim. Duplicate among master rows or existing destination rows fails |
| `tables_desc` | `description` | Empty becomes `NULL`. Truncate check at 255 characters: longer values fail |
| `tables_chair_count` | `capacity` | Required unsigned integer from 1 through 255. Blank, zero, negative, or non-numeric fails |
| `tables_qr` | — | Not copied into `code` |
| — | `code` | Unique ULID, same strategy as `TableService` |
| — | `floor_id` | Always `1` after the Ground Floor check succeeds. A null floor fails the row |
| — | `sort_order` | `0` |
| — | `status` | `0` available |
| — | `is_active` | `true` |
| `tab_created_at`, `tab_updated_at` | `created_at`, `updated_at` | Copied when valid |

## Discounts

`tbl_discounts` matches `discounts` closely. The legacy screen inserts name, type, value, levels, applies-to, and dates. It does not set `status` or `deleted`. `status` therefore defaults to `0` for rows created by the application. `delete()` removes the row instead of setting `deleted`.

| Legacy | Laravel | Rule |
| --- | --- | --- |
| `discount_id` | ID map | New id |
| `discount_name` | `name` | Required after trim, max 150 |
| `discount_type` | `type` | `custom` or `fixed` only |
| `discount_value` | `value` | Required numeric `>= 0` |
| `discount_min_level` | `min_level` | Null becomes `0`. Negative fails |
| `discount_max_level` | `max_level` | Null becomes `0`. Negative fails. `max_level < min_level` when both are greater than 0 fails |
| `applies_to` | `applies_to` | `product` or `order`. Null becomes `order` only if decision D9 is approved. Otherwise null fails |
| `start_date`, `end_date` | `start_date`, `end_date` | Null allowed. End before start fails |
| `status` | — | Recorded in the migration report only. Not used for `is_active` |
| `deleted` | — | `1` is skipped. `0` or null is imported |
| — | `is_active` | `false` for every imported discount |
| `created_at`, `updated_at` | `created_at`, `updated_at` | Copied when valid |

No bill rows are attached.

## Payment methods

There is no legacy payment-method table. `PaymentMethodSeeder` already creates the destination rows the billing code expects.

Phase 1 does not insert payment transactions. It records an approved code map for Phase 2:

| Legacy bill code | Meaning | Destination |
| --- | --- | --- |
| 1 | Cash | existing slug `cash` |
| 2 | UPI | existing slug `upi` |
| 3 | Split | not a tender. Existing slug `split` stays the allocation label already used by billing |
| 4 | Non-chargeable | existing slug `non-chargeable` |
| 5 | Card | existing slug `card` |
| 6 | Credit | existing inactive slug `credit` |
| 7 | Cancel | not a payment method |

`tbl_upipay` is a UPI account record, not the payment-method list. It is not imported.

If a seeded slug is missing, that code is an error. The migrator does not invent a new method.

## Approved decisions

D1. Role `2` Kitchen: map to existing `waiter`. Do not create a Kitchen role and do not skip the user because that role is absent.

D2. Role `3` Counter and role `4` Staff: map to existing `waiter`. Role `1` Admin maps to existing `admin`.

D3. Role `5` Store: skip the user and record the exclusion.

D4. Unknown or empty role: fail the row.

D5. `product_main_category` other than null, 1, 2, or 3: fail the row.

D6. `product_spicy`: `0` or null becomes `spicy_level` null. `1` and `2` are copied. Any other value fails.

D7. Ground Floor is required for imported physical tables. `floor_id` is `1` after the conflict checks above. The column stays nullable for other application paths.

D8. `tables.code` is a unique ULID. `tbl_tbid` stays in ID-map metadata. `tables_qr` is not copied.

D9. Discount `applies_to` null: fail the row.

D10. Discount `deleted = 1`: skip. Imported discounts are inactive (`is_active = false`). Raw `status` is reported and does not control `is_active`.

D11. Reuse seeded payment methods. Do not create another Split method. Do not import payment transactions. The transaction code list is the live counter list in the Phase 2 review, not the old table comment. The master-data map only records which existing payment-method row a legacy code reuses.

D12. Imported categories use `sort_order` 0, `is_active` true, and Food Menu.

D13. Existing destination rows stay. Colliding unique keys fail that legacy row. Seeded roles and payment methods are matched, not duplicated.

D14. Development password is the hashed value of configurable `test123`, only when `APP_ENV=local`. Imported users must change it on first login.

D15. Legacy categories and their products belong to Food Menu. Hot Beverages starts with no categories and no items.

## Proposed application changes

No migration engine and no data import are included in this step. These are the changes to review before that work starts.

### 1. Database migrations

`create_menu_types_table`:

- `id`
- `name` string, unique
- `slug` string, unique
- `sort_order` unsigned integer, default 0
- `is_active` boolean, default true
- timestamps

`add_menu_type_id_to_menu_categories_table`:

1. Add nullable `menu_type_id` foreignId to `menu_types`, `restrictOnDelete`.
2. Insert `food` / Food Menu and `hot-beverages` / Hot Beverages Menu with `updateOrCreate` on `slug`.
3. Set `menu_type_id` to the Food Menu id for every existing category.
4. Make `menu_type_id` required.

`menu_items` is unchanged. Existing `order_items.menu_item_id` values stay valid.

`tables.floor_id` nullability is not changed.

Ground Floor is not inserted by the schema migration. It is resolved at import time from the live `floors` table, because id 1 may already be Main Floor.

### 2. Eloquent relationships

`MenuType`:

- `categories(): HasMany MenuCategory`

`MenuCategory`:

- `menuType(): BelongsTo MenuType`
- `items(): HasMany MenuItem` unchanged
- `menu_type_id` fillable and cast to integer

`MenuItem`:

- `category(): BelongsTo MenuCategory` unchanged
- `menuType()` is `hasOneThrough` or a query through `category.menuType`. It is not a stored foreign key.

`Floor`:

- Add `code` and `description` to fillable so Ground Floor can be saved.
- `tables(): HasMany` already exists.

### 3. Menu management, validation, and filtering

Admin and API category create/update require `menu_type_id` for an active menu type.

Admin category form gains a Menu Type select. Index gains a menu-type filter and a Menu Type column. Default filter is Food Menu so the current cuisine list still opens on the imported menu. Choosing Hot Beverages shows an empty list until categories are added.

Admin item index filters categories by the selected menu type. The item form groups cuisine options under their menu type. Saving an item still sends only `category_id`.

API category and item index accept optional `menu_type` as the slug `food` or `hot-beverages`. Omitted means all types, so current API clients keep their current results. Item filtering uses `whereHas('category.menuType')`.

Category and item resources include the menu type id, name, and slug. Item resources read it from the loaded category.

Counter takeaway menu lists Food Menu items only. Hot Beverages has no order flow in this phase.

`docs/MenuManagement.md` is updated with the new table and filter. No separate menu-type admin CRUD in this phase. The two rows are seeded and not deleted while categories reference them.

### 4. Ground Floor bootstrap

A single idempotent method, used by the later table migrator:

```text
inspect floors
    id 1 is Ground Floor → reuse
    id 1 empty and no Ground Floor → insert id 1, code ground-floor
    id 1 is another floor → stop with a conflict report
    Ground Floor exists at another id → stop and request reconciliation
```

It does not rename Main Floor. It does not change `tables.floor_id` nullability. Table import does not start when this check stops.

### 5. Import dependency order

1. Menu types (`food`, then `hot-beverages`)
2. Roles, matching existing slugs
3. Users
4. Menu categories, each assigned to Food Menu
5. Menu items, only through those category ID maps
6. Ground Floor check
7. Physical tables with `floor_id = 1`
8. Discounts, inactive unless deleted
9. Payment-method code map, reusing existing rows

Users do not depend on menu or floor data. Discounts and payment methods stay independent of the menu and table chains. Tables wait for a successful Ground Floor check.

### 6. Automated test plan

Menu types, using factories and the schema migration, not the legacy database:

- Seeding twice leaves one `food` row and one `hot-beverages` row.
- The category migration assigns existing categories to Food Menu and then rejects a null `menu_type_id`.
- Creating a Hot Beverages category does not move existing Food items.
- Item index `menu_type=food` excludes Hot Beverages items, and `menu_type=hot-beverages` can be empty.
- Admin category create requires a menu type. Admin item save still uses `category_id` only.
- An order item still resolves after its category receives `menu_type_id`.

Legacy import fixtures, after the engine exists:

- A legacy category lands on Food Menu, and its product uses that new category id, not the old `category_id`.
- A product whose `product_category` has no map fails.
- Dietary and spice values outside the approved set fail.
- A Hot Beverages category created by hand is not a target for legacy products.

Ground Floor fixtures:

- Id 1 already Ground Floor: second run reuses it and imports a table with `floor_id = 1`.
- Empty floors: create id 1 and code `ground-floor` once.
- Id 1 is Main Floor: stop, import no table, leave Main Floor unchanged.
- Ground Floor at id 2: stop, do not insert another Ground Floor.
- A legacy row with `tables_session_id` set is excluded.
- `tbl_tbid` is stored on the ID map, and `code` is a new ULID.

Also covered with the later engine: Kitchen, Counter, and Staff map to waiter, Admin maps to admin, Store users stay skipped, hashed `test123` with `must_change_password`, duplicate username and mobile, inactive imported discounts, skipped deleted discounts, reused payment methods, dry-run leaving business tables unchanged, resume, and no writes on the legacy connection.

## Implementation order after this review

1. Menu type schema, relationships, validation, admin UI, API filter, and menu-type tests.
2. Ground Floor resolver and its conflict tests. No table rows are imported.
3. Migration tracking tables, `legacy` connection, and commands.
4. Master-data migrators in the dependency order above.
5. Password-change enforcement and the remaining import tests.

No data import runs during steps 1 and 2.
