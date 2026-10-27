# Restaurant ERP — Table Management

This document covers the table **master** used by the Admin Blade UI. Follow `docs/Authorization.md` for permission checking.

The Admin UI does **not** call `/api/v1`. `TableController` uses `TableService` — the same service a future Table API must reuse.

Waiter vacant/occupied/reserved flows are **not** part of this module.

---

## 1. Legacy findings

Core PHP stores tables in `tbl_tables` and operational events in `tbl_tables_status`.

### `tbl_tables` is two kinds of row

Admin master list:

```sql
SELECT * FROM tbl_tables WHERE tables_session_id IS NULL ORDER BY tables_id ASC
```

When a guest is seated, Core PHP **inserts another row** on the same table with session fields (`tables_session_id`, `tables_customer_name`, `tables_mobile_number`, `tables_chair_numbers`, `tables_reserved_chair`, `tables_user_status`). Orders join those session rows (`order_tables_id` → `tables_id`).

That mixing is not copied. The new `tables` table is master data only.

### Master fields used by the old Admin UI

| Legacy column | Role |
| ------------- | ---- |
| `tables_id` | Primary key |
| `tables_number` | Display label. Form type is text. Server only required it. Not unique in the old database. Values such as `12` and `T1` are valid. |
| `tables_desc` | Optional description |
| `tables_chair_count` | Seating capacity. The old form used a 1-digit numeric input and did not validate on the server. Empty and `0` were possible. |
| `tables_qr` | Generated PNG filename (`{rand}qr_code.png`) |
| `tbl_tbid` | Random public identifier used in QR URLs (`tables/#!/?table_id={tbl_tbid}`) |
| `tab_created_at` / `tab_updated_at` | Timestamps |

Create generated the QR file and `tbl_tbid`. Edit views existed, but the controller `edit` / `update` methods were commented out. Delete was a hard `DELETE FROM tbl_tables WHERE tables_id = ?`.

### Fields not migrated as master columns

| Legacy | Why omitted |
| ------ | ----------- |
| `tables_session_id` | Operational session, not configuration |
| `tables_customer_name`, `tables_mobile_number` | Guest details for a sitting |
| `tables_chair_numbers` | Chairs taken during a sitting |
| `tables_reserved_chair` | Remaining/reserved chairs during a sitting |
| `tables_user_status` | Runtime status on the session row |
| `tables_qr` image file | No QR package in this app yet. The public identifier is stored as `code`. PNG/PDF generation can return later. |
| Area / section | Not present in Core PHP |
| Reservation on the master row | Reservation wrote `tbl_tables_status.table_status = 3` |

### `tbl_tables_status`

Separate operational log: `table_id`, optional `waiter_id`, `table_status`, `deleted`, `created_at`. Known values include reserved (`3`) and occupied (waiter comments). Admin Table Management does **not** create this table. Order / Waiter modules should own it.

---

## 2. New master table

Table: `tables`

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | bigint PK | |
| `number` | varchar, unique | Required. Alphanumeric display label (`12`, `T1`, `VIP-1`). Unique so waiters and orders can resolve one table. Legacy did not enforce uniqueness. |
| `description` | varchar, nullable | Maps from `tables_desc`. |
| `capacity` | unsigned tinyint | Maps from `tables_chair_count`. Required. `1`–`99`. `0` and negatives are rejected. |
| `code` | varchar, unique | Public identifier replacing `tbl_tbid`. Generated on create. Not editable. |
| `sort_order` | unsigned int | Default `0`. Project convention (legacy used `tables_id` order). |
| `is_active` | boolean | Default `true`. New. Inactive tables stay in the database for history but must not be selectable for new operational activity. |
| `created_at` / `updated_at` | timestamps | |

There is no `area_id` / `section_id`.

Relationship for future modules:

```text
Table  (master)
  └── future order sessions / table_statuses  (operational)
```

`Table::scopeActive()` exists so later modules can query `Table::query()->active()`.

---

## 3. Service

`App\Services\Table\TableService`

