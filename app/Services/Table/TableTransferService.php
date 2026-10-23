<?php

namespace App\Services\Table;

use App\Enums\CleaningHoldReason;
use App\Enums\OrderStatus;
use App\Enums\TransferType;
use App\Exceptions\BusinessConflictException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTransfer;
use App\Models\Table;
use App\Models\TableCleaningHold;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TableTransferService
{
    public function __construct(
        private readonly TableOccupancyService $occupancy,
    ) {}

    public function canTransfer(Order $order, ?User $user): bool
    {
        return $user?->hasPermission('tables.transfer') === true
            && $this->isTransferEligibleSource($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(Order $order, User $user): array
    {
        $order->loadMissing(['table', 'customer', 'dineType']);
        $canTransfer = $this->canTransfer($order, $user);
        $requiredSeats = $this->occupancy->allocatedSeats($order);

        $move = [];
        $share = [];
        $join = [];

        if ($canTransfer && $order->table_id) {
            $this->occupancy->releaseExpiredCleaning($order->table);
            $this->occupancy->forgetSatelliteIndex();

            foreach ($this->candidateTables((int) $order->table_id) as $table) {
                $this->occupancy->releaseExpiredCleaning($table);
                $this->occupancy->forgetSatelliteIndex();

                if ($this->isEligibleMoveDestination($table, $order, $requiredSeats)) {
                    $move[] = $this->availableDestinationPayload($table);
                }

                if ($this->isEligibleShareDestination($table, $order, $requiredSeats)) {
                    $share[] = $this->occupiedDestinationPayload($table);
                }

                if ($this->isEligibleJoinDestination($table, $order, $requiredSeats)) {
                    foreach ($this->joinDestinationPayloads($table) as $party) {
                        $join[] = $party;
                    }
                }
            }
        }

        return [
            'can_transfer' => $canTransfer,
            'source' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'table_id' => $order->table_id,
                'table_number' => $order->table?->number,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'phone' => $order->customer->phone,
                ] : null,
                'pax' => (int) $order->pax,
                'allocated_seats' => $requiredSeats,
            ],
            'move' => $move,
            'share' => $share,
            'join' => $join,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transfer(Order $order, User $user, TransferType $type, int $destinationTableId, ?int $targetOrderId = null): array
    {
        if (! $user->hasPermission('tables.transfer')) {
            throw new BusinessConflictException('You do not have permission to transfer tables.');
        }

        if ($type === TransferType::Join) {
            return $this->joinParty($order, $user, $destinationTableId, $targetOrderId);
        }

        if (! $type->isImplemented()) {
            throw new BusinessConflictException('Join Party is not available yet.');
        }

        return DB::transaction(function () use ($order, $user, $type, $destinationTableId) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load(['table', 'customer', 'dineType', 'items', 'bills']);

            if ($order->table_id === null) {
                throw new BusinessConflictException('Only table-based orders can be transferred.');
            }

            $lockIds = [(int) $order->table_id, $destinationTableId];
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

            TableCleaningHold::query()
                ->unreleased()
                ->whereIn('table_id', $lockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var Table|null $sourceTable */
            $sourceTable = $lockedTables->get((int) $order->table_id);
            /** @var Table|null $destination */
            $destination = $lockedTables->get($destinationTableId);

            if (! $sourceTable || ! $destination) {
                throw new BusinessConflictException('This table is no longer available.');
            }

            $this->occupancy->releaseExpiredCleaning($sourceTable);
            $this->occupancy->releaseExpiredCleaning($destination);
            $this->occupancy->forgetSatelliteIndex();
            $sourceTable->unsetRelation('unreleasedOrders');
            $sourceTable->unsetRelation('cleaningHolds');
            $destination->unsetRelation('unreleasedOrders');
            $destination->unsetRelation('cleaningHolds');

            $this->assertTransferEligibleSource($order->fresh(['table', 'dineType', 'bills']) ?? $order);

            $requiredSeats = $this->occupancy->allocatedSeats($order);

            if ($type === TransferType::Move) {
                $this->assertEligibleMoveDestination($destination, $order, $requiredSeats);
            } else {
                $this->assertEligibleShareDestination($destination, $order, $requiredSeats);
            }

            $partitionLabel = $this->occupancy->nextPartitionLabel($destination);
            $fromNumber = $sourceTable->number;
            $toNumber = $destination->number;
            $fromTableId = (int) $sourceTable->id;

            OrderTransfer::query()->create([
                'source_order_id' => $order->id,
                'target_order_id' => null,
                'from_table_id' => $sourceTable->id,
                'to_table_id' => $destination->id,
                'type' => $type,
                'source_pax' => (int) $order->pax,
                'source_allocated_seats' => $requiredSeats,
                'performed_by' => $user->id,
                'transferred_at' => now(),
                'metadata' => [
                    'from_table_number' => $fromNumber,
                    'to_table_number' => $toNumber,
                ],
            ]);

            $order->update([
                'table_id' => $destination->id,
                'partition_label' => $partitionLabel,
            ]);

            $this->createTransferCleaningHold($sourceTable, $order);

            $order = $order->fresh(['table', 'customer', 'dineType', 'items']);
            $this->occupancy->forgetSatelliteIndex();
            $sourceTable->unsetRelation('unreleasedOrders');
            $sourceTable->unsetRelation('cleaningHolds');
            $destination->unsetRelation('unreleasedOrders');
            $destination->unsetRelation('cleaningHolds');
            $this->occupancy->applyOperationalStatus($sourceTable->fresh());
            $this->occupancy->applyOperationalStatus($destination->fresh());

            return [
                'type' => $type->value,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'from_table' => [
                    'id' => $fromTableId,
                    'number' => $fromNumber,
                ],
                'to_table' => [
                    'id' => $destination->id,
                    'number' => $toNumber,
                ],
                'partition_label' => $order->partition_label,
                'display_label' => $order->tableDisplayName(),
                'pax' => (int) $order->pax,
                'allocated_seats' => $this->occupancy->allocatedSeats($order),
            ];
        });
    }

    public function isTransferEligibleSource(Order $order): bool
    {
        $order->loadMissing(['table', 'dineType', 'bills']);

        if ($order->table_id === null || ! $order->table) {
            return false;
        }

        if ($order->dineType && ! $order->dineType->requiresTable()) {
            return false;
        }

        if ($order->status !== OrderStatus::Placed || ! $order->isActive()) {
            return false;
        }

        if ($order->hasMergedTables()) {
            return false;
        }

        if ($this->occupancy->hostOrderForSatellite($order->table)) {
            return false;
        }

        if ($order->bills->isNotEmpty()) {
            return false;
        }

        $others = $this->occupancy->unreleasedOrders($order->table)
            ->filter(fn (Order $candidate): bool => (int) $candidate->id !== (int) $order->id);

        return $others->isEmpty();
    }

    /**
     * @return Collection<int, Table>
     */
    private function candidateTables(int $sourceTableId): Collection
    {
        return Table::query()
            ->where('is_active', true)
            ->where('id', '!=', $sourceTableId)
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();
    }

    private function isEligibleMoveDestination(Table $table, Order $order, int $requiredSeats): bool
    {
        try {
            $this->assertEligibleMoveDestination($table, $order, $requiredSeats);
        } catch (BusinessConflictException) {
            return false;
        }

        return true;
    }

    private function isEligibleShareDestination(Table $table, Order $order, int $requiredSeats): bool
    {
        try {
            $this->assertEligibleShareDestination($table, $order, $requiredSeats);
        } catch (BusinessConflictException) {
            return false;
        }

        return true;
    }

    private function isEligibleJoinDestination(Table $table, Order $order, int $requiredSeats): bool
    {
        try {
            $this->assertEligibleJoinDestination($table, $order, $requiredSeats);
        } catch (BusinessConflictException) {
            return false;
        }

        return $this->joinableParties($table)->isNotEmpty();
    }

    private function assertTransferEligibleSource(Order $order): void
    {
        if ($this->isTransferEligibleSource($order)) {
            return;
        }

        if ($order->status !== OrderStatus::Placed || ! $order->isActive()) {
            throw new BusinessConflictException('Only an active placed order can be transferred.');
        }

        if ($order->hasMergedTables() || ($order->table && $this->occupancy->hostOrderForSatellite($order->table))) {
            throw new BusinessConflictException('Merged tables cannot be transferred.');
        }

        if ($order->relationLoaded('bills') ? $order->bills->isNotEmpty() : $order->bills()->exists()) {
            throw new BusinessConflictException('Orders with a bill cannot be transferred.');
        }

        throw new BusinessConflictException('Shared source tables cannot be transferred.');
    }

    private function assertEligibleMoveDestination(Table $table, Order $order, int $requiredSeats): void
    {
        $this->assertCommonDestination($table, $order);

        $occupancy = $this->occupancy->snapshot($table);

        if (! $occupancy->isFullyFree()) {
            throw new BusinessConflictException('This table is no longer available.');
        }

        if ((int) $table->capacity < $requiredSeats) {
            throw new BusinessConflictException('This table does not have enough available seats.');
        }
    }

    private function assertEligibleShareDestination(Table $table, Order $order, int $requiredSeats): void
    {
        $this->assertCommonDestination($table, $order);

        $occupancy = $this->occupancy->snapshot($table);

        if ($occupancy->occupiedSeats === 0) {
            throw new BusinessConflictException('Share Table requires an occupied table.');
        }

        if ($occupancy->availableSeats < $requiredSeats) {
            throw new BusinessConflictException('This table does not have enough available seats.');
        }
    }

    private function assertEligibleJoinDestination(Table $table, Order $order, int $requiredSeats): void
    {
        $this->assertCommonDestination($table, $order);

        $occupancy = $this->occupancy->snapshot($table);

        if ($occupancy->occupiedSeats === 0) {
            throw new BusinessConflictException('Join Party requires an occupied destination party.');
        }

        if ($occupancy->availableSeats < $requiredSeats) {
            throw new BusinessConflictException('This table does not have enough available seats.');
        }
    }

    private function assertCommonDestination(Table $table, Order $order): void
    {
        if (! $table->is_active) {
            throw new BusinessConflictException('Inactive tables cannot receive a transfer.');
        }

        if ((int) $table->id === (int) $order->table_id) {
            throw new BusinessConflictException('The destination cannot be the current table.');
        }

        if ($this->occupancy->hostOrderForSatellite($table)) {
            throw new BusinessConflictException('Merged tables cannot receive a transfer.');
        }

        $hasMergedMain = $this->occupancy->unreleasedOrders($table)
            ->contains(fn (Order $candidate): bool => $candidate->hasMergedTables());

        if ($hasMergedMain) {
            throw new BusinessConflictException('Merged tables cannot receive a transfer.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function availableDestinationPayload(Table $table): array
    {
        $occupancy = $this->occupancy->snapshot($table);

        return [
            'id' => $table->id,
            'number' => $table->number,
            'capacity' => (int) $table->capacity,
            'available_seats' => $occupancy->availableSeats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function occupiedDestinationPayload(Table $table): array
    {
        $occupancy = $this->occupancy->snapshot($table);
        $parties = $this->occupancy->unreleasedOrders($table)
            ->filter(fn (Order $order): bool => $this->occupancy->isOccupiedPartition($order))
            ->values()
            ->map(function (Order $party): array {
                $party->loadMissing(['customer', 'table:id,number']);

                return [
                    'partition_label' => $party->partition_label,
                    'display_label' => $party->tableDisplayName(),
                    'customer_name' => $party->displayCustomerName(),
                    'pax' => (int) $party->pax,
                    'allocated_seats' => $this->occupancy->allocatedSeats($party),
                ];
            })
            ->all();

        return [
            'id' => $table->id,
            'number' => $table->number,
            'capacity' => (int) $table->capacity,
            'occupied_seats' => $occupancy->occupiedSeats,
            'cleaning_seats' => $occupancy->cleaningSeats,
            'available_seats' => $occupancy->availableSeats,
            'parties' => $parties,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function joinDestinationPayloads(Table $table): array
    {
        $occupancy = $this->occupancy->snapshot($table);

        return $this->joinableParties($table)
            ->map(function (Order $party) use ($table, $occupancy): array {
                $party->loadMissing(['customer', 'table:id,number']);

                return [
                    'order_id' => $party->id,
                    'order_number' => $party->order_number,
                    'table_id' => $table->id,
                    'table_number' => $table->number,
                    'partition_label' => $party->partition_label,
                    'display_label' => $party->tableDisplayName(),
                    'customer_name' => $party->displayCustomerName(),
                    'pax' => (int) $party->pax,
                    'allocated_seats' => $this->occupancy->allocatedSeats($party),
                    'available_seats' => $occupancy->availableSeats,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Order>
     */
    private function joinableParties(Table $table): Collection
    {
        return $this->occupancy->unreleasedOrders($table)
            ->filter(fn (Order $party): bool => $this->isJoinEligibleTarget($party))
            ->values();
    }

    private function isJoinEligibleTarget(Order $party): bool
    {
        $party->loadMissing(['bills', 'dineType']);

        return $this->occupancy->isOccupiedPartition($party)
            && $party->isActive()
            && $party->status === OrderStatus::Placed
            && ! $party->isJoined()
            && ! $party->hasMergedTables()
            && $party->bills->isEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    private function joinParty(Order $order, User $user, int $destinationTableId, ?int $targetOrderId): array
    {
        if ($targetOrderId === null || $targetOrderId === (int) $order->id) {
            throw new BusinessConflictException('Join Party requires a specific destination party.');
        }

        return DB::transaction(function () use ($order, $user, $destinationTableId, $targetOrderId) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load(['table', 'customer', 'dineType', 'items', 'bills']);

            if ($order->isJoined() && (int) $order->joined_into_order_id === $targetOrderId) {
                return $this->existingJoinPayload($order, $targetOrderId);
            }

            if ($order->isJoined()) {
                throw new BusinessConflictException('This party has already joined another order.');
            }

            if ($order->table_id === null) {
                throw new BusinessConflictException('Only table-based orders can be transferred.');
            }

            $lockIds = [(int) $order->table_id, $destinationTableId];
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

            Order::query()->whereKey($targetOrderId)->lockForUpdate()->first();

            TableCleaningHold::query()
                ->unreleased()
                ->whereIn('table_id', $lockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var Table|null $sourceTable */
            $sourceTable = $lockedTables->get((int) $order->table_id);
            /** @var Table|null $destination */
            $destination = $lockedTables->get($destinationTableId);

            if (! $sourceTable || ! $destination) {
                throw new BusinessConflictException('This table is no longer available.');
            }

            $this->occupancy->releaseExpiredCleaning($sourceTable);
            $this->occupancy->releaseExpiredCleaning($destination);
            $this->occupancy->forgetSatelliteIndex();
            $sourceTable->unsetRelation('unreleasedOrders');
            $sourceTable->unsetRelation('cleaningHolds');
            $destination->unsetRelation('unreleasedOrders');
            $destination->unsetRelation('cleaningHolds');

            $source = $order->fresh(['table', 'dineType', 'bills', 'items']) ?? $order;
            $this->assertTransferEligibleSource($source);

            $requiredSeats = $this->occupancy->allocatedSeats($source);
            $this->assertEligibleJoinDestination($destination, $source, $requiredSeats);

            /** @var Order|null $target */
            $target = Order::query()->whereKey($targetOrderId)->first();
            if (! $target) {
                throw new BusinessConflictException('The selected destination party is no longer available.');
            }

            $target->load(['table', 'customer', 'dineType', 'items', 'bills']);
            $this->assertJoinEligibleTarget($target, $destination, $source);

            $sourceItemIds = $source->items->pluck('id')->all();
            $sourcePax = (int) $source->pax;
            $targetPax = (int) $target->pax;
            $targetSeats = $this->occupancy->allocatedSeats($target);
            $fromNumber = $sourceTable->number;
            $toNumber = $destination->number;
            $fromTableId = (int) $sourceTable->id;

            OrderItem::query()
                ->where('order_id', $source->id)
                ->whereNull('origin_order_id')
                ->update(['origin_order_id' => $source->id]);

            OrderItem::query()
                ->where('order_id', $source->id)
                ->update(['order_id' => $target->id]);

            $target->update([
                'pax' => $targetPax + $sourcePax,
                'allocated_seats' => $targetSeats + $requiredSeats,
            ]);

            $source->update([
                'joined_into_order_id' => $target->id,
                'joined_at' => now(),
                'table_id' => null,
            ]);

            OrderTransfer::query()->create([
                'source_order_id' => $source->id,
                'target_order_id' => $target->id,
                'from_table_id' => $sourceTable->id,
                'to_table_id' => $destination->id,
                'type' => TransferType::Join,
                'source_pax' => $sourcePax,
                'source_allocated_seats' => $requiredSeats,
                'performed_by' => $user->id,
                'transferred_at' => now(),
                'metadata' => [
                    'from_table_number' => $fromNumber,
                    'to_table_number' => $toNumber,
                    'source_item_ids' => $sourceItemIds,
                    'source_order_number' => $source->order_number,
                    'target_order_number' => $target->order_number,
                ],
            ]);

            $this->createTransferCleaningHold($sourceTable, $source);

            $target = $target->fresh(['table', 'customer', 'dineType', 'items']);
            $this->occupancy->forgetSatelliteIndex();
            $sourceTable->unsetRelation('unreleasedOrders');
            $sourceTable->unsetRelation('cleaningHolds');
            $destination->unsetRelation('unreleasedOrders');
            $destination->unsetRelation('cleaningHolds');
            $this->occupancy->applyOperationalStatus($sourceTable->fresh());
            $this->occupancy->applyOperationalStatus($destination->fresh());

            return $this->joinSuccessPayload(
                $source->fresh(),
                $target,
                $fromTableId,
                $fromNumber,
                $destination->id,
                $toNumber,
            );
        });
    }

    private function assertJoinEligibleTarget(Order $target, Table $destination, Order $source): void
    {
        if ((int) $target->id === (int) $source->id) {
            throw new BusinessConflictException('Join Party requires a specific destination party.');
        }

        if ((int) $target->table_id !== (int) $destination->id) {
            throw new BusinessConflictException('The selected destination party is no longer available.');
        }

        if (! $this->isJoinEligibleTarget($target)) {
            if ($target->hasMergedTables() || ($target->table && $this->occupancy->hostOrderForSatellite($target->table))) {
                throw new BusinessConflictException('Merged tables cannot receive a join.');
            }

            if ($target->relationLoaded('bills') ? $target->bills->isNotEmpty() : $target->bills()->exists()) {
                throw new BusinessConflictException('Orders with a bill cannot be joined.');
            }

            throw new BusinessConflictException('The selected destination party is no longer available.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function existingJoinPayload(Order $source, int $targetOrderId): array
    {
        $target = Order::query()->with(['table', 'customer', 'dineType', 'items'])->find($targetOrderId);
        if (! $target) {
            throw new BusinessConflictException('This party has already joined another order.');
        }

        $transfer = OrderTransfer::query()
            ->where('source_order_id', $source->id)
            ->where('target_order_id', $target->id)
            ->where('type', TransferType::Join)
            ->latest('id')
            ->first();

        $fromTableId = (int) ($transfer?->from_table_id ?? 0);
        $toTableId = (int) ($transfer?->to_table_id ?? $target->table_id);

        return $this->joinSuccessPayload(
            $source,
            $target,
            $fromTableId,
            (string) ($transfer?->metadata['from_table_number'] ?? ''),
            $toTableId,
            (string) ($transfer?->metadata['to_table_number'] ?? $target->table?->number ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function joinSuccessPayload(
        Order $source,
        Order $target,
        int $fromTableId,
        string $fromNumber,
        int $toTableId,
        string $toNumber,
    ): array {
        return [
            'type' => TransferType::Join->value,
            'order_id' => $target->id,
            'order_number' => $target->order_number,
            'source_order_id' => $source->id,
            'source_order_number' => $source->order_number,
            'from_table' => [
                'id' => $fromTableId,
                'number' => $fromNumber,
            ],
            'to_table' => [
                'id' => $toTableId,
                'number' => $toNumber,
            ],
            'partition_label' => $target->partition_label,
            'display_label' => $target->tableDisplayName(),
            'pax' => (int) $target->pax,
            'allocated_seats' => $this->occupancy->allocatedSeats($target),
        ];
    }

    private function createTransferCleaningHold(Table $table, Order $order): TableCleaningHold
    {
        $started = now();

        return TableCleaningHold::query()->create([
            'table_id' => $table->id,
            'source_order_id' => $order->id,
            'reason' => CleaningHoldReason::Transfer,
            'seats' => max(1, (int) $table->capacity),
            'started_at' => $started,
            'expires_at' => $started->copy()->addSeconds($this->occupancy->cleaningDurationSeconds()),
            'cleaned_at' => null,
        ]);
    }
}
