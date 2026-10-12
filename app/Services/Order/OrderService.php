<?php

namespace App\Services\Order;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use App\Services\Table\TableMergeService;
use App\Services\Table\TableOccupancyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly TableOccupancyService $occupancy,
        private readonly TableMergeService $merge,
    ) {}

    /**
     * @param  array{
     *     customer_id?: int,
     *     dine_type_id?: int,
     *     table_id?: int|null,
     *     order_id?: int|null,
     *     pax?: int,
     *     allocated_seats?: int|null,
     *     merged_table_ids?: list<int>,
     *     items: list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>
     * }  $data
     */
    public function place(array $data, User $waiter): Order
    {
        /** @var Order $order */
        $order = DB::transaction(function () use ($data, $waiter) {
            $mergedIds = $this->normalizedMergedTableIds($data);
            $table = $this->resolveTable($data['table_id'] ?? null, $data['dine_type_id'] ?? null);
            $menuItems = $this->authoritativeMenuItems($data['items']);

            if ($table) {
                $mergedIds = array_values(array_filter(
                    $mergedIds,
                    fn (int $id): bool => $id !== (int) $table->id,
                ));
                $data['merged_table_ids'] = $mergedIds;
                $table = $this->lockPlacementResources($table, $mergedIds);
            }

            $appendTo = $this->resolveAppendTarget($data, $table);
            if ($appendTo) {
                return $this->appendItems($appendTo, $data['items'], $menuItems, $table);
            }

            return $this->createOrder($data, $waiter, $table, $menuItems);
        });

        return $order;
    }

    public function findActiveOrderForTable(int $tableId, bool $lockForUpdate = true): ?Order
    {
        $query = Order::query()
            ->active()
            ->where('table_id', $tableId)
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $orders = $query->get();

        if ($orders->count() > 1) {
            throw new BusinessConflictException('Multiple parties are seated at this table. Open a specific partition.');
        }

        return $orders->first();
    }

    public function findActiveOrderById(int $orderId, bool $lockForUpdate = true): ?Order
    {
        $query = Order::query()->active()->whereKey($orderId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function tableHasUnpaidBill(int $tableId): bool
    {
        return Bill::query()
            ->where('status', BillStatus::Open)
            ->whereHas('order', function ($query) use ($tableId): void {
                $query->where('table_id', $tableId);
            })
            ->exists();
    }

    /**
     * @param  array{
     *     order_id?: int|null,
     *     allocated_seats?: int|null,
     *     table_id?: int|null
     * }  $data
     */
    private function resolveAppendTarget(array $data, ?Table $table): ?Order
    {
        $explicitAllocation = array_key_exists('allocated_seats', $data) && $data['allocated_seats'] !== null;
        $requestedMerge = $this->normalizedMergedTableIds($data) !== [];

        // Seating a new party always sends allocated_seats. That must win over a
        // leftover order_id so T1-B is not appended onto T1-A.
        // Registration-time merge is also a new party, never an append.
        if ($explicitAllocation || $requestedMerge) {
            return null;
        }

        if (isset($data['order_id'])) {
            $order = $this->findActiveOrderById((int) $data['order_id']);
            if (! $order) {
                throw new BusinessConflictException('The selected order is not available for additional items.');
            }
            if ($table && (int) $order->table_id !== (int) $table->id) {
                throw new BusinessConflictException('The selected order does not belong to this table.');
            }

            return $order;
        }

        if ($table === null) {
            return null;
        }

        $activeOrders = Order::query()
            ->active()
            ->where('table_id', $table->id)
            ->orderBy('id')
            ->get();

        if ($activeOrders->count() === 1) {
            return $activeOrders->first();
        }

        if ($activeOrders->count() > 1) {
            throw new BusinessConflictException('Multiple parties are seated at this table. Open a partition to add items, or seat a new customer.');
        }

        if ($table->status === TableOperationalStatus::Occupied && $this->tableHasUnpaidBill($table->id)) {
            throw new BusinessConflictException('This table has an unpaid bill and cannot accept new items.');
        }

        if ($table->status === TableOperationalStatus::Occupied && $activeOrders->isEmpty()) {
            Log::warning('Occupied table has no active order; creating a recovery order.', [
                'table_id' => $table->id,
            ]);
        }

        return null;
    }

    /**
     * @param  array{
     *     customer_id?: int,
     *     dine_type_id?: int,
     *     pax?: int,
     *     allocated_seats?: int|null,
     *     merged_table_ids?: list<int>,
     *     items: list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>
     * }  $data
     * @param  array<int, MenuItem>  $menuItems
     */
    private function createOrder(array $data, User $waiter, ?Table $table, array $menuItems): Order
    {
        $customer = Customer::query()->whereKey($data['customer_id'] ?? 0)->first();
        if (! $customer || ! $customer->is_active) {
            throw new BusinessConflictException('An active customer is required to start a new order.');
        }

        $dineType = DineType::query()->whereKey($data['dine_type_id'] ?? 0)->first();
        if (! $dineType || ! $dineType->is_active) {
            throw new BusinessConflictException('An active dine type is required to start a new order.');
        }

        if ($dineType->requiresTable() && $table === null) {
            throw new BusinessConflictException('A table is required for this dine type.');
        }

        $pax = max(1, (int) ($data['pax'] ?? 1));
        $allocatedSeats = null;
        $partitionLabel = null;
        $mergeSnapshots = [];

        if ($table) {
            $this->occupancy->forgetSatelliteIndex();
            if ($this->occupancy->hostOrderForSatellite($table)) {
                throw new BusinessConflictException('This table is already merged with another table.');
            }

            $occupancy = $this->occupancy->snapshot($table);
            $requestedMergeIds = $this->normalizedMergedTableIds($data);
            $needsExtraCapacity = $pax > $occupancy->availableSeats;
            $useMerge = $needsExtraCapacity && $requestedMergeIds !== [];

            if ($useMerge && ! $occupancy->isFullyFree()) {
                throw new BusinessConflictException('Shared tables cannot be merged.');
            }

            if ($dineType->requiresFullTable()) {
                if (! $occupancy->isFullyFree()) {
                    throw new BusinessConflictException($dineType->name.' requires a fully available table.');
                }

                if ($pax > (int) $table->capacity && ! $useMerge) {
                    throw new BusinessConflictException('Guest count exceeds table capacity.');
                }

                $allocatedSeats = (int) $table->capacity;
            } else {
                $cleaningOnly = $occupancy->occupiedSeats === 0 && $occupancy->cleaningSeats > 0;

                if ($cleaningOnly) {
                    throw new BusinessConflictException('This table is being cleaned.');
                }

                if (! $occupancy->allowsNewSeating()) {
                    throw new BusinessConflictException('This table does not have enough available seats.');
                }

                $allocatedSeats = $useMerge
                    ? $occupancy->availableSeats
                    : (array_key_exists('allocated_seats', $data) && $data['allocated_seats'] !== null
                        ? (int) $data['allocated_seats']
                        : $pax);
            }

            if ($useMerge) {
                $mergeSnapshots = $this->merge->snapshotsForTables($requestedMergeIds, (int) $table->id);
                $mergedCapacity = $this->authoritativeMergedCapacity($requestedMergeIds);
                $totalCapacity = $allocatedSeats + $mergedCapacity;
                if ($totalCapacity < $pax) {
                    throw new BusinessConflictException($this->insufficientSeatingMessage(
                        $table,
                        $occupancy->availableSeats,
                        $pax,
                        $totalCapacity,
                    ));
                }
            } else {
                if ($needsExtraCapacity && $occupancy->isFullyFree()) {
                    throw new BusinessConflictException($this->insufficientSeatingMessage(
                        $table,
                        $occupancy->availableSeats,
                        $pax,
                        $occupancy->availableSeats,
                    ));
                }

                if ($allocatedSeats < $pax) {
                    throw new BusinessConflictException('Allocated seats cannot be less than the number of guests.');
                }

                if ($allocatedSeats > $occupancy->availableSeats) {
                    throw new BusinessConflictException('Allocated seats exceed the available table capacity.');
                }
            }

            $partitionLabel = $this->occupancy->nextPartitionLabel($table);
        }

        $order = Order::query()->create([
            'order_number' => $this->orderNumberGenerator->generate(),
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table?->id,
            'waiter_id' => $waiter->id,
            'pax' => $pax,
            'allocated_seats' => $allocatedSeats,
            'partition_label' => $partitionLabel,
            'merged_tables' => $mergeSnapshots === [] ? null : $mergeSnapshots,
            'seating_cleaned_at' => null,
            'status' => OrderStatus::Placed,
            'started_at' => now(),
        ]);

        $this->createItems($order, $data['items'], $menuItems);

        if ($table) {
            $table->unsetRelation('unreleasedOrders');
            $this->occupancy->forgetSatelliteIndex();
            $this->occupancy->applyOperationalStatusForOrder($order);
        }

        return $this->loadOrderForResponse($order);
    }

    /**
     * @param  list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>  $items
     * @param  array<int, MenuItem>  $menuItems
     */
    private function appendItems(Order $order, array $items, array $menuItems, ?Table $table): Order
    {
        $this->createItems($order, $items, $menuItems);

        if ($table) {
            $this->occupancy->applyOperationalStatus($table);
        }

        return $this->loadOrderForResponse($order->fresh());
    }

    private function loadOrderForResponse(Order $order): Order
    {
        return $order->load([
            'customer',
            'dineType',
            'table.currentOrder.customer',
            'table.currentOrder.dineType',
            'table.currentOrder.items',
            'table.unreleasedOrders.customer',
            'table.unreleasedOrders.dineType',
            'table.unreleasedOrders.items',
            'waiter',
            'items.menuItem',
        ]);
    }

    /**
     * @param  list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>  $items
     * @param  array<int, MenuItem>  $menuItems
     */
    private function createItems(Order $order, array $items, array $menuItems): void
    {
        foreach ($items as $item) {
            $menuItem = $menuItems[$item['menu_item_id']];

            $order->items()->create([
                'menu_item_id' => $menuItem->id,
                'quantity' => $item['quantity'],
                'unit_price' => $menuItem->price,
                'packing_fee' => $item['packing_fee'] ?? 0,
                'special_instructions' => $item['special_instructions'] ?? null,
                'status' => OrderItemStatus::Pending,
            ]);
        }
    }

    private function resolveTable(?int $tableId, ?int $dineTypeId): ?Table
    {
        $dineType = $dineTypeId ? DineType::query()->find($dineTypeId) : null;

        if ($dineType?->requiresTable() && $tableId === null) {
            throw new BusinessConflictException('A table is required for this dine type.');
        }

        if ($tableId === null) {
            return null;
        }

        $table = Table::query()->whereKey($tableId)->first();

        if (! $table) {
            throw new BusinessConflictException('The selected table was not found.');
        }

        if (! $table->is_active) {
            throw new BusinessConflictException('The selected table is inactive.');
        }

        if (
            $table->status !== TableOperationalStatus::Available
            && $table->status !== TableOperationalStatus::Occupied
            && $table->status !== TableOperationalStatus::Cleaning
        ) {
            throw new BusinessConflictException('This table is not available.');
        }

        return $table;
    }

    /**
     * @param  list<int>  $satelliteIds
     */
    private function lockPlacementResources(Table $table, array $satelliteIds): Table
    {
        $ids = array_values(array_unique(array_merge([(int) $table->id], $satelliteIds)));
        sort($ids);

        $lockedTables = Table::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        /** @var Table $table */
        $table = $lockedTables->get($table->id) ?? $table;

        Order::query()
            ->whereIn('table_id', $ids)
            ->whereNull('cancelled_at')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('status', OrderStatus::Placed)
                        ->whereNull('completed_at');
                })->orWhere(function ($query): void {
                    $query->where('status', OrderStatus::Checkout)
                        ->whereNull('seating_cleaned_at');
                });
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        Order::query()
            ->unreleased()
            ->where(function ($query) use ($ids): void {
                $query->whereIn('table_id', $ids)
                    ->orWhereNotNull('merged_tables');
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $table->unsetRelation('unreleasedOrders');
        $this->occupancy->forgetSatelliteIndex();

        foreach ($ids as $id) {
            $locked = $lockedTables->get($id);
            if ($locked) {
                $this->occupancy->releaseExpiredCleaning($locked);
            }
        }

        $orders = Order::query()
            ->unreleased()
            ->where('table_id', $table->id)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        $table->setRelation('unreleasedOrders', $orders);

        return $table;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function normalizedMergedTableIds(array $data): array
    {
        if (! isset($data['merged_table_ids']) || ! is_array($data['merged_table_ids'])) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $data['merged_table_ids'])));
        sort($ids);

        return array_values(array_filter($ids, fn (int $id): bool => $id > 0));
    }

    /**
     * @param  list<int>  $tableIds
     */
    private function authoritativeMergedCapacity(array $tableIds): int
    {
        if ($tableIds === []) {
            return 0;
        }

        return (int) Table::query()->whereIn('id', $tableIds)->sum('capacity');
    }

    private function insufficientSeatingMessage(Table $table, int $mainAvailable, int $pax, int $selectedCapacity): string
    {
        $eligibleExtra = (int) $this->merge->eligibleAvailableTablesForMain((int) $table->id)->sum('capacity');
        $maximum = $mainAvailable + $eligibleExtra;

        if ($maximum < $pax) {
            return "Not enough available seating for {$pax} guests.";
        }

        return "Selected tables provide {$selectedCapacity} seats, but {$pax} guests require seating.";
    }

    /**
     * @param  list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>  $items
     * @return array<int, MenuItem>
     */
    private function authoritativeMenuItems(array $items): array
    {
        $ids = collect($items)->pluck('menu_item_id')->unique()->all();

        $menuItems = MenuItem::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            $menuItem = $menuItems->get($id);

            if (! $menuItem) {
                throw new BusinessConflictException('A selected menu item was not found.');
            }

            if (! $menuItem->is_available) {
                throw new BusinessConflictException('A selected menu item is unavailable.');
            }
        }

        return $menuItems->all();
    }
}
