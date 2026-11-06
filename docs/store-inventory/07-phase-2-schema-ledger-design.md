# Phase 2A design — goods receipts and stock ledger

Status: approved for Phase 2B, with the decisions in the next section. Those decisions supersede any conflicting sentence later in this document.

## Approved Phase 2B decisions

1. Receipt status is `draft`, `posted`, or `voided`. Voiding appends reversal movements, stores a reason, the user, and the timestamp, and leaves the receipt visible. A voided receipt is not posted again. Posted and voided receipts are not edited or deleted. A void that would make any item balance negative is rejected inside the same transaction that locks those items.
2. `paid_amount` is stored. `outstanding_amount` is `grand_total - paid_amount`. Paid requires the paid amount to equal the grand total. Unpaid and Credit require zero. Partially Paid requires an amount greater than zero and less than the grand total. Credit stays distinct from Unpaid. A grand total of `0.00` cannot be Partially Paid; Paid, Unpaid, and Credit are valid only when the paid amount is `0.00`. Payment status does not post or block stock.
3. Unit prices are tax-exclusive. Each line stores `gst_rate`, `taxable_amount`, `gst_amount`, and `line_total` (`taxable_amount + gst_amount`). The header stores `subtotal`, `gst_total`, and `grand_total`. GST rate `0` means no tax. This is one combined purchase-GST rate per line. Sales bills continue to split CGST and SGST in their own calculator. Purchase GST is not a tax return.
4. Vendor bill number is required. The stored display value trims the ends and collapses consecutive whitespace to one space. The unique key is that display value uppercased with `mb_strtoupper`. Punctuation is kept, so `INV-12` and `INV 12` are different bills, while `inv-12` and `INV-12` are the same bill for one vendor. Uniqueness is `(vendor_id, vendor_bill_key)` for every row that still exists: draft, posted, and voided. Deleting a draft removes the row and releases the number. Another vendor may use the same number.
5. Unit price `0.00` is valid stock. The receipt screen warns, and posting requires `confirm_zero_prices`. Negative prices are rejected.

Posting and voiding lock the receipt row, then lock the affected `inventory_items` rows with `lockForUpdate()` in ascending id order. That lock order is the stock-changing contract.

Permissions, matching this approval rather than the earlier `inventory.receipts.*` suggestion:

- `inventory.receipt.view`
- `inventory.receipt.create`
- `inventory.receipt.update`
- `inventory.receipt.post`
- `inventory.receipt.void`
- `inventory.stock.view`

Money columns replace the single `total_amount` field. Line columns add GST. Movement rows include `updated_at` because the table uses timestamps; application code still does not update a movement. Rounding remains half away from zero to 2 decimal places using `bcmath`: taxable amount from quantity times unit price, GST from the taxable amount times the rate divided by 100, line total from those two rounded amounts. Header totals are sums of the stored line amounts.

Phase 1 is the base: `inventory_categories`, `units_of_measure`, `inventory_items`, `vendors`, and the `document_sequences` row `vendor`. Verified in `docs/store-inventory/06-phase-1-verification.md`.

## What this design does not touch

Banquet `receipts` and `receipt_items` stay the event receipt book. Goods receipts get new tables. Sales orders, bills, `BillStatus`, and payment methods stay unchanged. `DocumentNumberService` continues to allocate only `order` and `bill`. Legacy `tbl_grocery_*` and `tbl_vendor` are not read or imported. There is one central store and no location column. There is no unit conversion, opening stock, kitchen issue, wastage, recipe deduction, or accounts-payable settlement.

## 1. Migration plan

One new migration, applied later only after this design is approved and only to the development database when that step is explicitly requested.

It would:

- Create `goods_receipts`, `goods_receipt_items`, and `inventory_stock_movements`.
- Insert `document_sequences.name = goods_receipt` with `next_number = 1`.
- Add foreign keys with `restrictOnDelete` so a vendor, item, user, receipt, or receipt line that is still referenced cannot be removed.
- Add the unique indexes and check constraints below.

`down()` would drop the three tables in reverse dependency order and delete the `goods_receipt` sequence row. It would not drop `document_sequences` and would not change the `order`, `bill`, or `vendor` rows.