| Method | Behavior |
| ------ | -------- |
| `paginate` | Search `number`, `description`, `code`. Filter `is_active`. Order `sort_order`, then `number`. Default 20 per page. |
| `create` | Defaults `is_active` and `sort_order`. Generates `code`. |
| `update` | Updates master fields. Ignores any submitted `code`. |
| `updateStatus` | Sets `is_active`. |
| `delete` | Always throws `BusinessConflictException`. |

Hard delete is refused even though no `orders` table exists yet. Future Order Management must keep this rule once orders reference `tables.id`.

---

## 4. Admin Blade routes

| Method | Path | Permission |
| ------ | ---- | ---------- |
| `GET` | `/admin/tables` | `tables.view` |
| `GET` | `/admin/tables/create` | `tables.create` |
| `POST` | `/admin/tables` | `tables.create` |
| `GET` | `/admin/tables/{table}/edit` | `tables.update` |
| `PUT` | `/admin/tables/{table}` | `tables.update` |
| `PATCH` | `/admin/tables/{table}/status` | `tables.update` |
| `DELETE` | `/admin/tables/{table}` | `tables.delete` |

`tables.delete` is new. It does not grant a physical delete; the page flashes the service conflict message.

## 4. Staff Table API

Waiter and Captain use the same `TableService` through:

| Method | Path | Permission |
| ------ | ---- | ---------- |
| `GET` | `/api/v1/tables` | `tables.view` |
| `GET` | `/api/v1/tables/{table}` | `tables.view` |

List query parameters: `search`, `is_active`, `page`, `per_page` (default 20, max 100).

Returned master fields: `id`, `number`, `description`, `capacity`, `code`, `sort_order`, `is_active`, timestamps.

Floor responses also include `status` (derived from occupancy, not a stale stored value), `server_now`, `occupancy` (`occupied_seats`, `cleaning_seats`, `available_seats`), and `partitions` for shared-table seating. Occupancy is derived; it is never stored on `tables`. Cleaning partitions include `cleaning_started_at` and `cleaning_expires_at`.

---

## 5. Validation

### Number

Required. Trimmed. Max 50. Unique.

### Description

Optional. Max 255.

### Capacity

Required integer. Min `1`. Max `99`.

### Sort order

Optional integer ≥ 0. Defaults to `0`.

---

## 6. Table partitions / shared seating

A physical table remains one `tables` row. Each independent party is an `orders` row.

| Field | Meaning |
| ----- | ------- |
| `orders.pax` | Actual guest count |
| `orders.allocated_seats` | Capacity reserved for that party |
| `orders.partition_label` | Backend-assigned operational label `A`, `B`, `C`, … |
| `orders.seating_cleaned_at` | Allocation released / cleaning completed. Set by Mark Clean (`now()`) or automatic expiry (`cleaning_expires_at`) |
| `orders.cleaning_started_at` | Set when checkout succeeds. Authoritative start of the cleaning timer |

Display labels combine the physical number with the partition (`T1-A`). Historical orders keep their stored label. After an allocation is released (Mark Clean or timer expiry), that label may be reused.

`pax >= 1`, `allocated_seats >= pax`, and `allocated_seats` cannot exceed currently available capacity. Active cleaning seats still consume capacity. Take Away orders leave seating fields `NULL`.

Legacy active table orders with `allocated_seats = NULL` fall back to `pax` (minimum 1) so an occupied table cannot appear empty.

Occupancy:

* occupied = sum of allocated seats on active dining orders
* cleaning = sum of allocated seats on checkout orders whose cleaning period has **not** expired and `seating_cleaned_at` is still `NULL`
* available = capacity − occupied − cleaning

A checkout allocation consumes cleaning capacity only while `now < cleaning_started_at + config('restaurant.cleaning_duration_seconds')` (default **5 minutes**). After that instant, GET `/tables`, occupancy, and new seating all treat it as released even if `seating_cleaned_at` is still `NULL` and no scheduler has run.

