<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableCleaningHold;
use App\Services\Table\TableOccupancyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredCleaning extends Command
{
    protected $signature = 'dinepro:release-expired-cleaning';

    protected $description = 'Persist seating_cleaned_at for checkout allocations whose cleaning period has expired';

    public function handle(TableOccupancyService $occupancy): int
    {
        $tableIds = Order::query()
            ->where('status', OrderStatus::Checkout)
            ->whereNull('seating_cleaned_at')
            ->whereNotNull('table_id')
            ->distinct()
            ->pluck('table_id')
            ->merge(
                TableCleaningHold::query()
                    ->unreleased()
                    ->pluck('table_id')
            )
            ->unique()
            ->values();

        $released = 0;

        foreach ($tableIds as $tableId) {
            $released += (int) DB::transaction(function () use ($tableId, $occupancy): int {
                $table = Table::query()->whereKey($tableId)->lockForUpdate()->first();
                if (! $table) {
                    return 0;
                }

                $before = Order::query()
                    ->where('table_id', $table->id)
                    ->where('status', OrderStatus::Checkout)
                    ->whereNull('seating_cleaned_at')
                    ->count();

                $occupancy->releaseExpiredCleaning($table);

                $after = Order::query()
                    ->where('table_id', $table->id)
                    ->where('status', OrderStatus::Checkout)
                    ->whereNull('seating_cleaned_at')
                    ->count();

                return max(0, $before - $after);
            });
        }

        $this->info("Released {$released} expired cleaning allocation(s).");

        return self::SUCCESS;
    }
}
