# Billing

View Bill (Admin / Counter) and Print Bill share persisted `bills` / `bill_items` as the financial source of truth. This document covers those Blade/session surfaces only. Reports already read `bills.additional_charges` and are unchanged.

## 1. View Bill

| Area | Route | Name | Permission |
| --- | --- | --- | --- |
| Admin | `GET /admin/orders/{order}/bill` | `admin.orders.bill` | `bills.view` |
| Counter | `GET /counter/orders/{order}/bill` | `counter.orders.bill` | `bills.view` |
| Admin apply | `POST /admin/orders/{order}/bill/adjustments` | `admin.orders.bill.adjustments` | `bills.discount` |
| Counter apply | `POST /counter/orders/{order}/bill/adjustments` | `counter.orders.bill.adjustments` | `bills.discount` |

`BillViewService` builds the table footer. One Apply persists discount + additional charges through `BillingService::applyAdjustments()`. Apply is disabled until the form is dirty and locally valid. Settled or partially paid bills cannot change adjustments.

Print Bill is available on a clean View Bill. Unapplied edits set `data-has-unapplied-bill-changes="true"` and block the Print link with **Apply changes before printing.** The print route never receives unsaved form values.

## 2. Print Bill

| Area | Route | Name | Permission |
| --- | --- | --- | --- |
| Admin | `GET /admin/orders/{order}/bill/print` | `admin.orders.bill.print` | `bills.view` |
| Counter | `GET /counter/orders/{order}/bill/print` | `counter.orders.bill.print` | `bills.view` |

Authorization follows existing bill viewing. `receipts.print` exists but is not required; anyone who can view the bill can print the persisted receipt. Admin and Counter area middleware still apply. Guests are redirected to the area login. Waiter / Captain cannot enter those Blade portals.

Thin `OrderBillController::print()` actions render `shared.billing.print-bill`. Financials come from `BillViewService::printViewData()` — the same persisted mapping as View Bill, except Print shows unsigned `bills.discount_amount` (View Bill prefixes a minus).

Core PHP reference: `VarsityKitchen` `billing/viewbill/{session}/{table}` → `BillprintController::bill` → `app/views/billprint/bill.php` (+ `thermal_styles.php`).

Thermal width: **80mm** (`@page { size: 80mm auto; }`). Screen preview is 74mm; print body is 78mm. The page auto-invokes `window.print()` and also has a screen-only Print button. No Admin/Counter chrome. No PDF.

Print is allowed for open/unpaid and settled bills. Payment rows are not printed: Core PHP had Payment Method commented out.

## 3. Historical items

Print (and View) lines use `bill_items`:

* `item_name`
* `quantity`
* `unit_price`
* `packing_fee`

Current `menu_items` names/prices are never used to rebuild an old receipt. Print columns follow Core PHP thermal labels: **Item / Qty / Rate / Amt**. Line amount is `unit_price * quantity`. Packing is item-level (`bill_items.packing_fee`) and shown as a compact `Pk` subline — it is not `bills.additional_charges`.

Shared seating uses `Order::tableDisplayName()` (`T1-A`, not only `T1`). Merged groups use the same helper with persisted `orders.merged_tables` snapshots (`T1 + T2 + T3`), including after satellite reuse.

## 4. Persisted financial mapping

| Print row | Source |
| --- | --- |
| Total Summary | `bills.subtotal` |
| Discount | `bills.discount_amount` (label may include persisted discount name or custom comment) |
| Taxable Amount | `bills.taxable_amount` |
| CGST / SGST | `BillViewService` / `BillCalculationService::taxBreakdown(taxable_amount)` so CGST + SGST = `bills.tax_amount` |
| GST Total | `bills.tax_amount` |
| Additional Charges | `bills.additional_charges` |
| Grand Total | presentation: `taxable_amount + tax_amount + additional_charges` |
| Rounded Off | persisted signed `bills.round_off` (`+0.40`, `-0.36`, `0.00`) |
| Payable Amount | `bills.grand_total` |

Laravel `grand_total` is the final payable amount, not the pre-round Grand Total.

Print contains no discount select, custom %, comment, additional-charges textbox, or Apply control.

## 5. Restaurant header

`config/restaurant.print` from env:

* `RESTAURANT_NAME` (falls back to `APP_NAME`)
* `RESTAURANT_ADDRESS`
* `RESTAURANT_PHONE`
* `RESTAURANT_GSTIN`

Empty fields are omitted. There is **no** Laravel source yet for logo or FSSAI; those Core PHP fields are not invented.

## 6. Dirty View Bill → no Print

Client-side only. The print GET always renders persisted Bill state. Users must Apply successfully before the Print link is enabled.

## 7. Out of scope / unchanged

Discount formula, GST rates, rounding, PaymentService, settlement, reports, partitions, cleaning, KOT, and order placement are unchanged by Print Bill.
