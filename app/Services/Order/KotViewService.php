<?php

namespace App\Services\Order;

use App\Models\Order;

class KotViewService
{
    public function forOrder(Order $order): Order
    {
        return $order->load([
            'table:id,number',
            'dineType:id,name,slug',
            'customer:id,name',
            'waiter:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.menuItem:id,name',
        ]);
    }

    public function tableDisplay(Order $order): string
    {
        if ($order->table?->number) {
            return $order->table->number;
        }

        if ($order->dineType && ! $order->dineType->requiresTable()) {
            return 'Take Away';
        }

        return $order->dineType?->name ?: 'Take Away';
    }
}
