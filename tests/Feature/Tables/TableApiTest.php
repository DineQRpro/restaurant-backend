<?php

namespace Tests\Feature\Tables;

use App\Models\Customer;
use App\Models\DineType;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class TableApiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_unauthenticated_user_cannot_list_tables(): void
    {
        $this->getJson('/api/v1/tables')->assertUnauthorized();
    }

    public function test_user_without_tables_view_cannot_list_tables(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'tables.view')->value('id'),
        );

        Sanctum::actingAs($admin->fresh());

        $this->getJson('/api/v1/tables')->assertForbidden();
    }

    public function test_waiter_can_list_active_tables(): void
    {
        Table::factory()->create([
            'number' => '1',
            'capacity' => 4,
            'is_active' => true,
        ]);
        Table::factory()->inactive()->create([
            'number' => '99',
            'capacity' => 2,
        ]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/tables?is_active=1')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Tables retrieved successfully.',
            ])
            ->assertJsonPath('data.0.number', '1')
            ->assertJsonMissing(['number' => '99'])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'number',
                        'description',
                        'capacity',
                        'status',
                        'code',
                        'sort_order',
                        'is_active',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('data.0.status', 0);
    }

    public function test_waiter_can_show_a_table(): void
    {
        $table = Table::factory()->create([
            'number' => 'T1',
            'capacity' => 2,
        ]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->getJson('/api/v1/tables/'.$table->id)
            ->assertOk()
            ->assertJsonPath('data.number', 'T1')
            ->assertJsonPath('data.capacity', 2)
            ->assertJsonPath('data.status', 0);
    }

    public function test_search_filters_tables(): void
    {
        Table::factory()->create(['number' => 'VIP-1', 'description' => 'Garden']);
        Table::factory()->create(['number' => 'A1', 'description' => 'Hall']);

        Sanctum::actingAs($this->userWithRole('captain'));

        $this->getJson('/api/v1/tables?search=VIP')
            ->assertOk()
            ->assertJsonPath('data.0.number', 'VIP-1')
            ->assertJsonMissing(['number' => 'A1']);
    }

    public function test_occupied_table_includes_pax_and_total(): void
    {
        $table = Table::factory()->create();
        $item = MenuItem::factory()->create(['price' => 100.00]);

        Sanctum::actingAs($this->userWithRole('waiter'));

        $this->postJson('/api/v1/orders', [
            'customer_id' => Customer::factory()->create()->id,
            'dine_type_id' => DineType::factory()->dineIn()->create()->id,
            'table_id' => $table->id,
            'pax' => 3,
            'items' => [
                ['menu_item_id' => $item->id, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->getJson('/api/v1/tables/'.$table->id)
            ->assertOk()
            ->assertJsonPath('data.status', 1)
            ->assertJsonPath('data.pax', 3)
            ->assertJsonPath('data.total', '200.00')
            ->assertJsonPath('data.current_order.pax', 3);
    }
}
