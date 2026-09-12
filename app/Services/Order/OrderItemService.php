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

        $order = $this->orderService->findActiveOrderForTable($table->id, false);

        if (! $order) {
            return [
                'order' => null,
                'items' => collect(),
            ];
        }

        $order->load(['customer', 'items.menuItem']);

        return [
            'order' => $order,
            'items' => $order->items,
        ];
    }

    /**
     * @param  list<int>  $itemIds
     * @return Collection<int, OrderItem>
     */
    public function updateStatuses(array $itemIds, OrderItemStatus $status): Collection
    {
        return DB::transaction(function () use ($itemIds, $status) {
            $items = OrderItem::query()
                ->whereIn('id', $itemIds)
                ->with('order')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count(array_unique($itemIds))) {
                throw new BusinessConflictException('One or more order items were not found.');
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