Physical `tables.status` (and Floor `status`) follows occupancy: Occupied if any occupied seats, else Cleaning if any **active** cleaning seats, else Available. A table with only **active** cleaning allocations cannot seat a new party. Mixed occupied + active cleaning may still use genuinely free seats. Expired cleaning does not block normal seating or exclusive dine types.

Cleaning starts when checkout **succeeds** (`orders.cleaning_started_at = now()`). Each partition has its own expiry (`cleaning_expires_at`). Floor countdown uses those Laravel timestamps, not LocalStorage or the browser clock as authority. Successful checkout still removes `order_{id}` from LocalStorage; that does not affect the timer.

`PATCH /api/v1/orders/{order}/seating/clean` (`tables.clean`) remains the early-release shortcut: it sets `seating_cleaned_at = now()` and discards remaining time. If the timer already expired, Mark Clean materializes the release and returns 200; a second call still returns the existing already-clean conflict.

Optional `dinepro:release-expired-cleaning` (scheduled every minute) persists `seating_cleaned_at` for tidy database state. Correctness does not depend on it.

Checkout affects one order only.

React Floor LocalStorage is namespaced by `order_{id}` after placement, or `draft_{uuid}` for a new party, so T1-A and T1-B never share a cart. Cleaning timers are reconstructed from Laravel partition payloads after refresh, logout/login, or another device.

### Table click routing

The Floor keeps **one card per physical table**. Partition labels such as T1-A / T1-B appear in the table details dialog and operational screens, not as extra Floor cards.

Click routing uses occupancy/partition data from Laravel:

| Table state | Click |
| ----------- | ----- |
| Completely available (`occupied_seats = 0`, `cleaning_seats = 0`) | Customer Registration |
| Exactly one occupied party, no free capacity, no cleaning | Open that party's Order Pad |
| Partial, multiple parties, mixed occupied + cleaning, or cleaning-only | Table Details / partition dialog |

The dialog is the dispatcher when more than one action is valid: Open Order (exact `order_id`), Mark Clean, and `+ Seat New Customer` when `allowsNewSeating` is true. Cleaning-only tables never show Seat New Customer.

### Exclusive full-table dine types

`dine_types.requires_full_table` is the domain flag. Seeded values:

| Dine type | `requires_full_table` |
| --------- | --------------------- |
| Dine In | false (sharing allowed) |
| Take Away | false (no table) |
| Couple Dine | true |
| Candle Light Dinner | true |

Exclusive types may start only when the physical table is fully available and clean (`occupied_seats = 0` and `cleaning_seats = 0`). Laravel then forces `allocated_seats = tables.capacity`. `pax` remains the actual guest count and cannot exceed capacity. React may disable exclusive options on a partial table; the API still rejects `{Dine type name} requires a fully available table.`

Normal Dine In may still share remaining seats, or intentionally allocate the full table. Take Away stays table-less (`table_id`, `allocated_seats`, `partition_label`, and `seating_cleaned_at` remain `NULL`).

### Physical table vs party/order scope

Once a table is shared, party operations are scoped by `order_id`, not `table_id`.

| Physical table scope | Party / order scope |
| -------------------- | ------------------- |
| Capacity | Customer |
| Occupancy (occupied / cleaning / available seats) | Amount (`Order::itemsTotal()`) |
| Cleaning / Mark Clean | Items and item status |
| Availability | Cart / draft after placement |
| Table Details dispatcher | Checkout and checkout preview |
| Aggregate physical table status | KOT |
| | Bill |

`GET /tables/{table}/active-order/items` and `GET /tables/{table}/checkout-preview` are compatibility helpers only:

* 0 active parties → no active order
* 1 active party → resolve that order
* 2+ active parties → `409 Conflict`; never `first()`

React Floor uses `GET /api/v1/orders/{order}/items` and order-specific checkout. Bulk `PATCH /api/v1/order-items/status` may include `order_id` and rejects mixed item IDs from different parties.

Floor amount display:

* Empty / cleaning-only → no order amount
* Exactly one occupied party → that party's `itemsTotal()` on the card
* Multiple occupied parties → `N Active Orders`; party amounts appear in Table Details, never as a combined table total

