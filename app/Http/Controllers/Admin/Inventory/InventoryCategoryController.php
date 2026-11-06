<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\InventoryCategoryRequest;
use App\Http\Requests\Admin\Inventory\InventoryIndexRequest;
use App\Http\Requests\Admin\Inventory\UpdateInventoryStatusRequest;
use App\Models\InventoryCategory;
use App\Services\Inventory\InventoryCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    public function __construct(private readonly InventoryCategoryService $categories) {}

    public function index(InventoryIndexRequest $request): View
    {
        return view('admin.inventory.categories.index', [
            'categories' => $this->categories->paginate($request->filters()),
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.categories.create');
    }

    public function store(InventoryCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->validated());

        return redirect()
            ->route('admin.inventory.categories.index')
            ->with('success', 'Item category added successfully.');
    }

    public function show(InventoryCategory $category): View
    {
        $category->loadCount('items');

        return view('admin.inventory.categories.show', ['category' => $category]);
    }

    public function edit(InventoryCategory $category): View
    {
        return view('admin.inventory.categories.edit', ['category' => $category]);
    }

    public function update(InventoryCategoryRequest $request, InventoryCategory $category): RedirectResponse
    {
        $this->categories->update($category, $request->validated());

        return redirect()
            ->route('admin.inventory.categories.index')
            ->with('success', 'Item category updated successfully.');
    }

    public function updateStatus(UpdateInventoryStatusRequest $request, InventoryCategory $category): RedirectResponse
    {
        $this->categories->updateStatus($category, $request->boolean('is_active'));

        return redirect()
            ->route('admin.inventory.categories.index')
            ->with('success', 'Item category status updated successfully.');
    }

    public function destroy(InventoryCategory $category): RedirectResponse
    {
        try {
            $this->categories->delete($category);
        } catch (BusinessConflictException $exception) {
            return redirect()
                ->route('admin.inventory.categories.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.categories.index')
            ->with('success', 'Item category deleted successfully.');
    }
}
