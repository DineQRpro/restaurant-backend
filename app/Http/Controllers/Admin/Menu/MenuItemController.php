<?php

namespace App\Http\Controllers\Admin\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Menu\IndexMenuItemRequest;
use App\Http\Requests\Admin\Menu\StoreMenuItemRequest;
use App\Http\Requests\Admin\Menu\UpdateMenuItemRequest;
use App\Http\Requests\Admin\Menu\UpdateMenuItemStatusRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Services\Menu\MenuItemService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly MenuItemService $menuItemService,
    ) {}

    public function index(IndexMenuItemRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.menu.items.index', [
            'items' => $this->menuItemService->paginate($filters),
            'filters' => $filters,
            'menuTypes' => MenuType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => MenuCategory::query()
                ->whereHas('menuType', fn ($query) => $query->where('slug', $filters['menu_type']))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menu.items.create', [
            'categories' => $this->selectableCategories(),
        ]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        $this->menuItemService->create($this->payload($request));

        return redirect()
            ->route('admin.menu.items.index')
            ->with('success', 'Menu item added successfully.');
    }

    public function edit(MenuItem $item): View
    {
        return view('admin.menu.items.edit', [
            'item' => $item,
            'categories' => $this->selectableCategories($item),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $item): RedirectResponse
    {
        $this->menuItemService->update($item, $this->payload($request));

        return redirect()
            ->route('admin.menu.items.index')
            ->with('success', 'Menu item updated successfully.');
    }

    public function updateStatus(UpdateMenuItemStatusRequest $request, MenuItem $item): RedirectResponse
    {
        $this->menuItemService->updateStatus($item, $request->boolean('is_available'));

        return redirect()
            ->route('admin.menu.items.index')
            ->with('success', 'Menu item availability updated successfully.');
    }

    public function destroy(MenuItem $item): RedirectResponse
    {
        $this->menuItemService->delete($item);

        return redirect()
            ->route('admin.menu.items.index')
            ->with('success', 'Menu item deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreMenuItemRequest|UpdateMenuItemRequest $request): array
    {
        $data = $request->validated();

        if ($request->has('is_available')) {
            $data['is_available'] = $request->boolean('is_available');
        }

        if ($request->has('is_chef_special')) {
            $data['is_chef_special'] = $request->boolean('is_chef_special');
        }

        return $data;
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    private function selectableCategories(?MenuItem $item = null): Collection
    {
        return MenuCategory::query()
            ->with('menuType')
            ->where(function ($query) use ($item) {
                $query->where('is_active', true);

                if ($item) {
                    $query->orWhere('id', $item->category_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
