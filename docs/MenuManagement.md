# Restaurant ERP — Menu Management API

This document covers menu categories and menu items. Follow `docs/ApiStandards.md` for response format and `docs/Authorization.md` for permission checking.

Frontend visibility is not a security boundary. Laravel enforces every operation.

---

## 1. Architecture

```text
Controller
    ↓
Form Request
    ↓
Service
    ↓
Eloquent Model
    ↓
MariaDB
```

| Concern | Class |
| ------- | ----- |
| Categories | `MenuCategoryController` → `MenuCategoryService` → `MenuCategory` |
| Items | `MenuItemController` → `MenuItemService` → `MenuItem` |

There is no Repository layer.

---

## 2. Category structure

Table: `menu_categories`

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | bigint PK | |
| `name` | varchar(255) | Required. Not unique (legacy cuisine names were not unique). |
| `description` | text, nullable | |
| `image` | varchar(255), nullable | Path/filename string. File upload is not part of this API. |
| `sort_order` | unsigned int | Default `0`. |
| `is_active` | boolean | Default `true`. |
| `created_at` / `updated_at` | timestamps | |

Relationship:

```text
MenuCategory hasMany MenuItem  (items())
```

---

## 3. Menu item structure

Table: `menu_items`

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | bigint PK | |
| `name` | varchar(255) | Required. |
| `description` | longtext, nullable | |
| `category_id` | FK → `menu_categories.id` | Required. `restrictOnDelete`. |
| `dietary_type` | varchar, nullable | `vegetarian`, `non_vegetarian`, `eggetarian`. |
| `price` | `DECIMAL(10,2)` | Never integer/float. |
| `is_available` | boolean | Default `true`. Unavailable items stay in the database. |
| `is_chef_special` | boolean | Default `false`. |
| `sort_order` | unsigned int | Default `0`. Maps conceptually from legacy `priority`. |
| `spicy_level` | unsigned tinyint, nullable | `0`/`null` = not spicy, `1` = spicy, `2` = very spicy. |
| `created_at` / `updated_at` | timestamps | |

Relationship:

```text
MenuItem belongsTo MenuCategory  (category())
```

There is **no** `main_category_id` foreign key and **no** `mainCategory()` relationship. See section 10.

---

## 4. Existing → new field mapping

### `tbl_category` → `menu_categories`

| Legacy | New |
| ------ | --- |
| `category_id` | `id` |
| `category_title` | `name` |
| `category_desc` | `description` |
| `category_image` | `image` |
| `cat_created_at` | `created_at` |
| `cat_updated_at` | `updated_at` |

The old admin UI labeled this list **Cuisine**. New items still belong to one of these records via `category_id`.

`is_active` and `sort_order` are new. The legacy table had neither.

### `tbl_products` → `menu_items`

| Legacy | New |
| ------ | --- |
| `product_id` | `id` |
| `product_title` | `name` |
| `product_desc` | `description` |
| `product_category` | `category_id` (FK to `menu_categories`) |
| `product_original_price` | `price` (`DECIMAL(10,2)`) |
| `product_avail` (`1` available, `2` not available) | `is_available` (boolean) |
| `chef_special` (`1` / `0`) | `is_chef_special` (boolean) |
| `priority` | `sort_order` |
| `product_spicy` | `spicy_level` |
| `product_main_category` | `dietary_type` (not a category FK) |

The old Core PHP tables are not modified. This mapping is for the new schema only.

Images lived in `tbl_product_images`. Item images are **out of scope** for this module.

---

## 5. API endpoints

All endpoints require Sanctum (`Authorization: Bearer {token}`).

Unauthenticated requests return `401`. Missing permission returns `403`.

### Categories

| Method | Endpoint | Permission |
| ------ | -------- | ---------- |
| `GET` | `/api/v1/menu/categories` | `menu.view` |
| `POST` | `/api/v1/menu/categories` | `menu.create` |
| `GET` | `/api/v1/menu/categories/{category}` | `menu.view` |
| `PUT` | `/api/v1/menu/categories/{category}` | `menu.update` |
| `PATCH` | `/api/v1/menu/categories/{category}/status` | `menu.update` |
| `DELETE` | `/api/v1/menu/categories/{category}` | `menu.delete` |

