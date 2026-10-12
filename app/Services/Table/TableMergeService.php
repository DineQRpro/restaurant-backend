<?php

namespace App\Services\Table;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TableMergeService
{
    public function __construct(
        private readonly TableOccupancyService $occupancy,
    ) {}

    public function canManage(Order $order, ?User $user): bool
    {
        return $user?->hasPermission('tables.merge') === true
            && $this->isMergeEligibleMain($order);
    }

    /**
     * @return array{
     *     can_merge: bool,
     *     has_merged_tables: bool,
     *     merged_table_display: string,
     *     tables: list<array{id: int, number: string, capacity: int, selected: bool}>
     * }
     */
    public function options(Order $order, User $user): array
    {
        $order->loadMissing(['table:id,number', 'dineType']);
        $canManage = $this->canManage($order, $user);
        $current = $order->mergedTableSnapshots();
        $currentIds = $order->mergedTableIds();

        $tables = [];

        if ($canManage) {
            foreach ($current as $snapshot) {
                $table = Table::query()->find($snapshot['id']);
                $tables[] = [
                    'id' => $snapshot['id'],
                    'number' => $snapshot['number'],
                    'capacity' => (int) ($table?->capacity ?? 0),
                    'selected' => true,
                ];
            }

            foreach ($this->eligibleAvailableTablesForMain((int) $order->table_id, [], $order->id) as $table) {
                if (in_array($table->id, $currentIds, true)) {
                    continue;
                }

                $tables[] = [
                    'id' => $table->id,
                    'number' => $table->number,
                    'capacity' => (int) $table->capacity,
                    'selected' => false,
                ];
            }
        }

        return [
            'can_merge' => $canManage,
            'has_merged_tables' => $order->hasMergedTables(),
            'merged_table_display' => $order->tableDisplayName(),
            'tables' => $tables,
        ];
    }

