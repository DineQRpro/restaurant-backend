<?php

namespace App\Services\Order;

use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class KotEditService
{
    public function canEdit(Order $order): bool
    {
        if (! $order->isActive()) {
            return false;
        }

        $hasItems = $order->relationLoaded('items')
            ? $order->items->isNotEmpty()
            : $order->items()->exists();

        $hasBill = $order->relationLoaded('bills')
            ? $order->bills->isNotEmpty()
            : $order->bills()->exists();

        return $hasItems && ! $hasBill;
    }

    /**
     * @param  list<array{id: int, quantity?: int, status?: string, special_instructions?: string|null}>  $items
     */
    public function update(Order $order, array $items): void
    {
        DB::transaction(function () use ($order, $items): void {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load(['bills:id,order_id']);

            if (! $this->canEdit($order)) {
                throw new BusinessConflictException('This KOT can no longer be edited.');
            }

            $ids = array_values(array_unique(array_map(
                fn (array $item): int => (int) $item['id'],
                $items,
            )));

            $existing = OrderItem::query()
                ->where('order_id', $order->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($existing->count() !== count($ids)) {
                throw new BusinessConflictException('One or more order items do not belong to this order.');
            }

            foreach ($items as $payload) {
                /** @var OrderItem $item */
                $item = $existing->get((int) $payload['id']);
                $this->applyItemUpdate($item, $payload);
            }
        });
    }

    /**
     * @param  array{id: int, quantity?: int, status?: string, special_instructions?: string|null}  $payload
     */
    private function applyItemUpdate(OrderItem $item, array $payload): void
    {
        $current = $item->status instanceof OrderItemStatus
            ? $item->status
            : OrderItemStatus::from((string) $item->status);
        $locked = in_array($current, [OrderItemStatus::Served, OrderItemStatus::Cancelled], true);
        $updates = [];

        if (array_key_exists('status', $payload) && $payload['status'] !== null && $payload['status'] !== '') {
            $next = OrderItemStatus::from((string) $payload['status']);

            if (! $current->canTransitionTo($next)) {
                throw new BusinessConflictException('This status change is not allowed for one or more items.');
            }

            if ($current !== $next) {
                $updates['status'] = $next;
            }
        }

        if (array_key_exists('quantity', $payload) && $payload['quantity'] !== null) {
            $quantity = (int) $payload['quantity'];

            if ($quantity < 1) {
                throw new BusinessConflictException('Quantity must be at least 1.');
            }

            if ($locked && $quantity !== $item->quantity) {
                throw new BusinessConflictException('Served or cancelled items cannot be edited.');
            }

            if ($quantity !== $item->quantity) {
                $updates['quantity'] = $quantity;
            }
        }

        if (array_key_exists('special_instructions', $payload)) {
            $instructions = $payload['special_instructions'];

            if ($locked && $instructions !== $item->special_instructions) {
                throw new BusinessConflictException('Served or cancelled items cannot be edited.');
            }

            $updates['special_instructions'] = $instructions;
        }

        if ($updates !== []) {
            $item->update($updates);
        }
    }
}
