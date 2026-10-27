<?php

namespace App\Services\Billing;

use App\Enums\BillStatus;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Bill;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Services\Order\OrderService;
use App\Services\Table\TableOccupancyService;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly BillCalculationService $calculator,
        private readonly BillNumberGenerator $billNumberGenerator,
        private readonly OrderService $orderService,
        private readonly TableOccupancyService $occupancy,
    ) {}

    /**
     * @return array{order: Order, table: ?Table, calculation: array<string, mixed>}
     */
    public function previewForTable(Table $table): array
    {
        $order = $this->requireActiveOrderForTable($table, lock: false);
        $calculation = $this->calculator->calculateForOrder($order);

        return [
            'order' => $order->loadMissing(['table', 'customer']),
            'table' => $order->table,
            'calculation' => $calculation,
        ];
    }

    /**
     * @return array{order: Order, table: ?Table, calculation: array<string, mixed>}
     */
    public function previewForOrder(Order $order): array
    {
        $order = $order->loadMissing(['items.menuItem', 'table', 'customer']);
        $table = $order->table;
        $this->assertCheckoutable($order, $table);
        $calculation = $this->calculator->calculateForOrder($order);

        return [
            'order' => $order,
            'table' => $table,
            'calculation' => $calculation,
        ];
    }

    public function checkout(Order $order): Bill
    {
        return DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load(['items.menuItem', 'table', 'customer']);

            $table = $order->table;
            if ($table) {
                Table::query()->whereKey($table->id)->lockForUpdate()->first();
                $table->refresh();
                Order::query()
                    ->unreleased()
                    ->where('table_id', $table->id)
                    ->lockForUpdate()
                    ->get();
            }

            $existing = $this->openBillForOrder($order);
            if ($existing) {
                return $existing->load(['items', 'order.table', 'order.customer']);
            }

            $this->assertCheckoutable($order, $table);

            $calculation = $this->calculator->calculateForOrder($order);

            $bill = Bill::query()->create([
                'bill_number' => $this->billNumberGenerator->generate(),
                'order_id' => $order->id,
                'subtotal' => $calculation['subtotal'],
                'discount_id' => null,
                'other_discount_percentage' => 0,
                'other_discount_comment' => null,
                'discount_amount' => $calculation['discount_amount'],
                'taxable_amount' => $calculation['taxable_amount'],
                'tax_amount' => $calculation['tax_amount'],
                'additional_charges' => $calculation['additional_charges'],
                'round_off' => $calculation['round_off'],
                'grand_total' => $calculation['grand_total'],
                'status' => BillStatus::Open,
                'issued_at' => now(),
                'settled_at' => null,
            ]);

            foreach ($calculation['items'] as $item) {
                /** @var OrderItem $item */
                $bill->items()->create([
                    'order_item_id' => $item->id,
                    'item_name' => $item->menuItem?->name ?? 'Item',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'packing_fee' => $item->packing_fee,
                ]);
            }

            $order->update([
                'status' => OrderStatus::Checkout,
                'seating_cleaned_at' => null,
                'cleaning_started_at' => now(),
            ]);

            if ($table) {
                $table->unsetRelation('unreleasedOrders');
                $this->occupancy->forgetSatelliteIndex();
                $this->occupancy->applyOperationalStatusForOrder($order);
            }

            return $bill->fresh()->load(['items', 'order.table.unreleasedOrders', 'order.customer']);
        });
    }

    /**
     * Apply discount and additional charges on an open unpaid bill.
     *
     * The client may send identifiers and entered amounts only. Money is calculated here.
     */
    public function applyAdjustments(
        Bill $bill,
        ?int $discountId,
        ?float $customPercent,
        ?string $comment,
        float $additionalCharges,
    ): Bill {
        return DB::transaction(function () use ($bill, $discountId, $customPercent, $comment, $additionalCharges) {
            /** @var Bill $bill */
            $bill = Bill::query()->whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $this->assertAdjustmentsMutable($bill);

            $percent = 0.0;
            $otherPercent = 0.0;
            $otherComment = null;
            $resolvedId = null;

            if ($discountId) {
                $discount = Discount::query()->whereKey($discountId)->lockForUpdate()->first();
                if (! $discount) {
                    throw new BusinessConflictException('Invalid discount');
                }

                $percent = $this->calculator->discountPercent($discount, $customPercent);
                $resolvedId = $discount->id;

                if ($discount->type === DiscountType::Custom) {
                    $otherPercent = $percent;
                    $otherComment = $comment !== null && $comment !== '' ? $comment : null;
                }
            }

            $calculation = $this->calculator->calculateFromSubtotal(
                (float) $bill->subtotal,
                $percent,
                $additionalCharges,
            );

            $bill->update([
                'discount_id' => $resolvedId,
                'other_discount_percentage' => $otherPercent,
                'other_discount_comment' => $otherComment,
                'discount_amount' => $calculation['discount_amount'],
                'taxable_amount' => $calculation['taxable_amount'],
                'tax_amount' => $calculation['tax_amount'],
                'additional_charges' => $calculation['additional_charges'],
                'round_off' => $calculation['round_off'],
                'grand_total' => $calculation['grand_total'],
            ]);

            return $bill->fresh([
                'items',
                'payments.paymentMethod',
                'discount',
                'order.table',
                'order.dineType',
                'order.customer',
            ]);
        });
    }

    public function openBillForOrder(Order $order): ?Bill
    {
        return Bill::query()
            ->where('order_id', $order->id)
            ->where('status', BillStatus::Open)
            ->latest('id')
            ->first();
    }

    private function requireActiveOrderForTable(Table $table, bool $lock): Order
    {
        if (! $table->is_active) {
            throw new BusinessConflictException('The selected table is inactive.');
        }

        if ($table->status !== TableOperationalStatus::Occupied) {
            throw new BusinessConflictException('Checkout is only available for occupied tables.');
        }

        $activeCount = Order::query()->active()->where('table_id', $table->id)->count();
        if ($activeCount > 1) {
            throw new BusinessConflictException('Multiple parties are seated at this table. Checkout a specific partition.');
        }

        $order = $this->orderService->findActiveOrderForTable($table->id, $lock);

        if (! $order) {
            throw new BusinessConflictException('This table does not have an active order to checkout.');
        }

        return $order->load(['items.menuItem', 'table', 'customer']);
    }

    private function assertCheckoutable(Order $order, ?Table $table): void
    {
        if (! $order->isActive()) {
            throw new BusinessConflictException('This order is not available for checkout.');
        }

        if ($table && ! $table->is_active) {
            throw new BusinessConflictException('The selected table is inactive.');
        }

        if ($table && $table->status !== TableOperationalStatus::Occupied) {
            throw new BusinessConflictException('Checkout is only available for occupied tables.');
        }

        $this->calculator->assertEligible($order);
    }

    private function assertAdjustmentsMutable(Bill $bill): void
    {
        if ($bill->historical_pay_method !== null || $bill->status !== BillStatus::Open) {
            throw new BusinessConflictException('This bill can no longer be adjusted.');
        }

        if ($bill->payments()->exists()) {
            throw new BusinessConflictException('This bill already has payments and cannot change financial totals.');
        }
    }
}
