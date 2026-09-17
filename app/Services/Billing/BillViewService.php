<?php

namespace App\Services\Billing;

use App\Models\Bill;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BillViewService
{
    public function forOrder(Order $order): Bill
    {
        $bill = Bill::query()
            ->where('order_id', $order->id)
            ->with([
                'items' => fn ($query) => $query->orderBy('id'),
                'payments.paymentMethod:id,name,slug',
                'order.table:id,number',
                'order.dineType:id,name,slug',
                'order.customer:id,name',
            ])
            ->latest('id')
            ->first();

        if (! $bill) {
            throw (new ModelNotFoundException)->setModel(Bill::class);
        }

        return $bill;
    }

    public function paidAmount(Bill $bill): float
    {
        return round((float) $bill->payments->sum('amount'), 2);
    }

    public function remainingBalance(Bill $bill): float
    {
        return round(max(0, (float) $bill->grand_total - $this->paidAmount($bill)), 2);
    }
}
