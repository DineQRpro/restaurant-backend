<?php

namespace App\Services\Inventory;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class MasterCodeAllocator
{
    public function next(string $sequence, string $prefix): string
    {
        return DB::transaction(function () use ($sequence, $prefix): string {
            $row = DocumentSequence::query()
                ->where('name', $sequence)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $row = DocumentSequence::query()->create([
                    'name' => $sequence,
                    'next_number' => 1,
                ]);
                $row = DocumentSequence::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $number = (int) $row->next_number;
            $row->next_number = $number + 1;
            $row->save();

            return sprintf('%s-%06d', $prefix, $number);
        }, 5);
    }
}
