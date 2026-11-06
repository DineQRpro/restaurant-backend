<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\InventoryIndexRequest;
use App\Http\Requests\Admin\Inventory\UpdateInventoryStatusRequest;
use App\Http\Requests\Admin\Inventory\VendorRequest;
use App\Models\Vendor;
use App\Services\Inventory\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly VendorService $vendors) {}

    public function index(InventoryIndexRequest $request): View
    {
        return view('admin.inventory.vendors.index', [
            'vendors' => $this->vendors->paginate($request->filters()),
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.vendors.create');
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $vendor = $this->vendors->create($request->validated());

        return redirect()
            ->route('admin.inventory.vendors.show', $vendor)
            ->with('success', 'Vendor added successfully. Vendor code '.$vendor->code.' cannot be changed.');
    }

    public function show(Vendor $vendor): View
    {
        return view('admin.inventory.vendors.show', ['vendor' => $vendor]);
    }

    public function edit(Vendor $vendor): View
    {
        return view('admin.inventory.vendors.edit', ['vendor' => $vendor]);
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendors->update($vendor, $request->validated());

        return redirect()
            ->route('admin.inventory.vendors.index')
            ->with('success', 'Vendor updated successfully.');
    }

    public function updateStatus(UpdateInventoryStatusRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendors->updateStatus($vendor, $request->boolean('is_active'));

        return redirect()
            ->route('admin.inventory.vendors.index')
            ->with('success', 'Vendor status updated successfully.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $this->vendors->delete($vendor);

        return redirect()
            ->route('admin.inventory.vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }
}
