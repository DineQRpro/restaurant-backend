<?php

namespace App\Services\Billing;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\Bill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly BillCalculationService $calculator,
        private readonly BillNumberGenerator $billNumberGenerator,
        private readonly OrderService $orderService,
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
            ]);

            if ($table) {
                $table->update([
                    'status' => TableOperationalStatus::Cleaning,
                ]);
            }

            return $bill->fresh()->load(['items', 'order.table', 'order.customer']);
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
}
