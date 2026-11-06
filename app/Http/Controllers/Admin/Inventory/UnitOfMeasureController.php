<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Enums\UnitDimension;
use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\InventoryIndexRequest;
use App\Http\Requests\Admin\Inventory\UnitOfMeasureRequest;
use App\Http\Requests\Admin\Inventory\UpdateInventoryStatusRequest;
use App\Models\UnitOfMeasure;
use App\Services\Inventory\UnitOfMeasureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitOfMeasureController extends Controller
{
    public function __construct(private readonly UnitOfMeasureService $units) {}

    public function index(InventoryIndexRequest $request): View
    {
        return view('admin.inventory.units.index', [
            'units' => $this->units->paginate($request->filters()),
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.units.create', [
            'dimensions' => UnitDimension::cases(),
        ]);
    }

    public function store(UnitOfMeasureRequest $request): RedirectResponse
    {
        $this->units->create($request->validated());

        return redirect()
            ->route('admin.inventory.units.index')
            ->with('success', 'Unit of measure added successfully.');
    }

    public function show(UnitOfMeasure $unit): View
    {
        $unit->loadCount('items');

        return view('admin.inventory.units.show', ['unit' => $unit]);
    }

    public function edit(UnitOfMeasure $unit): View
    {
        return view('admin.inventory.units.edit', [
            'unit' => $unit,
            'dimensions' => UnitDimension::cases(),
        ]);
    }

    public function update(UnitOfMeasureRequest $request, UnitOfMeasure $unit): RedirectResponse
    {
        $this->units->update($unit, $request->validated());

        return redirect()
            ->route('admin.inventory.units.index')
            ->with('success', 'Unit of measure updated successfully.');
    }

    public function updateStatus(UpdateInventoryStatusRequest $request, UnitOfMeasure $unit): RedirectResponse
    {
        $this->units->updateStatus($unit, $request->boolean('is_active'));

        return redirect()
            ->route('admin.inventory.units.index')
            ->with('success', 'Unit of measure status updated successfully.');
    }

    public function destroy(UnitOfMeasure $unit): RedirectResponse
    {
        try {
            $this->units->delete($unit);
        } catch (BusinessConflictException $exception) {
            return redirect()
                ->route('admin.inventory.units.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.units.index')
            ->with('success', 'Unit of measure deleted successfully.');
    }
}
