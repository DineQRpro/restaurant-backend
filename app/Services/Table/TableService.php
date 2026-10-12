<?php

namespace App\Services\Table;

use App\Exceptions\BusinessConflictException;
use App\Models\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class TableService
{
    /**
     * @param  array{
     *     search?: string,
     *     is_active?: bool,
     *     per_page?: int
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Table::query()->with($this->currentOrderRelations());

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderBy('sort_order')->orderBy('number')->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Table
    {
        $data['is_active'] ??= true;
        $data['sort_order'] ??= 0;
        $data['code'] ??= $this->uniqueCode();

        return Table::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Table $table, array $data): Table
    {
        unset($data['code']);

        $table->update($data);

        return $table->fresh();
    }

    public function updateStatus(Table $table, bool $isActive): Table
    {
        $table->update(['is_active' => $isActive]);

        return $table->fresh();
    }

    public function delete(Table $table): void
    {
        throw new BusinessConflictException(
            'Tables cannot be permanently deleted. Deactivate the table instead.'
        );
    }

    private function uniqueCode(): string
    {
        do {
            $code = (string) Str::ulid();
        } while (Table::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return list<string>
     */
    public function currentOrderRelations(): array
    {
        return [
            'currentOrder.customer',
            'currentOrder.dineType',
            'currentOrder.items',
            'unreleasedOrders.customer',
            'unreleasedOrders.dineType',
            'unreleasedOrders.items',
            'cleaningHolds',
        ];
    }
}
