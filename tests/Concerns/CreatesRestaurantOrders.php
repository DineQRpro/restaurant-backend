<?php

namespace Tests\Concerns;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Table;

trait CreatesRestaurantOrders
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPlacedOrder(array $overrides = []): Order
    {
        $waiter = $overrides['waiter'] ?? $this->userWithRole('waiter');
        unset($overrides['waiter']);

        $customer = $overrides['customer'] ?? Customer::factory()->create();
        unset($overrides['customer']);

        $dineType = $overrides['dineType'] ?? DineType::query()->firstOrCreate(
            ['slug' => 'dine-in'],
            ['name' => 'Dine In', 'sort_order' => 1, 'is_active' => true],
        );
        unset($overrides['dineType']);

        $table = array_key_exists('table', $overrides)
            ? $overrides['table']
            : Table::factory()->occupied()->create();
        unset($overrides['table']);

        return Order::query()->create(array_merge([
            'order_number' => 'ORD-'.fake()->unique()->numerify('####'),
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table?->id,
            'waiter_id' => $waiter->id,
            'pax' => 2,
            'status' => OrderStatus::Placed,
            'started_at' => now()->subMinutes(20),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $orderOverrides
     * @param  array<string, mixed>  $billOverrides
     * @return array{0: Order, 1: Bill}
     */
    protected function createCheckoutBill(array $orderOverrides = [], array $billOverrides = []): array
    {
        $table = $orderOverrides['table'] ?? Table::factory()->cleaning()->create();
        $orderOverrides['table'] = $table;

        $order = $this->createPlacedOrder(array_merge([
            'status' => OrderStatus::Checkout,
        ], $orderOverrides));

        if ($table->status !== TableOperationalStatus::Cleaning) {
            $table->update(['status' => TableOperationalStatus::Cleaning]);
        }

        $bill = Bill::query()->create(array_merge([
            'bill_number' => 'BILL-'.fake()->unique()->numerify('####'),
            'order_id' => $order->id,
            'subtotal' => 1500,
            'grand_total' => 1500,
            'status' => BillStatus::Open,
            'issued_at' => now(),
        ], $billOverrides));

        return [$order->fresh(['customer', 'table', 'dineType']), $bill->fresh()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function recordPayment(Bill $bill, PaymentMethod $method, float $amount, array $overrides = []): BillPayment
    {
        return BillPayment::query()->create(array_merge([
            'bill_id' => $bill->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'reference_number' => null,
            'notes' => null,
            'paid_at' => now(),
            'captain_id' => null,
        ], $overrides));
    }
}