Receipt numbers use the same locked-sequence pattern as `VendorCodeAllocator`, not `DocumentNumberService`. The allocator locks the `goods_receipt` row with `lockForUpdate()`, increments `next_number`, and returns `GRN-%06d` (`GRN-000001`, then `GRN-000002`). The number is assigned when the draft is created, inside the same database transaction as the receipt insert, so a rolled-back insert does not consume a number. The number is not fillable and is not accepted from a request.

### `goods_receipts`

| Column | Definition |
| --- | --- |
| `id` | bigint primary key |
| `receipt_number` | `string(20)` not null, unique |
| `idempotency_key` | `char(36)` not null, unique |
| `vendor_id` | FK `vendors.id`, restrict on delete |
| `vendor_code_snapshot` | `string(20)` not null |
| `vendor_name_snapshot` | `string(150)` not null |
| `vendor_bill_number` | `string(50)` not null |
| `vendor_bill_date` | `date` not null |
| `receipt_date` | `date` not null |
| `payment_status` | `string(30)` not null |
| `status` | `string(20)` not null, default `draft` |
| `total_amount` | `decimal(14,2)` not null |
| `notes` | `text` nullable |
| `created_by` | FK `users.id`, restrict on delete |
| `posted_by` | FK `users.id` nullable, restrict on delete |
| `posted_at` | `timestamp` nullable |
| `voided_by` | FK `users.id` nullable, restrict on delete |
| `voided_at` | `timestamp` nullable |
| `void_reason` | `string(255)` nullable |
| `timestamps` | `created_at`, `updated_at` |

Indexes and checks:

- Unique `receipt_number`.
- Unique `idempotency_key`.
- Unique `(vendor_id, vendor_bill_number)`.
- Index `receipt_date`, `status`, `payment_status`.
- `payment_status` in `paid`, `unpaid`, `partially_paid`, `credit`.
- `status` in `draft`, `posted`, `voided`.
- `total_amount >= 0`.
- Posted rows have `posted_at` and `posted_by`. Draft rows have both null. Voided rows have `voided_at`, `voided_by`, and a non-empty `void_reason`, and still keep the original `posted_at` and `posted_by`.

`voided` is included so a posted receipt can be corrected without deleting ledger rows. Confirmation of that third status is listed under unresolved decisions. The two statuses required for receiving are `draft` and `posted`.

### `goods_receipt_items`

| Column | Definition |
| --- | --- |
| `id` | bigint primary key |
| `goods_receipt_id` | FK `goods_receipts.id`, restrict on delete |
| `inventory_item_id` | FK `inventory_items.id`, restrict on delete |
| `item_code_snapshot` | `string(50)` not null |
| `item_name_snapshot` | `string(150)` not null |
| `unit_name_snapshot` | `string(100)` not null |
| `unit_symbol_snapshot` | `string(20)` not null |
| `quantity` | `decimal(12,3)` not null |
| `unit_price` | `decimal(12,2)` not null |
| `line_total` | `decimal(14,2)` not null |
| `batch_number` | `string(50)` nullable |
| `expiry_date` | `date` nullable |
| `timestamps` | `created_at`, `updated_at` |

Checks and indexes:

- `quantity > 0`.
- `unit_price >= 0`.
- `line_total >= 0`.
- Index `(goods_receipt_id)`, `(inventory_item_id)`.

The line stores the item’s base unit as text copied from `units_of_measure`. The request cannot submit a different unit. No conversion factor is stored. The same item may appear on more than one line when batch or expiry differs. There is no lot-balance table.

### `inventory_stock_movements`

| Column | Definition |
| --- | --- |
| `id` | bigint primary key |
| `inventory_item_id` | FK `inventory_items.id`, restrict on delete |
| `movement_type` | `string(30)` not null |
| `quantity_in` | `decimal(12,3)` not null, default `0` |
| `quantity_out` | `decimal(12,3)` not null, default `0` |
| `goods_receipt_id` | FK `goods_receipts.id`, restrict on delete |
| `goods_receipt_item_id` | FK `goods_receipt_items.id`, restrict on delete |
| `reversal_of_movement_id` | FK `inventory_stock_movements.id` nullable, restrict on delete |
| `posted_at` | `timestamp` not null |
| `posted_by` | FK `users.id`, restrict on delete |
| `created_at` | `timestamp` not null |

`updated_at` is omitted. A movement row is inserted once.

Checks and indexes:

