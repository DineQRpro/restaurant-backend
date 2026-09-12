<?php

namespace App\Services\Billing;

use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class BillCalculationService
{
    /**
     * @return array{
     *     items: Collection<int, OrderItem>,
     *     subtotal: float,
     *     discount_amount: float,
     *     taxable_amount: float,
     *     cgst_percent: float,
     *     sgst_percent: float,
     *     cgst_amount: float,
     *     sgst_amount: float,
     *     tax_amount: float,
     *     round_off: float,
     *     grand_total: float
     * }
     */
    public function calculateForOrder(Order $order): array
    {
        $order->loadMissing('items.menuItem');

        $this->assertEligible($order);

        $items = $order->items
            ->filter(fn (OrderItem $item): bool => $item->status !== OrderItemStatus::Cancelled)
            ->values();

        $subtotal = $items->sum(
            fn (OrderItem $item): float => ((float) $item->unit_price * $item->quantity) + (float) $item->packing_fee
        );

        $subtotal = $this->roundMoney($subtotal);
        $discountAmount = 0.0;
        $taxable = $this->roundMoney($subtotal - $discountAmount);
        $tax = $this->taxBreakdown($taxable);
        $roundOff = 0.0;
        $grandTotal = $this->roundMoney($taxable + $tax['tax_amount'] + $roundOff);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxable,
            ...$tax,
            'round_off' => $roundOff,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @return array{
     *     cgst_percent: float,
     *     sgst_percent: float,
     *     cgst_amount: float,
     *     sgst_amount: float,
     *     tax_amount: float
     * }
     */
    public function taxBreakdown(float $taxable): array
    {
        $cgstPercent = (float) config('billing.cgst_percent');
        $sgstPercent = (float) config('billing.sgst_percent');
        $cgstAmount = $this->roundMoney(($taxable * $cgstPercent) / 100);
        $sgstAmount = $this->roundMoney(($taxable * $sgstPercent) / 100);

        return [
            'cgst_percent' => $cgstPercent,
            'sgst_percent' => $sgstPercent,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'tax_amount' => $this->roundMoney($cgstAmount + $sgstAmount),
        ];
    }

    public function assertEligible(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            throw new BusinessConflictException('Checkout requires at least one order item.');
        }

        $billable = $order->items->filter(
            fn (OrderItem $item): bool => $item->status !== OrderItemStatus::Cancelled
        );

        if ($billable->isEmpty()) {
            throw new BusinessConflictException('Checkout requires at least one non-cancelled item.');
        }

        $unserved = $billable->first(
            fn (OrderItem $item): bool => $item->status !== OrderItemStatus::Served
        );

        if ($unserved) {
            throw new BusinessConflictException('All non-cancelled items must be served before checkout.');
        }
    }

    private function roundMoney(float $value): float
    {
        return round($value + 1e-8, 2);
    }
}
