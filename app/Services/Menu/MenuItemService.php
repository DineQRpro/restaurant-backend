<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MenuItemService
{
    /**
     * @param  array{
     *     search?: string,
     *     menu_type?: string,
     *     category_id?: int,
     *     dietary_type?: string,
     *     is_available?: bool,
     *     is_chef_special?: bool,
     *     sort?: string,
     *     order?: string,
     *     per_page?: int
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MenuItem::query()->with(['category.menuType']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['menu_type'])) {
            $menuType = $filters['menu_type'];
            $query->whereHas(
                'category.menuType',
                fn ($builder) => $builder->where('slug', $menuType),
            );
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['dietary_type'])) {
            $query->where('dietary_type', $filters['dietary_type']);
        }

        if (array_key_exists('is_available', $filters)) {
            $query->where('is_available', $filters['is_available']);
        }

        if (array_key_exists('is_chef_special', $filters)) {
            $query->where('is_chef_special', $filters['is_chef_special']);
        }

        $sort = $this->sortableColumn($filters['sort'] ?? 'sort_order');
        $direction = strtolower($filters['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction);

        if ($sort !== 'name') {
            $query->orderBy('name');
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MenuItem
    {
        $data['is_available'] ??= true;
        $data['is_chef_special'] ??= false;
        $data['sort_order'] ??= 0;

        return MenuItem::create($data)->load('category.menuType');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MenuItem $item, array $data): MenuItem
    {
        $item->update($data);

        return $item->fresh('category.menuType');
    }

    public function updateStatus(MenuItem $item, bool $isAvailable): MenuItem
    {
        $item->update(['is_available' => $isAvailable]);

        return $item->fresh('category.menuType');
    }

    public function delete(MenuItem $item): void
    {
        $item->delete();
    }

    private function sortableColumn(string $sort): string
    {
        $allowed = ['name', 'price', 'sort_order', 'created_at', 'is_available'];

        return in_array($sort, $allowed, true) ? $sort : 'sort_order';
    }
}
