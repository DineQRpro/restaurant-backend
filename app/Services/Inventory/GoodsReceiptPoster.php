<?php

namespace App\Services\Inventory;

use App\Enums\GoodsReceiptStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessConflictException;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class GoodsReceiptPoster
{
    public function __construct(
        private readonly StockLock $locks,
        private readonly StockBalanceService $balances,
    ) {}

    /**
     * @return array{receipt: GoodsReceipt, warnings: list<string>}
     */
    public function post(GoodsReceipt $receipt, User $user, bool $confirmZeroPrices): array
    {
        try {
            return DB::transaction(fn (): array => $this->postLocked($receipt, $user, $confirmZeroPrices));
        } catch (UniqueConstraintViolationException $exception) {
            $fresh = GoodsReceipt::query()->with('lines')->find($receipt->id);

            if ($fresh?->status === GoodsReceiptStatus::Posted) {
                return ['receipt' => $fresh, 'warnings' => []];
            }

            throw $exception;
        }
    }

    /**
     * @return array{receipt: GoodsReceipt, warnings: list<string>}
     */
    private function postLocked(GoodsReceipt $receipt, User $user, bool $confirmZeroPrices): array
    {
        $locked = GoodsReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

        if ($locked->status === GoodsReceiptStatus::Posted) {
            return ['receipt' => $locked->load('lines'), 'warnings' => []];
        }

        if ($locked->status !== GoodsReceiptStatus::Draft) {
            throw new BusinessConflictException('A voided receipt cannot be posted.');
        }

        $locked->load('lines');
        $vendor = Vendor::query()->whereKey($locked->vendor_id)->where('is_active', true)->first();

        if ($vendor === null) {
            throw new BusinessConflictException('Choose an active vendor.');
        }

        $items = InventoryItem::query()
            ->with(['category', 'unit'])
            ->whereIn('id', $locked->lines->pluck('inventory_item_id'))
            ->get()
            ->keyBy('id');

        $this->locks->lockItems($items->keys()->all());

        $hasZero = false;
        $incoming = [];

        foreach ($locked->lines as $line) {
            $item = $items->get($line->inventory_item_id);

            if ($item === null || ! $item->is_active) {
                throw new BusinessConflictException('Each line must use an active inventory item.');
            }

            $amounts = InventoryMoney::line($line->quantity, $line->unit_price, $line->gst_rate);
            $line->item_code_snapshot = $item->code;
            $line->item_name_snapshot = $item->name;
            $line->category_name_snapshot = $item->category?->name ?? '';
            $line->unit_name_snapshot = $item->unit?->name ?? '';
            $line->unit_symbol_snapshot = $item->unit?->symbol ?? '';
            $line->taxable_amount = $amounts['taxable_amount'];
            $line->gst_amount = $amounts['gst_amount'];
            $line->line_total = $amounts['line_total'];
            $line->save();

            if (bccomp(InventoryMoney::money($line->unit_price), '0.00', 2) === 0) {
                $hasZero = true;
            }

            $incoming[$item->id] = bcadd($incoming[$item->id] ?? '0.000', InventoryMoney::quantity($line->quantity), 3);
        }

        if ($locked->lines->isEmpty()) {
            throw new BusinessConflictException('Add at least one receipt line.');
        }

        if ($hasZero && ! $confirmZeroPrices) {
            throw new BusinessConflictException('Confirm that zero-cost lines should be posted.');
        }

        $header = InventoryMoney::header($locked->lines->map(fn (GoodsReceiptItem $line): array => [
            'taxable_amount' => InventoryMoney::money($line->taxable_amount),
            'gst_amount' => InventoryMoney::money($line->gst_amount),
            'line_total' => InventoryMoney::money($line->line_total),
        ])->all());

        InventoryMoney::assertPayment($locked->payment_status, $locked->paid_amount, $header['grand_total']);

        $warnings = [];

        foreach ($incoming as $itemId => $quantity) {
            $item = $items->get($itemId);
            $current = $this->balances->forItem($itemId);
            $projected = bcadd($current, $quantity, 3);
            $maximum = InventoryMoney::quantity($item->maximum_level);

            if (bccomp($projected, $maximum, 3) > 0) {
                $warnings[] = $item->name.' projected stock '.$projected.' exceeds maximum '.$maximum.'.';
            }
        }

        $postedAt = now();

        foreach ($locked->lines as $line) {
            $movement = new InventoryStockMovement;
            $movement->inventory_item_id = $line->inventory_item_id;
            $movement->movement_type = StockMovementType::Receipt;
            $movement->quantity_in = $line->quantity;
            $movement->quantity_out = '0.000';
            $movement->goods_receipt_id = $locked->id;
            $movement->goods_receipt_item_id = $line->id;
            $movement->posted_at = $postedAt;
            $movement->posted_by = $user->id;
            $movement->save();
        }

        $locked->vendor_code_snapshot = $vendor->code;
        $locked->vendor_name_snapshot = $vendor->name;
        $locked->subtotal = $header['subtotal'];
        $locked->gst_total = $header['gst_total'];
        $locked->grand_total = $header['grand_total'];
        $locked->outstanding_amount = InventoryMoney::outstanding($header['grand_total'], $locked->paid_amount);
        $locked->status = GoodsReceiptStatus::Posted;
        $locked->posted_by = $user->id;
        $locked->posted_at = $postedAt;
        $locked->save();

        return ['receipt' => $locked->fresh('lines'), 'warnings' => $warnings];
    }
}
