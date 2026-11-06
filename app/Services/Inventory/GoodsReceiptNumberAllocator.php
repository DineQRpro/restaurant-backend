<?php

namespace App\Services\Inventory;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class GoodsReceiptNumberAllocator
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            $row = DocumentSequence::query()
                ->where('name', DocumentSequence::GOODS_RECEIPT)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = DocumentSequence::query()->create([
                    'name' => DocumentSequence::GOODS_RECEIPT,
                    'next_number' => 1,
                ]);
                $row = DocumentSequence::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $number = (int) $row->next_number;
            $row->next_number = $number + 1;
            $row->save();

            return sprintf('GRN-%06d', $number);
        });
    }
}
