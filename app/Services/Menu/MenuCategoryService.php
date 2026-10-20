<?php

namespace App\Services\Menu;

use App\Exceptions\BusinessConflictException;
use App\Models\MenuCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MenuCategoryService
{
    /**
     * @param  array{
     *     search?: string,
     *     menu_type?: string,
     *     is_active?: bool,
     *     sort?: string,
     *     order?: string,
     *     per_page?: int
     * }  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = MenuCategory::query()->with('menuType');

        if (! empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['menu_type'])) {
            $menuType = $filters['menu_type'];
            $query->whereHas('menuType', fn ($builder) => $builder->where('slug', $menuType));
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
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
    public function create(array $data): MenuCategory
    {
        $data['is_active'] ??= true;
        $data['sort_order'] ??= 0;

        return MenuCategory::create($data)->load('menuType');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MenuCategory $category, array $data): MenuCategory
    {
        $category->update($data);

        return $category->fresh('menuType');
    }

    public function updateStatus(MenuCategory $category, bool $isActive): MenuCategory
    {
        $category->update(['is_active' => $isActive]);

        return $category->fresh('menuType');
    }

    public function delete(MenuCategory $category): void
    {
        if ($category->items()->exists()) {
            throw new BusinessConflictException(
                'This category cannot be deleted because it still has menu items. Deactivate it or move the items first.'
            );
        }

        $category->delete();
    }

    private function sortableColumn(string $sort): string
    {
        $allowed = ['name', 'sort_order', 'is_active', 'created_at'];

        return in_array($sort, $allowed, true) ? $sort : 'sort_order';
    }
}
