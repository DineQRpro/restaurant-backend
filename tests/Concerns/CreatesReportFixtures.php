<?php

namespace Tests\Concerns;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Models\Bill;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use Database\Seeders\PaymentMethodSeeder;

trait CreatesReportFixtures
{
    use CreatesRestaurantOrders;

    protected function seedPaymentMethods(): void
    {
        $this->seed(PaymentMethodSeeder::class);
    }

    protected function paymentMethod(string $slug): PaymentMethod
    {
        return PaymentMethod::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function addOrderItem(Order $order, array $attributes = []): OrderItem
    {
        $menuItem = $attributes['menuItem'] ?? MenuItem::factory()->create([
            'name' => $attributes['name'] ?? 'Butter Naan',
            'price' => $attributes['unit_price'] ?? 80,
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => $attributes['quantity'] ?? 1,
            'unit_price' => $attributes['unit_price'] ?? $menuItem->price,
            'packing_fee' => $attributes['packing_fee'] ?? 0,
            'status' => $attributes['status'] ?? OrderItemStatus::Served,
        ]);
    }

    /**
     * @param  array<string, mixed>  $billOverrides
     * @param  array<string, mixed>  $orderOverrides
     * @return array{0: Order, 1: Bill}
     */
    protected function createSettledBill(array $orderOverrides = [], array $billOverrides = [], ?string $methodSlug = PaymentMethod::SLUG_CASH): array
    {
        [$order, $bill] = $this->createCheckoutBill($orderOverrides, array_merge([
            'status' => BillStatus::Settled,
            'settled_at' => now(),
            'issued_at' => now(),
        ], $billOverrides));

        if ($methodSlug !== null) {
            $this->recordPayment($bill, $this->paymentMethod($methodSlug), (float) $bill->grand_total);
        }

        return [$order, $bill->fresh()];
    }
}
