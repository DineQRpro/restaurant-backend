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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $orderNumberGenerator,
    ) {}

    /**
     * @param  array{
     *     customer_id?: int,
     *     dine_type_id?: int,
     *     table_id?: int|null,
     *     pax?: int,
     *     items: list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>
     * }  $data
     */
    public function place(array $data, User $waiter): Order
    {
        /** @var Order $order */
        $order = DB::transaction(function () use ($data, $waiter) {
            $table = $this->resolveTable($data['table_id'] ?? null, $data['dine_type_id'] ?? null);
            $menuItems = $this->authoritativeMenuItems($data['items']);

            if ($table?->status === TableOperationalStatus::Occupied) {
                $activeOrder = $this->findActiveOrderForTable($table->id);

                if ($activeOrder) {
                    return $this->appendItems($activeOrder, $data['items'], $menuItems, $table);
                }

                if ($this->tableHasUnpaidBill($table->id)) {
                    throw new BusinessConflictException('This table has an unpaid bill and cannot accept new items.');
                }

                Log::warning('Occupied table has no active order; creating a recovery order.', [
                    'table_id' => $table->id,
                ]);
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
            ->latest('id');

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
     *     customer_id?: int,
     *     dine_type_id?: int,
     *     pax?: int,
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

        $order = Order::query()->create([
            'order_number' => $this->orderNumberGenerator->generate(),
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table?->id,
            'waiter_id' => $waiter->id,
            'pax' => max(1, (int) ($data['pax'] ?? 1)),
            'status' => OrderStatus::Placed,
            'started_at' => now(),
        ]);

        $this->createItems($order, $data['items'], $menuItems);

        if ($table) {
            $table->update([
                'status' => TableOperationalStatus::Occupied,
            ]);
        }

        return $this->loadOrderForResponse($order);
    }

    /**
     * @param  list<array{menu_item_id: int, quantity: int, packing_fee?: mixed, special_instructions?: string|null}>  $items
     * @param  array<int, MenuItem>  $menuItems
     */
    private function appendItems(Order $order, array $items, array $menuItems, Table $table): Order
    {
        $this->createItems($order, $items, $menuItems);

        $table->update([
            'status' => TableOperationalStatus::Occupied,
        ]);

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

        $table = Table::query()->whereKey($tableId)->lockForUpdate()->first();

        if (! $table) {
            throw new BusinessConflictException('The selected table was not found.');
        }

        if (! $table->is_active) {
            throw new BusinessConflictException('The selected table is inactive.');
        }

        if ($table->status === TableOperationalStatus::Cleaning) {
            throw new BusinessConflictException('This table is being cleaned.');
        }

        if (
            $table->status !== TableOperationalStatus::Available
            && $table->status !== TableOperationalStatus::Occupied
        ) {
            throw new BusinessConflictException('This table is not available.');
        }

        return $table;
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
