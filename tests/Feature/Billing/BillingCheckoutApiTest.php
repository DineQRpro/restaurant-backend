<?php

namespace Tests\Feature\Billing;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Table;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillNumberGenerator;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class BillingCheckoutApiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_request_receives_401(): void
    {
        $this->getJson('/api/v1/tables/1/checkout-preview')->assertUnauthorized();
        $this->postJson('/api/v1/orders/1/checkout')->assertUnauthorized();
    }

    public function test_user_without_billing_permission_receives_403(): void
    {
        [$order] = $this->readyOrder();

        $permission = Permission::query()->where('slug', 'bills.create')->firstOrFail();
        Role::query()->where('slug', 'waiter')->firstOrFail()->permissions()->detach($permission->id);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson("/api/v1/tables/{$order->table_id}/checkout-preview")->assertForbidden();
        $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertForbidden();
    }

    public function test_waiter_can_checkout_eligible_order(): void
    {
        [$order] = $this->readyOrder();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson("/api/v1/tables/{$order->table_id}/checkout-preview")->assertOk();

        $this->postJson("/api/v1/orders/{$order->id}/checkout")
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.table.status', 3)
            ->assertJsonPath('data.order.status', 'checkout');

        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
    }

    public function test_captain_can_checkout_eligible_order(): void
    {
        [$order] = $this->readyOrder();

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->postJson("/api/v1/orders/{$order->id}/checkout")
            ->assertOk()
            ->assertJsonPath('data.table.status', 3)
            ->assertJsonPath('data.order.status', 'checkout');

        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
    }

    public function test_captain_can_preview_checkout(): void
    {
        [$order, $items] = $this->readyOrder();
        $items[0]->menuItem->update(['price' => 999]);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->getJson("/api/v1/tables/{$order->table_id}/checkout-preview")
            ->assertOk()
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.subtotal', '120.00')
            ->assertJsonPath('data.gst.cgst_percent', '2.50')
            ->assertJsonPath('data.gst.sgst_percent', '2.50')
            ->assertJsonPath('data.gst.cgst_amount', '3.00')
            ->assertJsonPath('data.gst.sgst_amount', '3.00')
            ->assertJsonPath('data.tax_amount', '6.00')
            ->assertJsonPath('data.grand_total', '126.00');
    }

    public function test_checkout_blocked_when_item_is_pending(): void
    {
        [$order] = $this->placeOrderWithItems([['price' => 100, 'qty' => 1]]);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertConflict();
        $this->assertDatabaseCount('bills', 0);
        $this->assertSame(TableOperationalStatus::Occupied, $order->table->fresh()->status);
    }

    public function test_checkout_blocked_when_item_is_preparing(): void
    {
        [$order, $items] = $this->placeOrderWithItems([['price' => 100, 'qty' => 1]]);
        $items[0]->update(['status' => OrderItemStatus::Preparing]);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertConflict();
        $this->assertDatabaseCount('bills', 0);
        $this->assertSame(TableOperationalStatus::Occupied, $order->table->fresh()->status);
    }

    public function test_checkout_allowed_when_all_non_cancelled_items_are_served(): void
    {
        [$order, $items] = $this->readyOrder([
            ['price' => 100, 'qty' => 1, 'name' => 'Burger'],
            ['price' => 40, 'qty' => 1, 'name' => 'Soup', 'status' => OrderItemStatus::Cancelled],
        ]);

        Sanctum::actingAs($this->userWithRole('captain'));

        $response = $this->postJson("/api/v1/orders/{$order->id}/checkout");

        $response->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.grand_total', '105.00')
            ->assertJsonPath('data.table.status', 3)
            ->assertJsonPath('data.order.status', 'checkout')
            ->assertJsonPath('data.items.0.item_name', 'Burger');

        $this->assertDatabaseCount('bills', 1);
        $this->assertDatabaseCount('bill_items', 1);
        $this->assertDatabaseMissing('bill_items', ['order_item_id' => $items[1]->id]);
        $this->assertNull(Bill::query()->first()->settled_at);
        $this->assertSame(BillStatus::Open, Bill::query()->first()->status);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
        $this->assertSame(OrderStatus::Checkout, $order->fresh()->status);
        $this->assertDatabaseHas('order_items', ['id' => $items[1]->id, 'status' => 'cancelled']);
    }

    public function test_subtotal_uses_historical_unit_price(): void
    {
        [$order, $items] = $this->readyOrder([['price' => 80, 'qty' => 2, 'name' => 'Tea']]);
        $items[0]->update(['unit_price' => 50]);
        $items[0]->menuItem->update(['price' => 999, 'name' => 'New Tea Name']);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->postJson("/api/v1/orders/{$order->id}/checkout")
            ->assertOk()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.items.0.unit_price', '50.00')
            ->assertJsonPath('data.items.0.item_name', 'New Tea Name');
    }

    public function test_preview_and_checkout_return_identical_totals(): void
    {
        [$order] = $this->readyOrder([['price' => 200, 'qty' => 1, 'packing' => 10]]);

        Sanctum::actingAs($this->userWithRole('captain'));

        $preview = $this->getJson("/api/v1/tables/{$order->table_id}/checkout-preview")->assertOk();
        $checkout = $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertOk();

        foreach (['subtotal', 'taxable_amount', 'tax_amount', 'round_off', 'grand_total'] as $field) {
            $this->assertSame(
                $preview->json("data.{$field}"),
                $checkout->json("data.{$field}"),
            );
        }
        $this->assertSame($preview->json('data.gst'), $checkout->json('data.gst'));
    }

    public function test_duplicate_checkout_does_not_create_second_bill(): void
    {
        [$order] = $this->readyOrder();

        Sanctum::actingAs($this->userWithRole('captain'));

        $first = $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertOk();
        $second = $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(3, $second->json('data.table.status'));
        $this->assertDatabaseCount('bills', 1);
        $this->assertDatabaseCount('bill_items', 1);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
    }

    public function test_checked_out_order_cannot_accept_additional_items(): void
    {
        [$order] = $this->readyOrder();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson("/api/v1/orders/{$order->id}/checkout")->assertOk();

        $menuItem = MenuItem::factory()->create();

        $this->postJson('/api/v1/orders', [
            'customer_id' => $order->customer_id,
            'dine_type_id' => $order->dine_type_id,
            'table_id' => $order->table_id,
            'items' => [
                ['menu_item_id' => $menuItem->id, 'quantity' => 1],
            ],
        ])->assertConflict();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(TableOperationalStatus::Cleaning, $order->table->fresh()->status);
        $this->assertNull(app(OrderService::class)->findActiveOrderForTable($order->table_id, lockForUpdate: false));
    }

    public function test_failed_checkout_does_not_create_partial_bill(): void
    {
        [$order] = $this->readyOrder();

        $this->mock(BillNumberGenerator::class, function ($mock): void {
            $mock->shouldReceive('generate')->andThrow(new RuntimeException('number failed'));
        });

        try {
            app(BillingService::class)->checkout($order);
            $this->fail('Checkout should have failed.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('bills', 0);
        $this->assertDatabaseCount('bill_items', 0);
        $this->assertSame(OrderStatus::Placed, $order->fresh()->status);
        $this->assertSame(TableOperationalStatus::Occupied, $order->table->fresh()->status);
    }

    /**
     * @param  list<array{price: float, qty: int, name?: string, status?: OrderItemStatus, packing?: float}>  $lines
     * @return array{0: Order, 1: list<OrderItem>}
     */
    private function readyOrder(array $lines = []): array
    {
        [$order, $items] = $this->placeOrderWithItems($lines === [] ? [['price' => 120, 'qty' => 1]] : $lines);

        foreach ($items as $index => $item) {
            $desired = $lines[$index]['status'] ?? OrderItemStatus::Served;
            $item->update(['status' => $desired]);
        }

        return [$order, $items];
    }

    /**
     * @param  list<array{price: float, qty: int, name?: string, packing?: float}>  $lines
     * @return array{0: Order, 1: list<OrderItem>}
     */
    private function placeOrderWithItems(array $lines): array
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $customer = Customer::factory()->create();
        $dineType = DineType::query()->firstOrCreate(
            ['slug' => 'dine-in'],
            ['name' => 'Dine In', 'sort_order' => 1, 'is_active' => true],
        );

        $payloadItems = [];
        foreach ($lines as $line) {
            $menuItem = MenuItem::factory()->create([
                'name' => $line['name'] ?? 'Item',
                'price' => $line['price'],
            ]);
            $payloadItems[] = [
                'menu_item_id' => $menuItem->id,
                'quantity' => $line['qty'],
                'packing_fee' => $line['packing'] ?? 0,
            ];
        }

        Sanctum::actingAs($waiter);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table->id,
            'items' => $payloadItems,
        ])->assertCreated();

        $order = Order::query()->where('table_id', $table->id)->latest('id')->firstOrFail();

        return [$order, $order->items()->orderBy('id')->get()->all()];
    }
}
