<?php

namespace App\Http\Controllers\Admin\Menu;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Menu\IndexMenuCategoryRequest;
use App\Http\Requests\Admin\Menu\StoreMenuCategoryRequest;
use App\Http\Requests\Admin\Menu\UpdateMenuCategoryRequest;
use App\Http\Requests\Admin\Menu\UpdateMenuCategoryStatusRequest;
use App\Models\MenuCategory;
use App\Models\MenuType;
use App\Services\Menu\MenuCategoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MenuCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCategoryService $menuCategoryService,
    ) {}

    public function index(IndexMenuCategoryRequest $request): View
    {
        $categories = $this->menuCategoryService->paginate($request->filters());

        return view('admin.menu.categories.index', [
            'categories' => $categories,
            'filters' => $request->filters(),
            'menuTypes' => $this->menuTypes(),
        ]);
    }

    public function create(): View
    {
        return view('admin.menu.categories.create', [
            'menuTypes' => $this->menuTypes(),
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): RedirectResponse
    {
        $this->menuCategoryService->create($this->payload($request));

        return redirect()
            ->route('admin.menu.categories.index')
            ->with('success', 'Cuisine added successfully.');
    }

    public function edit(MenuCategory $category): View
    {
        return view('admin.menu.categories.edit', [
            'category' => $category->load('menuType'),
            'menuTypes' => $this->menuTypes(),
        ]);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $category): RedirectResponse
    {
        $this->menuCategoryService->update($category, $this->payload($request, $category));

        return redirect()
            ->route('admin.menu.categories.index')
            ->with('success', 'Cuisine updated successfully.');
    }

    public function updateStatus(UpdateMenuCategoryStatusRequest $request, MenuCategory $category): RedirectResponse
    {
        $this->menuCategoryService->updateStatus($category, $request->boolean('is_active'));

        return redirect()
            ->route('admin.menu.categories.index')
            ->with('success', 'Cuisine status updated successfully.');
    }

    public function destroy(MenuCategory $category): RedirectResponse
    {
        $image = $category->image;

        try {
            $this->menuCategoryService->delete($category);
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('admin.menu.categories.index')
                ->with('error', $e->getMessage());
        }

        if ($image) {
            Storage::disk('public')->delete($image);
        }

        return redirect()
            ->route('admin.menu.categories.index')
            ->with('success', 'Cuisine deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreMenuCategoryRequest|UpdateMenuCategoryRequest $request, ?MenuCategory $category = null): array
    {
        $data = $request->safe()->except('image');

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $file = $request->file('image');

        if ($file instanceof UploadedFile) {
            if ($category?->image) {
                Storage::disk('public')->delete($category->image);
            }

            $data['image'] = $file->store('menu-categories', 'public');
        }

        return $data;
    }

    /**
     * @return Collection<int, MenuType>
     */
    private function menuTypes(): Collection
    {
        return MenuType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }
}
