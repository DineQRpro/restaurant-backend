<?php

namespace Tests\Feature\Orders;

use App\Enums\TableOperationalStatus;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Table;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_request_receives_401(): void
    {
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();
    }

    public function test_user_without_orders_create_receives_403(): void
    {
        Sanctum::actingAs($this->userWithRole('counter'));

        $this->postJson('/api/v1/orders', [])->assertForbidden();
    }

    public function test_waiter_can_place_dine_in_order(): void
    {
        $waiter = $this->userWithRole('waiter');
        $customer = Customer::factory()->create();
        $dineType = DineType::factory()->dineIn()->create();
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create(['price' => 150.00]);

        Sanctum::actingAs($waiter);

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table->id,
            'pax' => 3,
            'waiter_id' => 999,
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'quantity' => 2,
                    'packing_fee' => 0,
                    'special_instructions' => 'Less spicy',
                    'unit_price' => 1.00,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'placed')
            ->assertJsonPath('data.waiter.id', $waiter->id)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.items.0.unit_price', '150.00')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.pax', 3);

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'table_id' => $table->id,
            'waiter_id' => $waiter->id,
            'pax' => 3,
        ]);
        $this->assertDatabaseHas('order_items', [
            'menu_item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => '150.00',
        ]);
        $this->assertSame(TableOperationalStatus::Occupied, $table->fresh()->status);
        $this->assertNotSame(999, Order::query()->first()->waiter_id);
    }

    public function test_occupied_table_with_active_order_can_add_items(): void
    {
        $waiter = $this->userWithRole('waiter');
        $customer = Customer::factory()->create();
        $dineType = DineType::factory()->dineIn()->create();
        $table = Table::factory()->create();
        $firstItem = MenuItem::factory()->create(['price' => 120.00]);
        $extraItem = MenuItem::factory()->create(['price' => 40.00]);

        Sanctum::actingAs($waiter);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table->id,
            'pax' => 2,
            'items' => [
                ['menu_item_id' => $firstItem->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $order = Order::query()->first();
        $this->assertNotNull($order);

        $otherCustomer = Customer::factory()->create();
        $otherDineType = DineType::factory()->takeAway()->create();

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $otherCustomer->id,
            'dine_type_id' => $otherDineType->id,
            'table_id' => $table->id,
            'items' => [
                [
                    'menu_item_id' => $extraItem->id,
                    'quantity' => 2,
                    'unit_price' => 1.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.dine_type.id', $dineType->id)
            ->assertJsonPath('data.waiter.id', $waiter->id)
            ->assertJsonPath('data.items.1.unit_price', '40.00')
            ->assertJsonPath('data.items.1.quantity', 2)
            ->assertJsonPath('data.table.status', 1);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'menu_item_id' => $extraItem->id,
            'quantity' => 2,
            'unit_price' => '40.00',
        ]);
        $this->assertSame($customer->id, $order->fresh()->customer_id);
        $this->assertSame($dineType->id, $order->fresh()->dine_type_id);
        $this->assertSame(TableOperationalStatus::Occupied, $table->fresh()->status);
    }

    public function test_occupied_table_without_active_order_creates_recovery_order(): void
    {
        $table = Table::factory()->occupied()->create();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/orders', $this->validPayload([
            'table_id' => $table->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.table.status', 1);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(TableOperationalStatus::Occupied, $table->fresh()->status);
    }

    public function test_captain_can_add_items_to_occupied_table(): void
    {
        $waiter = $this->userWithRole('waiter');
        $captain = $this->userWithRole('captain');
        $customer = Customer::factory()->create();
        $dineType = DineType::factory()->dineIn()->create();
        $table = Table::factory()->create();
        $firstItem = MenuItem::factory()->create(['price' => 90.00]);
        $extraItem = MenuItem::factory()->create(['price' => 35.00]);

        Sanctum::actingAs($waiter);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table->id,
            'items' => [
                ['menu_item_id' => $firstItem->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $order = Order::query()->first();

        Sanctum::actingAs($captain);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'table_id' => $table->id,
            'items' => [
                ['menu_item_id' => $extraItem->id, 'quantity' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.waiter.id', $waiter->id);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertSame($waiter->id, $order->fresh()->waiter_id);
    }

    public function test_cannot_order_on_cleaning_table(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/orders', $this->validPayload([
            'table_id' => Table::factory()->cleaning()->create()->id,
        ]))->assertConflict();
    }

    public function test_cannot_order_on_inactive_table(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/orders', $this->validPayload([
            'table_id' => Table::factory()->inactive()->create()->id,
        ]))->assertConflict();
    }

    public function test_cannot_order_with_inactive_customer(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/orders', $this->validPayload([
            'customer_id' => Customer::factory()->inactive()->create()->id,
        ]))->assertUnprocessable();
    }

    public function test_cannot_order_unavailable_menu_item(): void
    {
        Sanctum::actingAs($this->userWithRole('waiter'));

        $payload = $this->validPayload();
        $payload['items'][0]['menu_item_id'] = MenuItem::factory()->unavailable()->create()->id;

        $this->postJson('/api/v1/orders', $payload)->assertConflict();
    }

    public function test_failed_order_does_not_mark_table_occupied(): void
    {
        $table = Table::factory()->create();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $payload = $this->validPayload(['table_id' => $table->id]);
        $payload['items'][0]['menu_item_id'] = MenuItem::factory()->unavailable()->create()->id;

        $this->postJson('/api/v1/orders', $payload)->assertConflict();

        $this->assertSame(TableOperationalStatus::Available, $table->fresh()->status);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_failed_additional_items_do_not_create_partial_rows(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $availableItem = MenuItem::factory()->create();

        Sanctum::actingAs($waiter);

        $this->postJson('/api/v1/orders', $this->validPayload([
            'table_id' => $table->id,
            'items' => [
                ['menu_item_id' => $availableItem->id, 'quantity' => 1],
            ],
        ]))->assertCreated();

        $payload = $this->validPayload(['table_id' => $table->id]);
        $payload['items'][0]['menu_item_id'] = MenuItem::factory()->unavailable()->create()->id;

        $this->postJson('/api/v1/orders', $payload)->assertConflict();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(TableOperationalStatus::Occupied, $table->fresh()->status);
    }

    public function test_takeaway_can_omit_table(): void
    {
        $dineType = DineType::factory()->takeAway()->create();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create();
        $waiter = $this->userWithRole('waiter');

        Sanctum::actingAs($waiter);

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.table', null);

        $this->assertNull(Order::query()->first()->table_id);
    }

    public function test_dine_in_rejects_missing_table(): void
    {
        $dineType = DineType::factory()->dineIn()->create();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create();

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'dine_type_id' => $dineType->id,
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();
    }

    public function test_transaction_rolls_back_if_item_creation_fails(): void
    {
        $table = Table::factory()->create();
        $customer = Customer::factory()->create();
        $dineType = DineType::factory()->dineIn()->create();
        $item = MenuItem::factory()->create();
        $itemId = $item->id;
        $item->delete();

        try {
            app(OrderService::class)->place([
                'customer_id' => $customer->id,
                'dine_type_id' => $dineType->id,
                'table_id' => $table->id,
                'items' => [
                    ['menu_item_id' => $itemId, 'quantity' => 1],
                ],
            ], $this->userWithRole('waiter'));

            $this->fail('Order placement should have failed.');
        } catch (\Throwable) {
            // Expected: missing menu item after delete.
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(TableOperationalStatus::Available, $table->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        $item = MenuItem::factory()->create(['price' => 80]);

        return array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'dine_type_id' => DineType::query()->firstOrCreate(
                ['slug' => 'dine-in'],
                ['name' => 'Dine In', 'sort_order' => 1, 'is_active' => true],
            )->id,
            'table_id' => Table::factory()->create()->id,
            'items' => [
                [
                    'menu_item_id' => $item->id,
                    'quantity' => 1,
                ],
            ],
        ], $overrides);
    }
}
