<?php

namespace App\Services\Inventory;

use App\Enums\GoodsReceiptStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessConflictException;
use App\Models\GoodsReceipt;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class GoodsReceiptVoider
{
    public function __construct(
        private readonly StockLock $locks,
        private readonly StockBalanceService $balances,
    ) {}

    public function void(GoodsReceipt $receipt, User $user, string $reason): GoodsReceipt
    {
        try {
            return DB::transaction(fn (): GoodsReceipt => $this->voidLocked($receipt, $user, $reason));
        } catch (UniqueConstraintViolationException $exception) {
            $fresh = GoodsReceipt::query()->with('lines')->find($receipt->id);

            if ($fresh?->status === GoodsReceiptStatus::Voided) {
                return $fresh;
            }

            throw $exception;
        }
    }

    private function voidLocked(GoodsReceipt $receipt, User $user, string $reason): GoodsReceipt
    {
        $locked = GoodsReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();

        if ($locked->status === GoodsReceiptStatus::Voided) {
            return $locked->load('lines');
        }

        if ($locked->status !== GoodsReceiptStatus::Posted) {
            throw new BusinessConflictException('Only a posted receipt can be voided.');
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new BusinessConflictException('A void reason is required.');
        }

        $locked->load('lines');
        $this->locks->lockItems($locked->lines->pluck('inventory_item_id')->all());

        $originals = InventoryStockMovement::query()
            ->where('goods_receipt_id', $locked->id)
            ->where('movement_type', StockMovementType::Receipt)
            ->orderBy('id')
            ->get();

        $outbound = [];

        foreach ($originals as $movement) {
            $outbound[$movement->inventory_item_id] = bcadd(
                $outbound[$movement->inventory_item_id] ?? '0.000',
                InventoryMoney::quantity($movement->quantity_in),
                3,
            );
        }

        foreach ($outbound as $itemId => $quantity) {
            $after = bcsub($this->balances->forItem((int) $itemId), $quantity, 3);

            if (bccomp($after, '0.000', 3) < 0) {
                throw new BusinessConflictException('Voiding this receipt would make stock negative.');
            }
        }

        $postedAt = now();

        foreach ($originals as $movement) {
            $reversal = new InventoryStockMovement;
            $reversal->inventory_item_id = $movement->inventory_item_id;
            $reversal->movement_type = StockMovementType::Reversal;
            $reversal->quantity_in = '0.000';
            $reversal->quantity_out = $movement->quantity_in;
            $reversal->goods_receipt_id = $locked->id;
            $reversal->goods_receipt_item_id = $movement->goods_receipt_item_id;
            $reversal->reversal_of_movement_id = $movement->id;
            $reversal->posted_at = $postedAt;
            $reversal->posted_by = $user->id;
            $reversal->save();
        }

        $locked->status = GoodsReceiptStatus::Voided;
        $locked->voided_by = $user->id;
        $locked->voided_at = $postedAt;
        $locked->void_reason = $reason;
        $locked->save();

        return $locked->fresh('lines');
    }
}
