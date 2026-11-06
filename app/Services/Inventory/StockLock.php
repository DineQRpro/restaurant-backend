<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;

class StockLock
{
    /**
     * Lock inventory rows in ascending id order inside an open transaction.
     * Every stock-changing service uses this order after locking its source document.
     *
     * @param  list<int>  $itemIds
     */
    public function lockItems(array $itemIds): void
    {
        $ids = array_values(array_unique(array_map(intval(...), $itemIds)));
        sort($ids, SORT_NUMERIC);

        if ($ids === []) {
            return;
        }

        InventoryItem::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