- `movement_type` in `receipt`, `reversal`.
- Exactly one of `quantity_in` and `quantity_out` is greater than zero, and the other is zero.
- A `receipt` movement has `quantity_in > 0`, `quantity_out = 0`, and `reversal_of_movement_id` null.
- A `reversal` movement has `quantity_out > 0`, `quantity_in = 0`, and `reversal_of_movement_id` set.
- Unique `goods_receipt_item_id` together with `movement_type`, so each receipt line posts one receipt movement and at most one reversal movement.
- Unique `reversal_of_movement_id`.
- Index `(inventory_item_id, posted_at)` for balance reads.

No `current_stock` column is added to `inventory_items` or anywhere else.

## 2. Eloquent relationships

Proposed models, not created in this step:

- `GoodsReceipt` belongs to `Vendor`, `createdBy`, `postedBy`, and `voidedBy` (`User`). Has many `GoodsReceiptItem`. Has many `InventoryStockMovement`.
- `GoodsReceiptItem` belongs to `GoodsReceipt` and `InventoryItem`. Has many `InventoryStockMovement`.
- `InventoryStockMovement` belongs to `InventoryItem`, `GoodsReceipt`, `GoodsReceiptItem`, `postedBy`, and optional `reversalOf` (`InventoryStockMovement`). Has one `reversal`.
- `Vendor` has many `GoodsReceipt`.
- `InventoryItem` has many `GoodsReceiptItem` and `InventoryStockMovement`.
- `User` has many receipts created, posted, and voided.

`receipt_number` is not fillable. Snapshot columns, totals, status, poster, and void fields are assigned by services. Movement models expose no update or delete operation.

`payment_status` and `status` are PHP string enums, separate from `App\Enums\BillStatus`. `credit` is its own case. It is not stored or cast as `unpaid`.

## 3. Stock posting service

Proposed services, not created in this step:

- `GoodsReceiptNumberAllocator` locks `document_sequences` where `name = goods_receipt`.
- `GoodsReceiptService` creates, updates, and deletes drafts.
- `GoodsReceiptPoster` posts one draft.
- `GoodsReceiptVoider` voids one posted receipt by appending reversal movements.
- `StockBalanceService` reads the ledger.
- `Money` helper multiplies and rounds with `bcmath`, not binary floating point.

Draft create and update:

- Require an active vendor and at least one line once the receipt is posted. A draft may be saved with lines.
- Each line requires an active inventory item. Quantity must be greater than zero. Unit price must be zero or positive.
- Copy vendor code, vendor name, item code, item name, and base unit name and symbol onto the row at save time, and refresh those snapshots on each draft save from the current master row.
- Set `line_total` and `total_amount` on the server. Ignore any client-supplied total, receipt number, or status.
- The same `idempotency_key` returns the existing receipt instead of inserting another one.
- Delete a draft by deleting its lines and then the header inside one transaction. Posted and voided receipts are not deletable. Restrict foreign keys make a direct header delete fail while lines or movements exist.

Post:

1. Open one transaction.
2. `lockForUpdate()` the goods receipt.
3. If status is already `posted`, return that receipt and its movements. Insert nothing.
4. If status is not `draft`, stop.
5. Re-read vendor and each item. Reject a missing, inactive, or mismatched master. Reject a line whose submitted unit differs from the item’s current base unit.
6. Recompute every line total and the header total. Persist those values and refresh snapshots.
7. For each line, insert one `receipt` movement with `quantity_in` equal to the line quantity and `quantity_out` zero.
8. Set `status = posted`, `posted_by`, and `posted_at`.
9. Commit.

The maximum-stock comparison happens inside this transaction and is returned as a warning. It does not throw and does not stop the commit. Payment status is stored and is not read by the poster.

Void, if the third status is approved:

1. Lock the posted receipt.
2. If it is already `voided`, return it.
3. Insert one `reversal` movement per original `receipt` movement, with `quantity_out` equal to that movement’s `quantity_in` and `reversal_of_movement_id` pointing at the original row.
4. Set `voided_by`, `voided_at`, `void_reason`, and `status = voided`.
5. Leave the original receipt lines and original movements unchanged.

A failed line, constraint violation, or thrown exception rolls the whole transaction back. Draft posting either commits the receipt and every movement, or commits none of them.

## 4. Stock balance

