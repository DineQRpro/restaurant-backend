<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Discounts\IndexDiscountRequest;
use App\Http\Requests\Admin\Discounts\StoreDiscountRequest;
use App\Http\Requests\Admin\Discounts\UpdateDiscountRequest;
use App\Http\Requests\Admin\Discounts\UpdateDiscountStatusRequest;
use App\Models\Discount;
use App\Services\Discount\DiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiscountController extends Controller
{
    public function __construct(
        private readonly DiscountService $discountService,
    ) {}

    public function index(IndexDiscountRequest $request): View
    {
        return view('admin.discounts.index', [
            'discounts' => $this->discountService->paginate($request->filters()),
            'filters' => $request->only(['search', 'is_active', 'type', 'applies_to']),
            'types' => $this->discountService->types(),
            'appliesToOptions' => $this->discountService->appliesToOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.discounts.create', [
            'types' => $this->discountService->types(),
            'appliesToOptions' => $this->discountService->appliesToOptions(),
        ]);
    }

    public function store(StoreDiscountRequest $request): RedirectResponse
    {
        $this->discountService->create($this->payload($request));

        return redirect()
            ->route('admin.discounts.index')
            ->with('success', 'Discount added successfully.');
    }

    public function edit(Discount $discount): View
    {
        return view('admin.discounts.edit', [
            'discount' => $discount,
            'types' => $this->discountService->types(),
            'appliesToOptions' => $this->discountService->appliesToOptions(),
        ]);
    }

    public function update(UpdateDiscountRequest $request, Discount $discount): RedirectResponse
    {
        $this->discountService->update($discount, $this->payload($request));

        return redirect()
            ->route('admin.discounts.index')
            ->with('success', 'Discount updated successfully.');
    }

    public function updateStatus(UpdateDiscountStatusRequest $request, Discount $discount): RedirectResponse
    {
        $this->discountService->updateStatus($discount, $request->boolean('is_active'));

        return redirect()
            ->route('admin.discounts.index')
            ->with('success', 'Discount status updated successfully.');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        try {
            $this->discountService->delete($discount);
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('admin.discounts.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.discounts.index')
            ->with('success', 'Discount deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreDiscountRequest|UpdateDiscountRequest $request): array
    {
        $data = $request->safe()->all();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }
}
