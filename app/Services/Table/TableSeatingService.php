<?php

namespace App\Services\Table;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableCleaningHold;
use Illuminate\Support\Facades\DB;

class TableSeatingService
{
    public function __construct(
        private readonly TableOccupancyService $occupancy,
    ) {}

    public function markClean(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->table_id === null) {
                throw new BusinessConflictException('Only table-based orders can be marked clean.');
            }

            if ($order->status !== OrderStatus::Checkout) {
                throw new BusinessConflictException('Only checked-out seating can be marked clean.');
            }

            $table = Table::query()->whereKey($order->table_id)->lockForUpdate()->firstOrFail();
            $satelliteIds = $order->mergedTableIds();
            if ($satelliteIds !== []) {
                Table::query()->whereIn('id', $satelliteIds)->orderBy('id')->lockForUpdate()->get();
            }
            Order::query()
                ->where('table_id', $table->id)
                ->where('status', OrderStatus::Checkout)
                ->whereNull('seating_cleaned_at')
                ->lockForUpdate()
                ->get();

            if ($order->seating_cleaned_at !== null) {
                throw new BusinessConflictException('This seating has already been marked clean.');
            }

            $releasedAt = $this->occupancy->isCleaningExpired($order)
                ? ($this->occupancy->cleaningExpiresAt($order) ?? now())
                : now();

            $order->update([
                'seating_cleaned_at' => $releasedAt,
            ]);

            $table->unsetRelation('unreleasedOrders');
            $this->occupancy->forgetSatelliteIndex();
            $this->occupancy->applyOperationalStatusForOrder($order);

            return $order->fresh()->load([
                'customer',
                'dineType',
                'table.unreleasedOrders.customer',
                'table.unreleasedOrders.items',
                'table.currentOrder',
            ]);
        });
    }

    public function markTableClean(Table $table): Table
    {
        return DB::transaction(function () use ($table) {
            /** @var Table $table */
            $table = Table::query()->whereKey($table->id)->lockForUpdate()->firstOrFail();

            TableCleaningHold::query()
                ->unreleased()
                ->where('table_id', $table->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->occupancy->releaseExpiredCleaningHolds($table);

            $holds = $this->occupancy->activeCleaningHolds($table->unsetRelation('cleaningHolds'));
            if ($holds->isEmpty()) {
                throw new BusinessConflictException('This seating has already been marked clean.');
            }

            $releasedAt = now();
            foreach ($holds as $hold) {
                $hold->update(['cleaned_at' => $releasedAt]);
            }

            $table->unsetRelation('unreleasedOrders');
            $table->unsetRelation('cleaningHolds');
            $this->occupancy->forgetSatelliteIndex();

            return $this->occupancy->applyOperationalStatus($table);
        });
    }
}
