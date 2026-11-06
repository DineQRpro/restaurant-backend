<?php

namespace App\Services\Inventory;

use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryItemService
{
    public function __construct(private readonly MasterCodeAllocator $codes) {}
    /**
     * @param  array{search?: string, is_active?: bool, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = InventoryItem::query()->with(['category', 'unit']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InventoryItem
    {
        unset($data['code']);
        $data['is_active'] ??= true;

        $item = new InventoryItem($data);
        $item->code = $this->codes->next(DocumentSequence::INVENTORY_ITEM, 'ITM');
        $item->save();

        return $item->fresh(['category', 'unit']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InventoryItem $item, array $data): InventoryItem
    {
        unset($data['code']);

        $item->update($data);

        return $item->fresh(['category', 'unit']);
    }

    public function updateStatus(InventoryItem $item, bool $isActive): InventoryItem
    {
        $item->update(['is_active' => $isActive]);

        return $item->fresh();
    }

    public function delete(InventoryItem $item): void
    {
        $item->delete();
    }
}