List query parameters: `search`, `is_active`, `sort` (`name`, `sort_order`, `is_active`, `created_at`), `order` (`asc`, `desc`), `page`, `per_page` (default 20, max 100).

### Menu items

| Method | Endpoint | Permission |
| ------ | -------- | ---------- |
| `GET` | `/api/v1/menu/items` | `menu.view` |
| `POST` | `/api/v1/menu/items` | `menu.create` |
| `GET` | `/api/v1/menu/items/{item}` | `menu.view` |
| `PUT` | `/api/v1/menu/items/{item}` | `menu.update` |
| `PATCH` | `/api/v1/menu/items/{item}/status` | `menu.update` |
| `DELETE` | `/api/v1/menu/items/{item}` | `menu.delete` |

List query parameters:

| Parameter | Rules |
| --------- | ----- |
| `search` | Optional. Matches name or description. |
| `category_id` | Optional. Must exist in `menu_categories`. |
| `dietary_type` | Optional. `vegetarian`, `non_vegetarian`, or `eggetarian`. |
| `is_available` | Optional boolean. |
| `is_chef_special` | Optional boolean. |
| `sort` | Optional. `name`, `price`, `sort_order`, `created_at`, `is_available`. |
| `order` | Optional. `asc` or `desc`. |
| `per_page` | Optional. Default `20`. Maximum `100`. |
| `page` | Optional page number. |

List/show responses include a nested `category` object `{ id, name }`.

Pagination uses the existing `meta` object (`current_page`, `per_page`, `total`, `last_page`), not a nested `data.items` / `data.pagination` envelope.

---

## 6. Permissions

Existing permissions (already seeded):

```text
menu.view
menu.create
menu.update
menu.delete
```

Admin receives all of these through `RolePermissionSeeder`. Waiter, captain, and counter do **not** currently have menu master permissions.

There is no admin bypass. Authorization is permission-based.

The Laravel Admin portal also exposes the same category operations as Blade pages under `/admin/menu/categories`, using `MenuCategoryService` and the same permission slugs. See `docs/AdminDashboard.md`.

### Admin Menu Items UI

Blade pages under `/admin/menu/items` use `MenuItemController` → `MenuItemService`. They do **not** call `/api/v1/menu/items`.

| Method | Path | Permission |
| ------ | ---- | ---------- |
| `GET` | `/admin/menu/items` | `menu.view` |
| `GET` | `/admin/menu/items/create` | `menu.create` |
| `POST` | `/admin/menu/items` | `menu.create` |
| `GET` | `/admin/menu/items/{item}/edit` | `menu.update` |
| `PUT` | `/admin/menu/items/{item}` | `menu.update` |
| `PATCH` | `/admin/menu/items/{item}/status` | `menu.update` |
| `DELETE` | `/admin/menu/items/{item}` | `menu.delete` |

Listing search and filters (`search`, `category_id`, `dietary_type`, `is_available`, `is_chef_special`) are the same as the API and are applied server-side. Pagination is 20 per page and keeps query parameters.

Create/edit validation matches the API: required `name`, `category_id`, `dietary_type`, `price`; optional description, `spicy_level` (0–2), `sort_order`, `is_available`, `is_chef_special`. New items cannot use an inactive category. An existing item may keep its current category if that category was later deactivated.

Availability is toggled through `MenuItemService::updateStatus()`. Hard delete is currently allowed because `order_items` does not exist yet.

**Item images are not supported.** `menu_items` has no image column. Legacy product images lived in `tbl_product_images` and remain out of scope. The Admin UI does not add a separate storage path.

---

## 7. Validation

### Category create/update

```text
name          required|string|max:255
description   nullable|string
image         nullable|string|max:255
sort_order    nullable|integer|min:0
is_active     sometimes|boolean
```

Category names are **not** unique. The Core PHP cuisine form only required a title.

### Menu item create/update

