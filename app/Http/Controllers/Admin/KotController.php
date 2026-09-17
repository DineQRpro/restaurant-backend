<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\UpdateKotRequest;
use App\Models\Order;
use App\Services\Order\KotEditService;
use App\Services\Order\KotViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class KotController extends Controller
{
    public function __construct(
        private readonly KotViewService $kotViewService,
        private readonly KotEditService $kotEditService,
    ) {}

    public function show(Order $order): View
    {
        $order = $this->kotViewService->forOrder($order);

        return view('shared.orders.kot', [
            'order' => $order,
            'tableDisplay' => $this->kotViewService->tableDisplay($order),
        ]);
    }

    public function edit(Order $order): View
    {
        $order = $this->kotViewService->forOrder($order);

        if (! $this->kotEditService->canEdit($order)) {
            abort(409, 'This KOT can no longer be edited.');
        }

        return view('shared.orders.edit-kot', [
            'order' => $order,
            'tableDisplay' => $this->kotViewService->tableDisplay($order),
            'updateUrl' => route('admin.orders.kot.update', $order),
        ]);
    }

    public function update(UpdateKotRequest $request, Order $order): JsonResponse
    {
        try {
            $this->kotEditService->update($order, $request->validated('items'));
        } catch (BusinessConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'KOT updated successfully.',
        ]);
    }
}
