<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderItemStatus;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class OrderItemStatusApiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_request_receives_401(): void
    {
        $this->patchJson('/api/v1/order-items/status', [])->assertUnauthorized();
        $this->getJson('/api/v1/tables/1/active-order/items')->assertUnauthorized();
    }

    public function test_user_without_item_status_permission_receives_403(): void
    {
        Sanctum::actingAs($this->userWithRole('counter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [1],
            'status' => 'served',
        ])->assertForbidden();
    }

    public function test_waiter_can_change_one_item_to_preparing(): void
    {
        [$table, $items] = $this->placeOrderWithItems(1);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id],
            'status' => 'preparing',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $items[0]->id)
            ->assertJsonPath('data.items.0.status', 'preparing');

        $this->assertSame(OrderItemStatus::Preparing, $items[0]->fresh()->status);
    }

    public function test_captain_can_bulk_update_items_in_one_request(): void
    {
        [$table, $items] = $this->placeOrderWithItems(3);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id, $items[1]->id],
            'status' => 'served',
        ])
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'served')
            ->assertJsonPath('data.items.1.status', 'served');

        $this->assertSame(OrderItemStatus::Served, $items[0]->fresh()->status);
        $this->assertSame(OrderItemStatus::Served, $items[1]->fresh()->status);
        $this->assertSame(OrderItemStatus::Pending, $items[2]->fresh()->status);
    }

    public function test_preparing_can_change_to_served(): void
    {
        [, $items] = $this->placeOrderWithItems(1);
        $items[0]->update(['status' => OrderItemStatus::Preparing]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id],
            'status' => 'served',
        ])->assertOk();

        $this->assertSame(OrderItemStatus::Served, $items[0]->fresh()->status);
    }

    public function test_eligible_item_can_be_cancelled_and_row_remains(): void
    {
        [, $items] = $this->placeOrderWithItems(1);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id],
            'status' => 'cancelled',
        ])->assertOk();

        $this->assertDatabaseHas('order_items', [
            'id' => $items[0]->id,
            'status' => 'cancelled',
            'quantity' => $items[0]->quantity,
        ]);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_invalid_status_is_rejected(): void
    {
        [, $items] = $this->placeOrderWithItems(1);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id],
            'status' => 'ready',
        ])->assertUnprocessable();
    }

    public function test_empty_item_id_array_is_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [],
            'status' => 'served',
        ])->assertUnprocessable();
    }

    public function test_unknown_item_ids_are_rejected(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [999999],
            'status' => 'served',
        ])->assertUnprocessable();
    }

    public function test_items_from_closed_order_cannot_be_updated(): void
    {
        [, $items] = $this->placeOrderWithItems(1);
        Order::query()->first()->update(['completed_at' => now()]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id],
            'status' => 'served',
        ])->assertConflict();

        $this->assertSame(OrderItemStatus::Pending, $items[0]->fresh()->status);
    }

    public function test_failed_bulk_update_does_not_change_any_rows(): void
    {
        [, $activeItems] = $this->placeOrderWithItems(1);
        [, $closedItems] = $this->placeOrderWithItems(1);
        Order::query()->latest('id')->first()->update(['completed_at' => now()]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$activeItems[0]->id, $closedItems[0]->id],
            'status' => 'served',
        ])->assertConflict();

        $this->assertSame(OrderItemStatus::Pending, $activeItems[0]->fresh()->status);
        $this->assertSame(OrderItemStatus::Pending, $closedItems[0]->fresh()->status);
    }

    public function test_cancelled_item_cannot_be_restored(): void
    {
        [, $items] = $this->placeOrderWithItems(1);
        $items[0]->update(['status' => OrderItemStatus::Cancelled]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->patchJson('/api/v1/order-items/status', [
            'order_item_ids' => [$items[0]->id],
            'status' => 'preparing',
        ])->assertConflict();

        $this->assertSame(OrderItemStatus::Cancelled, $items[0]->fresh()->status);
    }

    public function test_waiter_can_list_active_order_items_for_table(): void
    {
        [$table, $items] = $this->placeOrderWithItems(2);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson("/api/v1/tables/{$table->id}/active-order/items")
            ->assertOk()
            ->assertJsonPath('data.order.id', Order::query()->first()->id)
            ->assertJsonPath('data.items.0.id', $items[0]->id)
            ->assertJsonPath('data.items.1.id', $items[1]->id);
    }

    /**
     * @return array{0: Table, 1: list<OrderItem>}
     */
    private function placeOrderWithItems(int $count): array
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $customer = Customer::factory()->create();
        $dineType = DineType::query()->firstOrCreate(
            ['slug' => 'dine-in'],
            ['name' => 'Dine In', 'sort_order' => 1, 'is_active' => true],
        );

        $payloadItems = [];
        for ($i = 0; $i < $count; $i++) {
            $menuItem = MenuItem::factory()->create(['price' => 50 + $i]);
            $payloadItems[] = [
                'menu_item_id' => $menuItem->id,
                'quantity' => 1 + $i,
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
        $items = $order->items()->orderBy('id')->get()->all();

        return [$table, $items];
    }
}
