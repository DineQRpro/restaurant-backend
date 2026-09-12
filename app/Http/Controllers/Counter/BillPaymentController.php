<?php

namespace App\Http\Controllers\Counter;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\StoreBillPaymentRequest;
use App\Http\Requests\Counter\Billing\StoreSplitBillPaymentRequest;
use App\Models\Bill;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;

class BillPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function store(StoreBillPaymentRequest $request, Bill $bill): RedirectResponse
    {
        try {
            $this->paymentService->record($bill, $request->validated(), $request->user());
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('counter.dashboard')
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('counter.dashboard')
            ->with('success', 'Payment recorded successfully.');
    }

    public function storeSplit(StoreSplitBillPaymentRequest $request, Bill $bill): RedirectResponse
    {
        try {
            $this->paymentService->recordSplit(
                $bill,
                $request->validated('payments'),
                $request->user(),
            );
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('counter.dashboard')
                ->with('error', $e->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('counter.dashboard')
            ->with('success', 'Split payment recorded successfully.');
    }
}
