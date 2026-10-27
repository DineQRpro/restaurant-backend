# Restaurant ERP — Discount Management

This document covers the discount **master** used by the Admin Blade UI. Follow `docs/Authorization.md` for permission checking.

The Admin UI does **not** call `/api/v1`. `DiscountController` uses `DiscountService`. Waiter/Captain checkout currently does **not** apply these records; `BillCalculationService` still stores `discount_amount = 0` and `discount_id = null`.

---

## 1. Laravel schema

Table: `discounts`

| Column | Type | Notes |
| ------ | ---- | ----- |
| `id` | bigint PK | Laravel native |
| `name` | string(150) | Display name. Seed/update key. Unique in Admin validation |
| `type` | enum `custom`, `fixed` | Cast to `DiscountType` |
| `value` | decimal(10,2) default 0 | **Percentage**, 0–100 |
| `min_level` | unsigned int default 0 | Lower bound for Custom staff-entered percentage. `0` means unused |
| `max_level` | unsigned int default 0 | Upper bound for Custom staff-entered percentage. `0` means unused |
| `applies_to` | enum `product`, `order` default `order` | Cast to `DiscountAppliesTo` |
| `start_date` | datetime nullable | Optional configured start. Billing does not currently enforce it |
| `end_date` | datetime nullable | Optional configured end. Billing does not currently enforce it |
| `is_active` | boolean default true | Admin activate/deactivate |
| timestamps | | |

No soft deletes. `bills.discount_id` is nullable FK `nullOnDelete()`.

Related bill columns (unchanged by this CRUD):

* `discount_id`
* `other_discount_percentage`
* `other_discount_comment`
* `discount_amount`

---

## 2. Mapping from legacy `tbl_discounts`

| Legacy | Laravel |
| ------ | ------- |
| `discount_id` | `id` |
| `discount_name` | `name` |
| `discount_type` | `type` (`custom` / `fixed`) |
| `discount_value` | `value` |
| `discount_min_level` | `min_level` |
| `discount_max_level` | `max_level` |
| `applies_to` | `applies_to` |
| `start_date` / `end_date` | `start_date` / `end_date` |
| `status` | `is_active` |
| `deleted` | not copied. Unused discounts are hard-deleted; used discounts are blocked and should be deactivated |

---

## 3. Custom vs Fixed

Established from the live VarsityKitchen **form labels**, Laravel `DiscountSeeder`, and the older working apply path (`applaydiscountold`):

| Type | Meaning |
| ---- | ------- |
| **Custom** | Staff later enter a percentage. Master `value` is typically `0`. `min_level` / `max_level` bound that entered percentage (seeded `Others` is 1–30). Laravel stores the entered rate on the bill as `other_discount_percentage` when billing is implemented. |
| **Fixed** | Predetermined **percentage** stored in `value`. Seeded examples: `Discount 5%` → 5, `Discount 10%` → 10, `Non-Chargeable` → 100. Min/max are unused (`0`) on seeded fixed rows. |

Both Admin form options in VarsityKitchen were labeled `custom (%)` and `Fixed (%)`. `value` was labeled **Discount Percentage (%)**.

A later `DashboardController::applaydiscount()` rewrite compared `type === 'percentage'` (which never matches the `custom`/`fixed` enum) and treated `fixed` as a rupee amount. That path is inconsistent with the form, seeder names, and `bills.other_discount_percentage`. This CRUD follows the seeder + form + old apply path: **both types are percentages**.

---

## 4. Min / max

Laravel columns are integers, not money.

* Custom `Others`: `min_level = 1`, `max_level = 30` — percentage bounds for the cashier-entered rate.
* Fixed seeded discounts: `0` / `0`.
* Admin list shows `—` when the stored value is `0`.

The live VarsityKitchen form labeled these **Min. Amount (₹)** / **Max. Amount (₹)**. That labeling contradicts the seeded `Others` 1–30 range and `other_discount_percentage`. Admin Laravel labels them **Minimum Level** / **Maximum Level** and documents them as percentage bounds for Custom.

---

## 5. Applies to

Master can store `product` or `order`. Seeded discounts are all `order`.

Current Laravel billing does **not** apply product-level or order-level discounts at checkout. Configuring `product` on the master does not change cart math.

---

## 6. Dates

Nullable start/end are stored on the master. If both are present, `end_date >= start_date`.

Billing does **not** currently filter discounts by date. Dates are configuration only.

The live VarsityKitchen create/edit form had start/end fields **commented out**. Laravel Admin exposes them because the schema already has the columns.

---

## 7. Delete / history

If any `bills.discount_id` points at the discount, delete is rejected:

> This discount cannot be deleted because it is used on historical bills. Deactivate it instead.

Unused discounts may be hard-deleted. Historical bills keep `discount_id` and the master row so reports can still join `discounts.name`.

---

## 8. Admin routes

| Method | Path | Name | Permission |
| ------ | ---- | ---- | ---------- |
| GET | `/admin/discounts` | `admin.discounts.index` | `discounts.view` |
| GET | `/admin/discounts/create` | `admin.discounts.create` | `discounts.create` |
| POST | `/admin/discounts` | `admin.discounts.store` | `discounts.create` |
| GET | `/admin/discounts/{discount}/edit` | `admin.discounts.edit` | `discounts.update` |
| PUT | `/admin/discounts/{discount}` | `admin.discounts.update` | `discounts.update` |
| PATCH | `/admin/discounts/{discount}/status` | `admin.discounts.status` | `discounts.update` |
| DELETE | `/admin/discounts/{discount}` | `admin.discounts.destroy` | `discounts.delete` |

Admin-only Blade/session. Admin receives these slugs through the existing all-permissions assignment. Waiter / Captain / Counter do not get discount **master** permissions. Counter still has `bills.discount` for applying discounts on a bill when that flow exists.

---

## 9. Billing integration

Checkout preview and final checkout still:

* set `discount_id` null
* set `other_discount_percentage` 0
* set `discount_amount` 0
* compute GST from the undiscounted taxable amount

This CRUD does not change GST, payments, or reports. Reports already left-join `discounts.name` when `discount_id` is present.

---

## 10. Intentional differences from legacy

* Laravel-native column names (`name`, `type`, `is_active`) instead of `discount_*` / `status` / `deleted`.
* Unique name validation.
* Activate/deactivate instead of a `deleted` tinyint.
* Block delete when bills reference the discount (legacy hard-deleted anyway).
* Date fields are available in Admin (legacy live form hid them).
* Min/max labeled as levels/percent bounds, not rupees.
* Product vs order is stored but not applied at checkout.
* No REST API from Blade.
