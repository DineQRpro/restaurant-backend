<?php

namespace App\Services\Inventory;

use App\Exceptions\BusinessConflictException;
use App\Models\DocumentSequence;
use App\Models\InventoryCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryCategoryService
{
    public function __construct(private readonly MasterCodeAllocator $codes) {}

    /**
     * @param  array{search?: string, is_active?: bool, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = InventoryCategory::query()->withCount('items');

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
    public function create(array $data): InventoryCategory
    {
        unset($data['code']);
        $data['is_active'] ??= true;

        $category = new InventoryCategory($data);
        $category->code = $this->codes->next(DocumentSequence::INVENTORY_CATEGORY, 'CAT');
        $category->save();

        return $category->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InventoryCategory $category, array $data): InventoryCategory
    {
        unset($data['code']);

        $category->update($data);

        return $category->fresh();
    }

    public function updateStatus(InventoryCategory $category, bool $isActive): InventoryCategory
    {
        $category->update(['is_active' => $isActive]);

        return $category->fresh();
    }

    public function delete(InventoryCategory $category): void
    {
        if ($category->items()->exists()) {
            throw new BusinessConflictException(
                'This category cannot be deleted because inventory items use it. Deactivate it instead.'
            );
        }

        $category->delete();
    }
}
