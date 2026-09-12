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

The response does **not** include operational occupancy (`vacant`, `occupied`, `reserved`). That belongs to a future Order / table-status module. React must not invent those values from `is_active`.

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

## 6. Out of scope

* Vacant / occupied / reserved / blocked UI
* Table merge and transfer
* QR image or PDF generation
* Customer booking URLs
* Counter / Kitchen / Waiter table boards
* Dashboard live table status (still a placeholder)
