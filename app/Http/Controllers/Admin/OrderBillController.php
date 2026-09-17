<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\BillViewService;
use App\Services\Order\KotViewService;
use Illuminate\View\View;

class OrderBillController extends Controller
{
    public function __construct(
        private readonly BillViewService $billViewService,
        private readonly KotViewService $kotViewService,
    ) {}

    public function show(Order $order): View
    {
        $bill = $this->billViewService->forOrder($order);
        $billOrder = $bill->order ?? $order;

        return view('shared.billing.view-bill', [
            'bill' => $bill,
            'order' => $billOrder,
            'tableDisplay' => $this->kotViewService->tableDisplay($billOrder),
            'paidAmount' => $this->billViewService->paidAmount($bill),
            'remainingBalance' => $this->billViewService->remainingBalance($bill),
        ]);
    }
}