```text
name             required|string|max:255
description      nullable|string
category_id      required|exists:menu_categories,id (must be active on create)
dietary_type     required|in:vegetarian,non_vegetarian,eggetarian
price            required|numeric|min:0|decimal:0,2
is_available     sometimes|boolean
is_chef_special  sometimes|boolean
sort_order       nullable|integer|min:0
spicy_level      nullable|integer|min:0|max:2
```

New items cannot be assigned to an inactive category.

Updating an item may keep its current category even if that category was later deactivated. It cannot be moved to a *different* inactive category.

---

## 8. Business rules

### Categories

* Inactive categories remain in the database and keep their items.
* Inactive categories cannot be selected when creating a new menu item.
* Existing items in an inactive category are not deleted or hidden from the menu master APIs.
* Deleting a category that still has items returns `409`. Items are never cascade-deleted.

### Menu items

* `is_available = false` means the item should not be offered for **new** orders. The row remains.
* Historical orders must keep a stable `menu_item_id` once the Order module exists. The items table uses `restrictOnDelete` from categories for the same reason.
* Hard-deleting an item is currently allowed because `order_items` does not exist yet. When orders are implemented, deletion must be blocked (or switched to deactivation) so historical lines are preserved.

### Soft deletes

SoftDeletes were **not** added.

Reasons:

* Users already use deactivation + `409` instead of `deleted_at`.
* Categories use `is_active` plus a delete-guard when items exist.
* Items use `is_available` for operational unavailability.
* Adding `deleted_at` now would be unused until orders exist, and would not by itself protect historical FKs.

---

## 9. Delete / dependency behavior

| Action | Result |
| ------ | ------ |
| `DELETE` category with no items | `200`, row removed |
| `DELETE` category with items | `409`, category and items unchanged |
| `DELETE` menu item | `200`, row removed (until orders exist) |
| `PATCH` item status `is_available: false` | Item remains; not offered for new orders |

Category FK:

```text
menu_items.category_id → menu_categories.id  ON DELETE RESTRICT
```

---

## 10. Decisions and legacy ambiguity

### `product_category`

Confirmed: foreign key to `tbl_category.category_id`. Joins in Waiter, Menu, Counter, and Report models all use:

```sql
JOIN tbl_category ON tbl_products.product_category = tbl_category.category_id
```

The create form labels this field **Cuisine**. Mapped to `menu_items.category_id`.

### `product_main_category`

**Not** a parent category and **not** an FK to `tbl_category`.

The live create/edit forms treat it as a dietary classification:

| Legacy value | Label | New `dietary_type` |
| ------------ | ----- | ------------------- |
| `1` | Vegetarian | `vegetarian` |
| `2` | Non Vegetarian | `non_vegetarian` |
| `3` | Eggetarian | `eggetarian` |

Waiter UI filters (`product_main_category === mainCategoryFilter`) use these same integers, not cuisine IDs.

A kitchen-menu backup form also listed `4` as “Dietary” (rendered with the vegetarian icon). That value is **not** on the live menu create form and is **not** in the new enum.

`MenuItem::mainCategory()` was therefore not created. List filtering uses `dietary_type`, not `main_category_id`.

### `product_spicy`

| Legacy | Meaning | New `spicy_level` |
| ------ | ------- | ----------------- |
| empty / other | Not spicy | `null` or `0` |
| `1` | Spicy (one pepper icon) | `1` |
| `2` | Very spicy (two pepper icons) | `2` |

### `chef_special`

Checkbox. `1` when checked. Mapped to boolean `is_chef_special`.

### `product_avail`

| Legacy | Meaning | New |
| ------ | ------- | --- |
| `1` | Available | `is_available = true` |
| `2` | Not available | `is_available = false` |

Ordering queries only load `product_avail = 1`.

### `priority`

Used as a featured/priority checkbox in the admin list (values such as `1` vs `4`), not a dense sequential sort. The new column is still `sort_order` for a cleaner ERP model.

### Category uniqueness

Core PHP only validated that `category_title` was required. Duplicate cuisine names were allowed. The new table has **no unique index** on `name`.
