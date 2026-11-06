<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\InventoryIndexRequest;
use App\Http\Requests\Admin\Inventory\InventoryItemRequest;
use App\Http\Requests\Admin\Inventory\UpdateInventoryStatusRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\InventoryItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function __construct(private readonly InventoryItemService $items) {}

    public function index(InventoryIndexRequest $request): View
    {
        return view('admin.inventory.items.index', [
            'items' => $this->items->paginate($request->filters()),
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.items.create', $this->formOptions());
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        $this->items->create($request->validated());

        return redirect()
            ->route('admin.inventory.items.index')
            ->with('success', 'Inventory item added successfully.');
    }

    public function show(InventoryItem $item): View
    {
        $item->load(['category', 'unit']);

        return view('admin.inventory.items.show', ['item' => $item]);
    }

    public function edit(InventoryItem $item): View
    {
        $item->load(['category', 'unit']);

        return view('admin.inventory.items.edit', [
            'item' => $item,
            ...$this->formOptions($item),
        ]);
    }

    public function update(InventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->items->update($item, $request->validated());

        return redirect()
            ->route('admin.inventory.items.index')
            ->with('success', 'Inventory item updated successfully.');
    }

    public function updateStatus(UpdateInventoryStatusRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->items->updateStatus($item, $request->boolean('is_active'));

        return redirect()
            ->route('admin.inventory.items.index')
            ->with('success', 'Inventory item status updated successfully.');
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $this->items->delete($item);

        return redirect()
            ->route('admin.inventory.items.index')
            ->with('success', 'Inventory item deleted successfully.');
    }

    /**
     * @return array{categories: \Illuminate\Support\Collection, units: \Illuminate\Support\Collection}
     */
    private function formOptions(?InventoryItem $item = null): array
    {
        return [
            'categories' => InventoryCategory::query()
                ->where('is_active', true)
                ->when($item, fn ($query) => $query->orWhere('id', $item->inventory_category_id))
                ->orderBy('name')
                ->get(),
            'units' => UnitOfMeasure::query()
                ->where('is_active', true)
                ->when($item, fn ($query) => $query->orWhere('id', $item->unit_of_measure_id))
                ->orderBy('name')
                ->get(),
        ];
    }
}
