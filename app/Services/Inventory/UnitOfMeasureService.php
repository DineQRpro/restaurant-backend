<?php

namespace App\Services\Inventory;

use App\Exceptions\BusinessConflictException;
use App\Models\DocumentSequence;
use App\Models\UnitOfMeasure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UnitOfMeasureService
{
    public function __construct(private readonly MasterCodeAllocator $codes) {}
    /**
     * @param  array{search?: string, is_active?: bool, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = UnitOfMeasure::query()->withCount('items');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('symbol', 'like', '%'.$search.'%')
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
    public function create(array $data): UnitOfMeasure
    {
        unset($data['code']);
        $data['is_active'] ??= true;

        $unit = new UnitOfMeasure($data);
        $unit->code = $this->codes->next(DocumentSequence::UNIT_OF_MEASURE, 'UOM');
        $unit->save();

        return $unit->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(UnitOfMeasure $unit, array $data): UnitOfMeasure
    {
        unset($data['code']);

        $unit->update($data);

        return $unit->fresh();
    }

    public function updateStatus(UnitOfMeasure $unit, bool $isActive): UnitOfMeasure
    {
        $unit->update(['is_active' => $isActive]);

        return $unit->fresh();
    }

    public function delete(UnitOfMeasure $unit): void
    {
        if ($unit->items()->exists()) {
            throw new BusinessConflictException(
                'This unit cannot be deleted because inventory items use it. Deactivate it instead.'
            );
        }

        $unit->delete();
    }
}
