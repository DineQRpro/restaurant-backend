# Acceptance checklist

## Master data
- [ ] Admin can list/create/view/edit/deactivate each of four masters.
- [ ] Duplicate categories/items/UoM values rejected per audited business scope.
- [ ] Vendor codes unique under concurrent creation and uneditable in UI/API.
- [ ] Referenced masters cannot be destructively deleted.
- [ ] Category appears on Inventory Item create/edit.
- [ ] Reorder level nonnegative and less than maximum.

## Receiving
- [x] Default autocomplete lists only active low-stock items, including exactly-at-threshold. Browser search for Chana returned it while stock was 0.000 and reorder was 10.000.
- [x] All Items is available on the receipt form. Ordering of low-stock items is covered by `GoodsReceiptTest`.
- [x] Vendor search displays name — code and submits the vendor id. Browser selected `Phase2B Vendor — VEN-000003`.
- [x] Item selection fills category, unit, stock, reorder level, and maximum level from the item master.
- [x] Multiple lines on one vendor bill are stored. Covered by `GoodsReceiptTest`.
- [x] Price, quantity, bill, and date validation are enforced on the server.
- [x] Paid, Unpaid, Partially Paid, and Credit are stored as distinct statuses, with paid-amount constraints.
- [x] Over-max warning is returned and does not block posting. Covered by `GoodsReceiptTest`.
- [x] Posting the same receipt twice does not create a second movement. The database unique key is `(goods_receipt_item_id, movement_type)`.
- [x] A failed post leaves the receipt draft and creates no movements. Covered by the zero-price confirmation test.
- [x] Concurrent receipts cannot corrupt balances. `tests/acceptance/phase2b_mariadb.php` held the receipt row and the goods-receipt sequence row on MariaDB while two other connections waited. Each check observed two `LOCK WAIT` transactions. One post produced one inbound movement per line, two creates produced `GRN-000002` and `GRN-000003`, and one void produced one reversal per line.
- [x] Posted receipt correction uses a reversal movement. A second void does not add another reversal. A void that would make stock negative is rejected.
- [x] Batch and expiry are stored on the receipt line. There is no separate lot balance.

## Phase 2B verification notes
- Draft creation, editing, print totals, inventory overview, low stock, and empty movement history were exercised in the browser. The first pass did not click Post or Void. Final acceptance did both on an isolated database; see **Final Acceptance Verification** in `08-phase-2b-implementation-report.md`.
- The temporary verification user, vendor, and draft receipt were removed. `order` and `bill` sequences were unchanged. `vendor` advanced to 4 and `goods_receipt` advanced to 2 because those allocated numbers are not reused.
- Banquet receipt tests and the live banquet receipt page still load. Order-wise report still shows View Bill. Sales payment methods and `BillStatus` were not changed.

## Integration / quality
- [ ] Admin sidebar appears; other roles remain unauthorized until granted.
- [ ] Authorization enforced on routes and actions.
- [ ] Existing Sales and Reports regressions pass.
- [ ] Responsive Blade views use existing admin components.
- [ ] Feature tests, migration dry-run/rollback review, and relevant project checks pass.
