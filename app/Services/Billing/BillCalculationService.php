<?php

namespace App\Services\Billing;

use App\Enums\DiscountAppliesTo;
use App\Enums\DiscountType;
use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class BillCalculationService
{
    /**
     * @return array{
     *     items: Collection<int, OrderItem>,
     *     subtotal: float,
     *     discount_percent: float,
     *     discount_amount: float,
     *     taxable_amount: float,
     *     cgst_percent: float,
     *     sgst_percent: float,
     *     cgst_amount: float,
     *     sgst_amount: float,
     *     tax_amount: float,
     *     additional_charges: float,
     *     pre_round_total: float,
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

        $subtotalCents = 0;
        foreach ($items as $item) {
            $lineCents = $this->toCents((float) $item->unit_price) * $item->quantity;
            $lineCents += $this->toCents((float) $item->packing_fee);
            $subtotalCents += $lineCents;
        }

        return [
            'items' => $items,
            ...$this->calculateFromSubtotalCents($subtotalCents, 0.0, 0),
        ];
    }

    /**
     * Authoritative bill totals from a persisted subtotal and a discount percentage.
     *
     * grand_total is the payable amount (nearest rupee). pre_round_total is Grand Total on View Bill.
     *
     * @return array{
     *     subtotal: float,
     *     discount_percent: float,
     *     discount_amount: float,
     *     taxable_amount: float,
     *     cgst_percent: float,
     *     sgst_percent: float,
     *     cgst_amount: float,
     *     sgst_amount: float,
     *     tax_amount: float,
     *     additional_charges: float,
     *     pre_round_total: float,
     *     round_off: float,
     *     grand_total: float
     * }
     */
    public function calculateFromSubtotal(float $subtotal, float $discountPercent = 0.0, float $additionalCharges = 0.0): array
    {
        return $this->calculateFromSubtotalCents(
            $this->toCents($subtotal),
            $discountPercent,
            $this->toCents($additionalCharges),
        );
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
        return $this->taxBreakdownFromCents($this->toCents($taxable));
    }

    /**
     * Resolve the percentage to apply from a discount master row.
     */
    public function discountPercent(Discount $discount, ?float $customPercent): float
    {
        if ($discount->applies_to !== DiscountAppliesTo::Order) {
            throw new BusinessConflictException('This discount cannot be applied to the whole bill.');
        }

        if (! $discount->is_active) {
            throw new BusinessConflictException('This discount is not active.');
        }

        if ($discount->type === DiscountType::Custom) {
            if ($customPercent === null) {
                throw new BusinessConflictException('Enter a discount percentage.');
            }

            $min = (int) $discount->min_level;
            $max = (int) $discount->max_level;

            if ($min > 0 && $customPercent < $min) {
                throw new BusinessConflictException("Minimum discount should be {$min} %");
            }

            if ($max > 0 && $customPercent > $max) {
                throw new BusinessConflictException("Maximum discount should be {$max} %");
            }

            if ($customPercent < 0) {
                throw new BusinessConflictException('Discount percentage cannot be negative.');
            }

            return $customPercent;
        }

        return (float) $discount->value;
    }

    public function isEligible(Order $order): bool
    {
        $order->loadMissing('items');

        if ($order->items->isEmpty()) {
            return false;
        }

        $billable = $order->items->filter(
            fn (OrderItem $item): bool => $item->status !== OrderItemStatus::Cancelled
        );

        if ($billable->isEmpty()) {
            return false;
        }

        return $billable->every(
            fn (OrderItem $item): bool => $item->status === OrderItemStatus::Served
        );
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

        if (! $this->isEligible($order)) {
            throw new BusinessConflictException('All non-cancelled items must be served before checkout.');
        }
    }

    /**
     * Core PHP: if fractional rupees >= 0.50 ceil, otherwise floor.
     * round_off is signed: payable - pre_round.
     *
     * @return array{pre_round_cents: int, payable_cents: int, round_off_cents: int}
     */
    public function roundToRupeeCents(int $preRoundCents): array
    {
        $paise = $preRoundCents % 100;
        $payableCents = $paise >= 50
            ? $preRoundCents + (100 - $paise)
            : $preRoundCents - $paise;

        return [
            'pre_round_cents' => $preRoundCents,
            'payable_cents' => $payableCents,
            'round_off_cents' => $payableCents - $preRoundCents,
        ];
    }

    /**
     * @return array{
     *     subtotal: float,
     *     discount_percent: float,
     *     discount_amount: float,
     *     taxable_amount: float,
     *     cgst_percent: float,
     *     sgst_percent: float,
     *     cgst_amount: float,
     *     sgst_amount: float,
     *     tax_amount: float,
     *     additional_charges: float,
     *     pre_round_total: float,
     *     round_off: float,
     *     grand_total: float
     * }
     */
    private function calculateFromSubtotalCents(int $subtotalCents, float $discountPercent, int $additionalCents): array
    {
        $discountCents = $this->percentCents($subtotalCents, $discountPercent);
        if ($discountCents > $subtotalCents) {
            $discountCents = $subtotalCents;
        }

        $taxableCents = $subtotalCents - $discountCents;
        $tax = $this->taxBreakdownFromCents($taxableCents);
        $preRoundCents = $taxableCents + $this->toCents($tax['tax_amount']) + $additionalCents;
        $rounding = $this->roundToRupeeCents($preRoundCents);

        return [
            'subtotal' => $this->fromCents($subtotalCents),
            'discount_percent' => $discountPercent,
            'discount_amount' => $this->fromCents($discountCents),
            'taxable_amount' => $this->fromCents($taxableCents),
            ...$tax,
            'additional_charges' => $this->fromCents($additionalCents),
            'pre_round_total' => $this->fromCents($rounding['pre_round_cents']),
            'round_off' => $this->fromCents($rounding['round_off_cents']),
            'grand_total' => $this->fromCents($rounding['payable_cents']),
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
    private function taxBreakdownFromCents(int $taxableCents): array
    {
        $cgstPercent = (float) config('billing.cgst_percent');
        $sgstPercent = (float) config('billing.sgst_percent');
        $cgstCents = $this->percentCents($taxableCents, $cgstPercent);
        $sgstCents = $this->percentCents($taxableCents, $sgstPercent);

        return [
            'cgst_percent' => $cgstPercent,
            'sgst_percent' => $sgstPercent,
            'cgst_amount' => $this->fromCents($cgstCents),
            'sgst_amount' => $this->fromCents($sgstCents),
            'tax_amount' => $this->fromCents($cgstCents + $sgstCents),
        ];
    }

    private function percentCents(int $baseCents, float $percent): int
    {
        return (int) round($baseCents * $percent / 100);
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
