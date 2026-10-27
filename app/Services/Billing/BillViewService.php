<?php

namespace App\Services\Billing;

use App\Enums\BillStatus;
use App\Enums\DiscountType;
use App\Models\Bill;
use App\Models\Discount;
use App\Models\Order;
use App\Models\User;
use App\Services\Discount\DiscountService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class BillViewService
{
    public function __construct(
        private readonly BillCalculationService $calculator,
        private readonly DiscountService $discountService,
    ) {}

    public function forOrder(Order $order): Bill
    {
        $bill = Bill::query()
            ->where('order_id', $order->id)
            ->with([
                'items' => fn ($query) => $query->orderBy('id'),
                'payments.paymentMethod:id,name,slug',
                'discount',
                'order.table:id,number',
                'order.dineType:id,name,slug',
                'order.customer:id,name,phone',
                'order.waiter:id,name',
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

    public function canApplyAdjustments(Bill $bill, User $user): bool
    {
        return $user->hasPermission('bills.discount')
            && $bill->historical_pay_method === null
            && $bill->status === BillStatus::Open
            && $bill->payments->isEmpty();
    }

    /**
     * Last successfully applied editable values for dirty-state comparison.
     *
     * @return array{discount_id: string, custom_percentage: string, comment: string, additional_charges: string}
     */
    public function appliedState(Bill $bill): array
    {
        $isCustom = $bill->discount?->type === DiscountType::Custom;

        return [
            'discount_id' => $bill->discount_id ? (string) $bill->discount_id : '',
            'custom_percentage' => $isCustom
                ? number_format((float) $bill->other_discount_percentage, 2, '.', '')
                : '',
            'comment' => $isCustom ? (string) ($bill->other_discount_comment ?? '') : '',
            'additional_charges' => number_format((float) $bill->additional_charges, 2, '.', ''),
        ];
    }

    /**
     * @return Collection<int, Discount>
     */
    public function discountOptions(): Collection
    {
        return $this->discountService->orderLevelOptions();
    }

    /**
     * Presentation values for the View Bill table footer.
     *
     * Grand Total is pre-round. Payable Amount is persisted grand_total.
     *
     * @return array{
     *     total_summary: string,
     *     discount_amount: string,
     *     taxable_amount: string,
     *     cgst_label: string,
     *     sgst_label: string,
     *     cgst_amount: string,
     *     sgst_amount: string,
     *     gst_total: string,
     *     additional_charges: string,
     *     grand_total: string,
     *     round_off: string,
     *     payable_amount: string
     * }
     */
    public function summary(Bill $bill): array
    {
        $tax = $this->calculator->taxBreakdown((float) $bill->taxable_amount);
        $additionalCharges = (float) $bill->additional_charges;
        $preRound = round((float) $bill->taxable_amount + (float) $bill->tax_amount + $additionalCharges, 2);

        return [
            'total_summary' => $this->decimal((float) $bill->subtotal),
            'discount_amount' => $this->discountDisplay((float) $bill->discount_amount),
            'taxable_amount' => $this->decimal((float) $bill->taxable_amount),
            'cgst_label' => 'CGST ('.$this->percentLabel($tax['cgst_percent']).'%)',
            'sgst_label' => 'SGST ('.$this->percentLabel($tax['sgst_percent']).'%)',
            'cgst_amount' => $this->signed((float) $tax['cgst_amount']),
            'sgst_amount' => $this->signed((float) $tax['sgst_amount']),
            'gst_total' => $this->decimal((float) $bill->tax_amount),
            'additional_charges' => $this->decimal($additionalCharges),
            'grand_total' => $this->decimal($preRound),
            'round_off' => $this->signed((float) $bill->round_off),
            'payable_amount' => $this->decimal((float) $bill->grand_total),
        ];
    }

    public function decimal(float $amount): string
    {
        return number_format(round($amount, 2), 2);
    }

    public function signed(float $amount): string
    {
        $rounded = round($amount, 2);

        if (abs($rounded) < 0.005) {
            return $this->decimal(0);
        }

        $sign = $rounded > 0 ? '+' : '-';

        return $sign.$this->decimal(abs($rounded));
    }

    public function percentLabel(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Standalone thermal print payload from persisted Bill / BillItem state.
     *
     * @return array<string, mixed>
     */
    public function printViewData(Order $order): array
    {
        $bill = $this->forOrder($order);
        $billOrder = $bill->order ?? $order;
        $summary = $this->summary($bill);
        $summary['discount_amount'] = $this->decimal((float) $bill->discount_amount);

        return [
            'bill' => $bill,
            'order' => $billOrder,
            'tableDisplay' => $billOrder->tableDisplayName(),
            'summary' => $summary,
            'restaurant' => $this->restaurantPrintHeader(),
            'isTakeaway' => $billOrder->dineType && ! $billOrder->dineType->requiresTable(),
            'maskedPhone' => $this->maskPhone($billOrder->customer?->phone),
            'discountLabel' => $this->printDiscountLabel($bill),
            'issuedAt' => $this->printIssuedAt($bill, $billOrder),
        ];
    }

    /**
     * @return array{name: string, address: string, phone: string, gstin: string}
     */
    public function restaurantPrintHeader(): array
    {
        $print = config('restaurant.print', []);

        return [
            'name' => trim((string) ($print['name'] ?? '')),
            'address' => trim((string) ($print['address'] ?? '')),
            'phone' => trim((string) ($print['phone'] ?? '')),
            'gstin' => trim((string) ($print['gstin'] ?? '')),
        ];
    }

    public function maskPhone(?string $phone): string
    {
        $digits = trim((string) $phone);
        if ($digits === '') {
            return '';
        }

        $length = strlen($digits);
        if ($length > 4) {
            return str_repeat('*', $length - 4).substr($digits, -4);
        }

        return $digits;
    }

    private function printDiscountLabel(Bill $bill): string
    {
        if ($bill->discount?->type === DiscountType::Custom && filled($bill->other_discount_comment)) {
            return (string) $bill->other_discount_comment;
        }

        return $bill->discount?->name ?: 'Discount';
    }

    private function printIssuedAt(Bill $bill, Order $order): string
    {
        $at = $bill->issued_at ?? $order->created_at;
        if (! $at) {
            return '';
        }

        return $at->timezone(config('app.timezone'))->format('d/m/Y, h:i A');
    }

    private function discountDisplay(float $amount): string
    {
        $rounded = round($amount, 2);

        if (abs($rounded) < 0.005) {
            return $this->decimal(0);
        }

        return '-'.$this->decimal(abs($rounded));
    }
}
