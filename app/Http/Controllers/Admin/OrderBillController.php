<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ApplyBillAdjustmentsRequest;
use App\Models\Order;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillViewService;
use App\Services\Order\KotViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OrderBillController extends Controller
{
    public function __construct(
        private readonly BillViewService $billViewService,
        private readonly BillingService $billingService,
        private readonly KotViewService $kotViewService,
    ) {}

    public function show(Order $order): View
    {
        return view('shared.billing.view-bill', $this->viewData($order));
    }

    public function print(Order $order): View
    {
        return view('shared.billing.print-bill', $this->billViewService->printViewData($order));
    }

    public function updateAdjustments(ApplyBillAdjustmentsRequest $request, Order $order): JsonResponse
    {
        $bill = $this->billViewService->forOrder($order);

        $validated = $request->validated();

        try {
            $bill = $this->billingService->applyAdjustments(
                $bill,
                $validated['discount_id'] ?? null,
                array_key_exists('custom_percentage', $validated) && $validated['custom_percentage'] !== null
                    ? (float) $validated['custom_percentage']
                    : null,
                $validated['comment'] ?? null,
                (float) $validated['additional_charges'],
            );
        } catch (BusinessConflictException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        $bill = $this->billViewService->forOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Changes applied successfully.',
            'summary' => $this->billViewService->summary($bill),
            'applied' => $this->billViewService->appliedState($bill),
            'discount_id' => $bill->discount_id,
            'custom_percentage' => $bill->other_discount_percentage,
            'comment' => $bill->other_discount_comment,
            'additional_charges' => number_format((float) $bill->additional_charges, 2, '.', ''),
            'paid_amount' => number_format($this->billViewService->paidAmount($bill), 2, '.', ''),
            'remaining_balance' => number_format($this->billViewService->remainingBalance($bill), 2, '.', ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Order $order): array
    {
        $bill = $this->billViewService->forOrder($order);
        $billOrder = $bill->order ?? $order;
        $user = request()->user();
        $canApplyAdjustments = $user ? $this->billViewService->canApplyAdjustments($bill, $user) : false;

        return [
            'bill' => $bill,
            'order' => $billOrder,
            'tableDisplay' => $this->kotViewService->tableDisplay($billOrder),
            'summary' => $this->billViewService->summary($bill),
            'discountOptions' => $this->billViewService->discountOptions(),
            'canApplyAdjustments' => $canApplyAdjustments,
            'adjustmentsApplyUrl' => route('admin.orders.bill.adjustments', $billOrder),
            'printUrl' => route('admin.orders.bill.print', $billOrder),
            'appliedState' => $this->billViewService->appliedState($bill),
            'paidAmount' => $this->billViewService->paidAmount($bill),
            'remainingBalance' => $this->billViewService->remainingBalance($bill),
        ];
    }
}