Posted stock for an item is:

`sum(quantity_in) - sum(quantity_out)`

over `inventory_stock_movements` for that `inventory_item_id`. Items with no movements have balance `0.000`. Draft receipts contribute nothing.

The result is a `decimal(12,3)` string from SQL, compared with `bccomp`. Reorder and maximum levels are read from `inventory_items` at calculation time. They are not copied onto the ledger.

Low stock means the posted balance is less than or equal to `reorder_level`. The warning on post uses:

`posted balance + sum of this receipt’s quantities for that item`

compared with `maximum_level`. The warning payload includes current balance, incoming quantity, projected balance, and maximum level. Other unposted drafts are not part of the projection.

Batch and expiry stay on the receipt line and are not separate balance buckets. Menu selling prices are not read or written.

### Rounding

Quantity scale is 3. Money scale is 2, matching the rupee and paise precision already used for money in this application. Inventory cost is still not a sales price.

For each line:

1. `bcmul(quantity, unit_price, 6)`.
2. Round half away from zero to 2 decimal places.
3. Store that result as `line_total`.

`total_amount` is the sum of stored line totals, not a second rounding of the unrounded products. Example: quantity `1.005` and unit price `10.00` multiplies to `10.050000`, which rounds to `10.05`.

## 5. Concurrency and idempotency

- Receipt numbers are serialized by the locked `goods_receipt` sequence row, plus the unique `receipt_number` index.
- A repeated create with the same `idempotency_key` returns the first receipt.
- Posting locks the receipt row. A second concurrent post waits, sees `posted`, and returns the same receipt.
- The unique `(goods_receipt_item_id, movement_type)` index stops a second receipt movement if a caller bypasses the lock.
- The unique `reversal_of_movement_id` index stops a second reversal of the same movement.
- Balances are not stored, so two receipts for the same item cannot lose each other’s quantity. Each committed movement remains in the sum.
- Posted lines, posted headers, and movement rows have no update path. Void appends rows.

## 6. Feature test plan

Tests to add when implementation is approved. They should use the existing PHPUnit sqlite refresh and `InteractsWithAuthorization`. They should not migrate the MariaDB development database.

- Draft create stores `GRN-000001`, then the next draft stores `GRN-000002`. A submitted receipt number is ignored.
- The same idempotency key does not create two receipts.
- Duplicate vendor bill number for the same vendor is rejected. The same bill number for another vendor is allowed.
- Zero quantity, negative quantity, and negative unit price are rejected. A missing item, missing vendor, inactive item, and inactive vendor are rejected.
- `paid`, `unpaid`, `partially_paid`, and `credit` persist as four different values.
- Line total and header total follow the rounding rule above and ignore a client total.
- A draft leaves the stock balance at `0.000`.
- Posting creates one inbound movement per line, sets `posted`, and changes the balance by the received quantity.
- Payment status does not change the posted quantity.
- A second post, including an overlapping transaction, does not add movements.
- A thrown failure after the first movement insert leaves no receipt in `posted` and no movements.
- Two posted receipts for one item sum in the balance.
- Quantity that lands above the maximum still posts and returns the warning. Quantity exactly at the reorder level is low stock. Quantity just above it is not.
- Deleting a posted receipt fails. Updating a posted line or movement fails. Deleting a draft succeeds.
- Void, if approved, keeps the original movement and adds an outbound reversal that returns the balance to its prior value. A second void does not add another reversal.
- Changing the item name, vendor name, or unit symbol after posting does not change the snapshot columns.
- An item or vendor referenced by a receipt cannot be deleted. Category and unit deletion rules from Phase 1 still hold.
- Admin authorization is required. Waiter, captain, and counter remain denied. Sales `DocumentNumberService` still rejects the `goods_receipt` sequence name.
- No row is written to banquet `receipts`.

## 7. Decisions closed by Phase 2B

The five items that were open are now the approved rules at the top of this document. Lot balances remain item-level: batch and expiry stay on the receipt line. Phase 1 still stores maximum level as greater than or equal to reorder level.

Header money is `subtotal`, `gst_total`, `grand_total`, `paid_amount`, and `outstanding_amount`. Line money adds `gst_rate`, `taxable_amount`, and `gst_amount` beside `line_total`. `vendor_bill_key` is the unique normalized bill identity.