Cleaning partitions may still show the completed party's amount in Table Details. It is not merged with another occupied party.

---

## 8. Development transaction reset

Reset **orders, billing, and table operational state** without wiping master data. Never run this in production.

Interactive (default answer is **No**):

```bash
ddev exec php artisan dinepro:reset-transactions
```

Automated local reset:

```bash
ddev exec php artisan dinepro:reset-transactions --yes
```

`--yes` skips confirmation only. It cannot bypass the production guard.

Deletes:

* orders
* order items
* bills
* bill items
* bill payments
* order transfers
* table cleaning holds

Preserves:

* customers
* users, roles, and permissions
* menu categories and items
* tables (number, capacity, floor, code, `is_active`, …)
* floors
* dine types, payment methods, and discounts

Resets:

* physical `tables.status` → Available (`TableOperationalStatus::Available`)

Occupancy then reports `occupied_seats = 0`, `cleaning_seats = 0`, `available_seats = capacity`. Order/bill numbers stay date-random; sequences are not rewound.

---

## 9. Table merge

A party stays attached to **one main physical table**. Extra tables are satellites stored on the order, not as extra Order rows.

### Persistence

`orders.merged_tables` is JSON, nullable, Laravel-cast to an array of snapshots `{id, number}`. The main table is **not** included. Example after merging T2 and T3 into T1:

* `orders.table_id` = T1
* `orders.merged_tables` = `[{"id":2,"number":"T2"},{"id":3,"number":"T3"}]`

Numbers are snapshotted so KOT / View Bill / Print Bill still show `T1 + T2 + T3` after a satellite is renamed or reused. Display is `Order::tableDisplayName()`. When merge snapshots exist, the partition suffix (`T1-A`) is omitted so the physical group is visible.

Merge state is **derived** from unreleased order metadata. There is no `TableOperationalStatus::Merged`. Satellites report Occupied (or Cleaning after checkout) and `available_seats = 0`. After checkout, one host timestamp (`cleaning_started_at` / `seating_cleaned_at`) drives timed cleaning for the whole group.

### Eligibility

A table can be newly merged only when `TableOccupancyService` says the **whole physical table** is free. Reject occupied, shared/partial, cleaning, inactive, already-merged, another order’s main table, and the main table itself. A shared table (free seats but already occupied) cannot initiate or receive merge. Multiple active parties on the main table cannot merge. Exclusive dine types may merge additional fully free tables; exclusive still applies to the main table and does not change `tables.capacity`, `pax`, or `allocated_seats`.

Merge / unmerge is allowed only while the main order is Placed. After checkout the snapshots are historical/frozen.

### API

* `GET /api/v1/tables/{table}/merge-options` — pre-order eligible satellites for a fully free main (`tables.merge`). Uses the same eligibility as post-order merge. No order is created.
* `GET /api/v1/orders/{order}/merge-tables` — options (`tables.merge`)
* `PUT /api/v1/orders/{order}/merge-tables` — `{ "table_ids": [...] }` desired satellite set. Empty array fully unmerges.

`POST /api/v1/orders` may include `merged_table_ids` when a new party’s `pax` exceeds the fully available main table. Laravel revalidates eligibility under lock, creates **one** order on the main table, and writes the existing `merged_tables` snapshot format. After placement the result is a normal merged order. Shared/occupied mains cannot use this path.

The PUT is an editor: it atomically releases removed satellites and claims newly selected ones. `TableMergeService` locks the main table, current/desired satellites, and relevant unreleased orders with `lockForUpdate()` in ID order.

`tables.merge` is assigned to Waiter, Captain, and Admin. Counter does not receive it.

### Floor / dashboards

React Floor keeps one card per physical table. Satellites are non-clickable (`Merged` / `Merged with T1`). The main card stays clickable and may label `T1 + T2 + T3`. Merge / Un-Merge lives on the order status popup. LocalStorage is not merge-authoritative; Floor refetches Laravel tables after merge, checkout, and Mark Clean.

Admin/Counter boards show `Merged → T1` on satellites. Summary counts treat satellites as Occupied (legacy status 2), not Available. There is no separate Merged count. Track Orders uses `tableDisplayName()`.