    /**
     * @param  list<int>  $tableIds
     * @return array<string, mixed>
     */
    public function sync(Order $order, User $user, array $tableIds): array
    {
        if (! $user->hasPermission('tables.merge')) {
            throw new BusinessConflictException('You do not have permission to merge tables.');
        }

        $desiredIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($desiredIds);

        return DB::transaction(function () use ($order, $desiredIds) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load(['table', 'dineType']);

            if ($order->table_id === null) {
                throw new BusinessConflictException('Only table-based orders can merge tables.');
            }

            if ($order->status !== OrderStatus::Placed || ! $order->isActive()) {
                throw new BusinessConflictException('Table merge cannot be changed after checkout.');
            }

            $currentIds = $order->mergedTableIds();
            $lockIds = array_values(array_unique(array_merge(
                [$order->table_id],
                $currentIds,
                $desiredIds,
            )));
            sort($lockIds);

            $lockedTables = Table::query()
                ->whereIn('id', $lockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            Order::query()
                ->unreleased()
                ->where(function ($query) use ($lockIds): void {
                    $query->whereIn('table_id', $lockIds)
                        ->orWhereNotNull('merged_tables');
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->occupancy->forgetSatelliteIndex();

            $this->assertMergeEligibleMain($order->fresh(['table', 'dineType']) ?? $order);

            $snapshots = [];
            foreach ($desiredIds as $id) {
                if ($id === (int) $order->table_id) {
                    throw new BusinessConflictException('The main table cannot be merged into itself.');
                }

                /** @var Table|null $table */
                $table = $lockedTables->get($id);
                if (! $table) {
                    throw new BusinessConflictException('This table is no longer available.');
                }

                $this->assertTargetMergeable($table, (int) $order->table_id, $currentIds, $order->id);
                $snapshots[] = [
                    'id' => $table->id,
                    'number' => $table->number,
                ];
            }

            $affectedIds = array_values(array_unique(array_merge($currentIds, $desiredIds, [(int) $order->table_id])));

            $order->update([
                'merged_tables' => $snapshots === [] ? null : $snapshots,
            ]);

            $order = $order->fresh(['table', 'dineType']);
            $this->occupancy->forgetSatelliteIndex();
            $this->occupancy->applyOperationalStatusForOrder($order);

            foreach ($affectedIds as $id) {
                $table = Table::query()->find($id);
                if ($table) {
                    $this->occupancy->applyOperationalStatus($table);
                }
            }

            $order = $order->fresh(['table', 'dineType']);

            return [
                'order_id' => $order->id,
                'table_id' => $order->table_id,
                'merged_tables' => $order->mergedTableSnapshots(),
                'merged_table_display' => $order->tableDisplayName(),
            ];
        });
    }

    public function isMergeEligibleMain(Order $order): bool
    {
        $order->loadMissing(['table', 'dineType']);

        if ($order->table_id === null || ! $order->table) {
            return false;
        }

        if ($order->dineType && ! $order->dineType->requiresTable()) {
            return false;
        }

        if ($order->status !== OrderStatus::Placed || ! $order->isActive()) {
            return false;
        }

        if ($this->occupancy->hostOrderForSatellite($order->table)) {
            return false;
        }

        $occupiedOnMain = $this->occupancy->unreleasedOrders($order->table)
            ->filter(fn (Order $candidate): bool => $this->occupancy->isOccupiedPartition($candidate))
            ->values();

        if ($occupiedOnMain->count() !== 1 || (int) $occupiedOnMain->first()?->id !== (int) $order->id) {
            return false;
        }

        $occupancy = $this->occupancy->snapshot($order->table);

        return $occupancy->availableSeats === 0 && $occupancy->cleaningSeats === 0;
    }

    /**
     * @return array{
     *     can_merge: bool,
     *     main: array{id: int, number: string, capacity: int, available_seats: int},
     *     tables: list<array{id: int, number: string, capacity: int}>
     * }
     */
    public function optionsForTable(Table $table, User $user): array
    {
        $this->occupancy->releaseExpiredCleaning($table);
        $this->occupancy->forgetSatelliteIndex();
        $occupancy = $this->occupancy->snapshot($table);
        $canMerge = $user->hasPermission('tables.merge') === true
            && $table->is_active
            && $occupancy->isFullyFree()
            && $this->occupancy->hostOrderForSatellite($table) === null;

        $tables = [];
        if ($canMerge) {
            foreach ($this->eligibleAvailableTablesForMain((int) $table->id) as $candidate) {
                $tables[] = [
                    'id' => $candidate->id,
                    'number' => $candidate->number,
                    'capacity' => (int) $candidate->capacity,
                ];
            }
        }

        return [
            'can_merge' => $canMerge,
            'main' => [
                'id' => $table->id,
                'number' => $table->number,
                'capacity' => (int) $table->capacity,
                'available_seats' => $occupancy->availableSeats,
            ],
            'tables' => $tables,
        ];
    }

    /**
     * @return Collection<int, Table>
     */
    public function eligibleAvailableTables(Order $order): Collection
    {
        return $this->eligibleAvailableTablesForMain((int) $order->table_id, [], $order->id);
    }

    /**
     * @param  list<int>  $currentIds
     * @return Collection<int, Table>
     */
    public function eligibleAvailableTablesForMain(int $mainTableId, array $currentIds = [], ?int $ownerOrderId = null): Collection
    {
        return Table::query()
            ->where('is_active', true)
            ->where('id', '!=', $mainTableId)
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get()
            ->filter(function (Table $table) use ($mainTableId, $currentIds, $ownerOrderId): bool {
                try {
                    $this->assertTargetMergeable($table, $mainTableId, $currentIds, $ownerOrderId);
                } catch (BusinessConflictException) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  list<int>  $currentIds
     */
    public function assertTargetMergeable(Table $table, int $mainTableId, array $currentIds = [], ?int $ownerOrderId = null): void
    {
        if (! $table->is_active) {
            throw new BusinessConflictException('Inactive tables cannot be merged.');
        }

        if ((int) $table->id === $mainTableId) {
            throw new BusinessConflictException('The main table cannot be merged into itself.');
        }

        if (in_array($table->id, $currentIds, true)) {
            return;
        }

        $this->occupancy->releaseExpiredCleaning($table);
        $this->occupancy->forgetSatelliteIndex();

        $host = $this->occupancy->hostOrderForSatellite($table);
        if ($host && ($ownerOrderId === null || (int) $host->id !== $ownerOrderId)) {
            throw new BusinessConflictException('This table is already merged with another table.');
        }

        $occupancy = $this->occupancy->snapshot($table);

        if ($occupancy->cleaningSeats > 0 && $occupancy->occupiedSeats === 0) {
            throw new BusinessConflictException('Cleaning tables cannot be merged.');
        }

        if (! $occupancy->isFullyFree()) {
            if ($occupancy->occupiedSeats > 0 && $occupancy->availableSeats > 0) {
                throw new BusinessConflictException('Shared tables cannot be merged.');
            }

            throw new BusinessConflictException('This table is no longer available.');
        }

        $otherMain = Order::query()
            ->unreleased()
            ->where('table_id', $table->id)
            ->when($ownerOrderId !== null, fn ($query) => $query->where('id', '!=', $ownerOrderId))
            ->exists();

        if ($otherMain) {
            throw new BusinessConflictException('This table is already acting as a main table.');
        }
    }

    /**
     * @param  list<int>  $tableIds
     * @return list<array{id: int, number: string}>
     */
    public function snapshotsForTables(array $tableIds, int $mainTableId): array
    {
        $ids = array_values(array_unique(array_map('intval', $tableIds)));
        sort($ids);

        $snapshots = [];
        foreach ($ids as $id) {
            if ($id === $mainTableId) {
                throw new BusinessConflictException('The main table cannot be merged into itself.');
            }

            $table = Table::query()->find($id);
            if (! $table) {
                throw new BusinessConflictException('This table is no longer available.');
            }

            $this->assertTargetMergeable($table, $mainTableId);
            $snapshots[] = [
                'id' => $table->id,
                'number' => $table->number,
            ];
        }

        return $snapshots;
    }

    private function assertMergeEligibleMain(Order $order): void
    {
        if (! $this->isMergeEligibleMain($order)) {
            if ($order->status !== OrderStatus::Placed) {
                throw new BusinessConflictException('Table merge cannot be changed after checkout.');
            }

            throw new BusinessConflictException('Shared tables cannot be merged.');
        }
    }
}
