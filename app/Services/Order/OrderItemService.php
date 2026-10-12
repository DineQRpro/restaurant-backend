<?php

namespace App\Services\Order;

use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderItemService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * @return array{order: ?Order, items: Collection<int, OrderItem>}
     */
    public function itemsForTable(Table $table): array
    {
        if (! $table->is_active) {
            throw new BusinessConflictException('The selected table is inactive.');
        }

        $activeCount = Order::query()->active()->where('table_id', $table->id)->count();
        if ($activeCount > 1) {
            throw new BusinessConflictException('Multiple parties are seated at this table. Open a specific partition.');
        }

        $order = $this->orderService->findActiveOrderForTable($table->id, false);

        if (! $order) {
            return [
                'order' => null,
                'items' => collect(),
            ];
        }

        $order->load(['customer', 'items.menuItem', 'table', 'dineType']);

        return [
            'order' => $order,
            'items' => $order->items,
        ];
    }

    /**
     * @return array{order: ?Order, items: Collection<int, OrderItem>}
     */
    public function itemsForOrder(Order $order): array
    {
        $order->load(['customer', 'items.menuItem', 'table', 'dineType']);

        return [
            'order' => $order,
            'items' => $order->items,
        ];
    }

    /**
     * @param  list<int>  $itemIds
     * @return Collection<int, OrderItem>
     */
    public function updateStatuses(array $itemIds, OrderItemStatus $status, ?int $orderId = null): Collection
    {
        return DB::transaction(function () use ($itemIds, $status, $orderId) {
            $items = OrderItem::query()
                ->whereIn('id', $itemIds)
                ->with('order')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count(array_unique($itemIds))) {
                throw new BusinessConflictException('One or more order items were not found.');
            }

            $orderIds = $items->map(fn (OrderItem $item): int => (int) $item->order_id)->unique()->values();
            if ($orderIds->count() !== 1) {
                throw new BusinessConflictException('Order items must belong to a single order.');
            }

            if ($orderId !== null && $orderIds->first() !== $orderId) {
                throw new BusinessConflictException('Order items must belong to the selected order.');
            }

            foreach ($itemIds as $itemId) {
                /** @var OrderItem $item */
                $item = $items->get($itemId);
                $order = $item->order;

                if (! $order instanceof Order || ! $order->isActive()) {
                    throw new BusinessConflictException('Order items must belong to an active order.');
                }

                $current = $item->status instanceof OrderItemStatus
                    ? $item->status
                    : OrderItemStatus::from((string) $item->status);

                if (! $current->canTransitionTo($status)) {
                    throw new BusinessConflictException('This status change is not allowed for one or more items.');
                }

                if ($current === $status) {
                    continue;
                }

                $item->update([
                    'status' => $status,
                ]);
            }

            return OrderItem::query()
                ->whereIn('id', $itemIds)
                ->with('menuItem')
                ->orderBy('id')
                ->get();
        });
    }
}
