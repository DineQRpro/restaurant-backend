<?php

namespace App\Services\Table;

use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableCleaningHold;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TableOccupancyService
{
    /** @var Collection<int, Order>|null */
    private ?Collection $satelliteHosts = null;

    public function snapshot(Table $table): TableOccupancy
    {
        $host = $this->hostOrderForSatellite($table);
        if ($host) {
            $capacity = max(0, (int) $table->capacity);
            if ($this->isOccupiedPartition($host)) {
                return new TableOccupancy(
                    capacity: $capacity,
                    occupiedSeats: $capacity,
                    cleaningSeats: 0,
                    availableSeats: 0,
                    status: TableOperationalStatus::Occupied,
                );
            }

            if ($this->isCleaningPartition($host)) {
                return new TableOccupancy(
                    capacity: $capacity,
                    occupiedSeats: 0,
                    cleaningSeats: $capacity,
                    availableSeats: 0,
                    status: TableOperationalStatus::Cleaning,
                );
            }
        }

        $orders = $this->unreleasedOrders($table);
        $occupied = 0;
        $cleaning = 0;

        foreach ($orders as $order) {
            $seats = $this->allocatedSeats($order);

            if ($this->isOccupiedPartition($order)) {
                $occupied += $seats;
            } elseif ($this->isCleaningPartition($order)) {
                $cleaning += $seats;
            }
        }

        $capacity = max(0, (int) $table->capacity);
        $cleaning += $this->activeCleaningHoldSeats($table);
        $available = max(0, $capacity - $occupied - $cleaning);

        return new TableOccupancy(
            capacity: $capacity,
            occupiedSeats: $occupied,
            cleaningSeats: $cleaning,
            availableSeats: $available,
            status: $this->statusFromCounts($occupied, $cleaning),
        );
    }

    public function forgetSatelliteIndex(): void
    {
        $this->satelliteHosts = null;
    }

    public function hostOrderForSatellite(Table $table): ?Order
    {
        $host = $this->satelliteIndex()->get((int) $table->id);
        if (! $host) {
            return null;
        }

        if ($this->isOccupiedPartition($host) || $this->isCleaningPartition($host)) {
            return $host;
        }

        return null;
    }

    public function applyOperationalStatusForOrder(Order $order): void
    {
        $this->forgetSatelliteIndex();
        $ids = array_values(array_unique(array_filter([
            $order->table_id,
            ...$order->mergedTableIds(),
        ])));

        foreach ($ids as $id) {
            $table = Table::query()->find($id);
            if (! $table) {
                continue;
            }

            $table->unsetRelation('unreleasedOrders');
            $this->applyOperationalStatus($table);
        }
    }

    /**
     * @return Collection<int, Order>
     */
    private function satelliteIndex(): Collection
    {
        if ($this->satelliteHosts !== null) {
            return $this->satelliteHosts;
        }

        $hosts = collect();
        $orders = Order::query()
            ->unreleased()
            ->whereNotNull('merged_tables')
            ->with(['table:id,number'])
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            foreach ($order->mergedTableIds() as $id) {
                $hosts[$id] = $order;
            }
        }

        return $this->satelliteHosts = $hosts;
    }

    /**
     * Legacy table orders stored NULL allocated_seats. Use pax so an occupied
     * table cannot appear empty after migration.
     */
    public function allocatedSeats(Order $order): int
    {
        if ($order->table_id === null) {
            return 0;
        }

        if ($order->allocated_seats !== null) {
            return max(0, (int) $order->allocated_seats);
        }

        return max(1, (int) ($order->pax ?? 1));
    }

    public function isOccupiedPartition(Order $order): bool
    {
        return $order->table_id !== null && $order->isActive();
    }

    public function isCleaningPartition(Order $order): bool
    {
        return $order->table_id !== null
            && $order->status === OrderStatus::Checkout
            && $order->seating_cleaned_at === null
            && $order->cancelled_at === null
            && ! $this->isCleaningExpired($order);
    }

    public function cleaningDurationSeconds(): int
    {
        return max(1, (int) config('restaurant.cleaning_duration_seconds', 300));
    }

    public function cleaningStartedAt(Order $order): ?Carbon
    {
        return $order->cleaning_started_at ?? $order->updated_at;
    }

    public function cleaningExpiresAt(Order $order): ?Carbon
    {
        $started = $this->cleaningStartedAt($order);

        return $started?->copy()->addSeconds($this->cleaningDurationSeconds());
    }

    public function isCleaningExpired(Order $order): bool
    {
        if ($order->status !== OrderStatus::Checkout || $order->seating_cleaned_at !== null) {
            return false;
        }

        $expires = $this->cleaningExpiresAt($order);

        return $expires !== null && $expires->lte(now());
    }

    public function nextPartitionLabel(Table $table): string
    {
        $used = $this->unreleasedOrders($table)
            ->pluck('partition_label')
            ->filter(fn ($label): bool => is_string($label) && $label !== '')
            ->all();

        $index = 0;
        while (true) {
            $label = $this->labelAt($index);
            if (! in_array($label, $used, true)) {
                return $label;
            }
            $index++;
        }
    }

    public function applyOperationalStatus(Table $table): Table
    {
        $occupancy = $this->snapshot($table);

        if ($table->status !== $occupancy->status) {
            $table->update(['status' => $occupancy->status]);
        }

        return $table->refresh();
    }

    public function releaseExpiredCleaning(Table $table): void
    {
        $candidates = Order::query()
            ->where('status', OrderStatus::Checkout)
            ->whereNull('seating_cleaned_at')
            ->whereNull('cancelled_at')
            ->where(function ($query) use ($table): void {
                $query->where('table_id', $table->id)
                    ->orWhereNotNull('merged_tables');
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $released = collect();

        foreach ($candidates as $order) {
            $isMain = (int) $order->table_id === (int) $table->id;
            $isSatellite = in_array((int) $table->id, $order->mergedTableIds(), true);
            if (! $isMain && ! $isSatellite) {
                continue;
            }

            if (! $this->isCleaningExpired($order)) {
                continue;
            }

            $order->update([
                'seating_cleaned_at' => $this->cleaningExpiresAt($order) ?? now(),
            ]);
            $released->push($order);
        }

        $holdsReleased = $this->releaseExpiredCleaningHolds($table);

        if ($released->isEmpty() && ! $holdsReleased) {
            return;
        }

        $this->forgetSatelliteIndex();
        foreach ($released as $order) {
            $this->applyOperationalStatusForOrder($order);
        }

        if ($holdsReleased) {
            $table->unsetRelation('unreleasedOrders');
            $table->unsetRelation('cleaningHolds');
            $this->applyOperationalStatus($table);
        }
    }

    /**
     * @return Collection<int, TableCleaningHold>
     */
    public function activeCleaningHolds(Table $table): Collection
    {
        $holds = $table->relationLoaded('cleaningHolds')
            ? $table->cleaningHolds
            : TableCleaningHold::query()
                ->unreleased()
                ->where('table_id', $table->id)
                ->orderBy('id')
                ->get();

        return $holds
            ->filter(fn (TableCleaningHold $hold): bool => $this->isCleaningHoldActive($hold))
            ->values();
    }

    public function activeCleaningHoldSeats(Table $table): int
    {
        return (int) $this->activeCleaningHolds($table)->sum('seats');
    }

    public function isCleaningHoldActive(TableCleaningHold $hold): bool
    {
        return $hold->cleaned_at === null
            && $hold->expires_at !== null
            && $hold->expires_at->gt(now());
    }

    public function isCleaningHoldExpired(TableCleaningHold $hold): bool
    {
        return $hold->cleaned_at === null
            && $hold->expires_at !== null
            && $hold->expires_at->lte(now());
    }

    public function releaseExpiredCleaningHolds(Table $table): bool
    {
        $holds = TableCleaningHold::query()
            ->unreleased()
            ->where('table_id', $table->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $released = false;

        foreach ($holds as $hold) {
            if (! $this->isCleaningHoldExpired($hold)) {
                continue;
            }

            $hold->update([
                'cleaned_at' => $hold->expires_at ?? now(),
            ]);
            $released = true;
        }

        if ($released) {
            $table->unsetRelation('cleaningHolds');
        }

        return $released;
    }

    public function statusFromCounts(int $occupiedSeats, int $cleaningSeats): TableOperationalStatus
    {
        if ($occupiedSeats > 0) {
            return TableOperationalStatus::Occupied;
        }

        if ($cleaningSeats > 0) {
            return TableOperationalStatus::Cleaning;
        }

        return TableOperationalStatus::Available;
    }

    /**
     * @return Collection<int, Order>
     */
    public function unreleasedOrders(Table $table): Collection
    {
        if ($table->relationLoaded('unreleasedOrders')) {
            return $table->unreleasedOrders
                ->filter(fn (Order $order): bool => $this->isOccupiedPartition($order) || $this->isCleaningPartition($order))
                ->values();
        }

        $orders = Order::query()
            ->unreleased()
            ->where('table_id', $table->id)
            ->orderBy('id')
            ->get();

        $table->setRelation('unreleasedOrders', $orders);

        return $orders;
    }

    public function labelAt(int $index): string
    {
        $n = $index + 1;
        $label = '';

        while ($n > 0) {
            $n--;
            $label = chr(65 + ($n % 26)).$label;
            $n = intdiv($n, 26);
        }

        return $label;
    }
}