### Checkout / cleaning

Checkout of the main order sends **main + all satellites** into the existing 5-minute cleaning lifecycle. Mark Clean on the main order releases the whole group. Historical `merged_tables` is kept. Occupancy queries ignore released hosts so satellites can be seated again.

---

## 10. Table transfer (Phase 1)

Phase 1 moves a **single active party** to another physical table. The order stays the same row.

| Type | Meaning |
| ---- | ------- |
| `move` | Destination is fully available. Source enters transfer cleaning. |
| `share` | Destination already has an occupied party and enough **true available** seats. Orders and bills stay separate. |
| `join` | Source party moves onto a **specific destination order**. Items consolidate onto the surviving target. Source becomes historical (`joined_into_order_id`). Source table uses the same transfer cleaning hold as Move/Share. |

### Eligibility

Source (server-enforced):

* Placed / active, table-based
* Sole unreleased party on the source table (Phase 1 does **not** transfer one party off a shared table)
* No bill (including Open)
* Not a merge main (`hasMergedTables()`)
* Not a merged satellite
* `tables.transfer` (Waiter, Captain, Admin — not Counter)

Move destinations: active, fully free, not the source, not merge main/satellite, physical capacity ≥ incoming `allocated_seats` (legacy NULL falls back to `max(1, pax)` via `TableOccupancyService::allocatedSeats`).

Share destinations: active, occupied (not cleaning-only), not merge, true `available_seats` ≥ incoming allocation. Cleaning seats are **not** available.

### Cleaning

After the order’s `table_id` changes, the vacated source cannot use that order for cleaning. `table_cleaning_holds` records a table-level hold (`reason = transfer`) using `config('restaurant.cleaning_duration_seconds')`. Occupancy treats an active hold as cleaning capacity. Expired holds stop consuming seats without a scheduler, same as checkout cleaning. Floor Mark Clean on a hold partition calls `PATCH /api/v1/tables/{table}/seating/clean`. Checkout Mark Clean is unchanged.

Phase 1 holds consume the **physical table** because the source was the only party.

### Audit

`order_transfers` stores `type`, source order, from/to table IDs, pax, allocated seats, performer, and metadata snapshots `{from_table_number, to_table_number}`. `target_order_id` is null for Move and Share.

### API

* `GET /api/v1/orders/{order}/transfer-options`
* `POST /api/v1/orders/{order}/transfer` — `{ "type": "move"|"share", "destination_table_id": n }`
* Join: `{ "type": "join", "destination_table_id": n, "target_order_id": n }` — destination is a specific party, not merely a table.

### Join Party

Join is **not** Table Merge. Merge keeps one party across multiple tables. Join combines two parties into one billable order on one table.

* Source and destination must both be unbilled, unmerged, active/Placed.
* Destination capacity uses true available seats (occupied + cleaning + transfer holds are not available).
* Source items are reassigned to the target with immutable `origin_order_id`. Statuses are not reset; KOT is not replayed.
* Target `pax` becomes `target.pax + source.pax`. Target `allocated_seats` becomes the sum of both current allocations on the destination table.
* Source keeps its order number and row. `joined_into_order_id` / `joined_at` mark it historical. It cannot checkout or accept new items.
* Join creates no order and no bill. Checkout of the surviving target produces one bill.

`TableTransferService` locks the source order, source/destination tables (ID order), unreleased orders, and unreleased holds, then revalidates under the transaction.

### LocalStorage

After success, the waiter app updates the canonical `order_{id}` session’s `tableId` / `tableNumber`. It does not create `table_*` clones. Floor refetches tables.

### Floor

Order Status shows **one** Merge/Un-Merge control beside Transfer. Merged mains can Un-Merge; Transfer is hidden. Satellites stay non-clickable.

---

## 7. Out of scope

* Vacant / occupied / reserved / blocked UI
* QR image or PDF generation
* Customer booking URLs
* Counter / Kitchen / Waiter table boards
* Dashboard live table status (still a placeholder)
