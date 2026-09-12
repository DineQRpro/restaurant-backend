<?php

namespace Tests\Feature\Admin\Tables;

use App\Models\Permission;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class TableUiTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_guest_is_redirected_from_tables_to_login(): void
    {
        $this->get('/admin/tables')
            ->assertRedirect('/admin/login');
    }

    public function test_waiter_cannot_open_admin_tables(): void
    {
        $this->actingAs($this->userWithRole('waiter'))
            ->get('/admin/tables')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_table_list(): void
    {
        Table::factory()->create([
            'number' => 'T1',
            'description' => 'Window table',
            'capacity' => 4,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/tables')
            ->assertOk()
            ->assertSee('Restaurant Table List')
            ->assertSee('T1')
            ->assertSee('Window table')
            ->assertSee('4')
            ->assertSee('Add New')
            ->assertSee('Tables');
    }

    public function test_empty_list_shows_an_empty_state(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/tables')
            ->assertOk()
            ->assertSee('There is no Table');
    }

    public function test_search_and_status_filters_are_applied_together(): void
    {
        Table::factory()->create([
            'number' => 'VIP-1',
            'description' => 'Garden',
            'is_active' => true,
        ]);
        Table::factory()->inactive()->create([
            'number' => 'VIP-2',
            'description' => 'Garden closed',
        ]);
        Table::factory()->create([
            'number' => 'A1',
            'description' => 'Hall',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/tables?search=VIP&is_active=1')
            ->assertOk()
            ->assertSee('VIP-1')
            ->assertDontSee('VIP-2')
            ->assertDontSee('Hall');
    }

    public function test_filtered_empty_state_is_specific(): void
    {
        Table::factory()->create(['number' => '12']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/tables?search=DoesNotExist')
            ->assertOk()
            ->assertSee('No tables match your search or filters.');
    }

    public function test_admin_can_create_a_table(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->post('/admin/tables', [
                'number' => 'T2',
                'description' => 'Patio',
                'capacity' => 6,
                'sort_order' => 2,
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/tables')
            ->assertSessionHas('success');

        $table = Table::query()->where('number', 'T2')->first();

        $this->assertNotNull($table);
        $this->assertSame('Patio', $table->description);
        $this->assertSame(6, $table->capacity);
        $this->assertTrue($table->is_active);
        $this->assertNotEmpty($table->code);
    }

    public function test_create_validates_required_fields_and_capacity(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/tables/create')
            ->post('/admin/tables', [
                'number' => '',
                'capacity' => 0,
            ])
            ->assertRedirect('/admin/tables/create')
            ->assertSessionHasErrors(['number', 'capacity']);
    }

    public function test_create_rejects_duplicate_and_negative_capacity(): void
    {
        Table::factory()->create(['number' => '12']);

        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/tables/create')
            ->post('/admin/tables', [
                'number' => '12',
                'capacity' => -1,
            ])
            ->assertRedirect('/admin/tables/create')
            ->assertSessionHasErrors(['number', 'capacity']);
    }

    public function test_admin_can_update_a_table_without_changing_code(): void
    {
        $table = Table::factory()->create([
            'number' => '3',
            'description' => 'Old',
            'capacity' => 2,
            'code' => 'KEEP-CODE',
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->put('/admin/tables/'.$table->id, [
                'number' => '3A',
                'description' => 'Updated',
                'capacity' => 4,
                'sort_order' => 1,
                'is_active' => 1,
                'code' => 'CHANGED',
            ])
            ->assertRedirect('/admin/tables')
            ->assertSessionHas('success');

        $table->refresh();

        $this->assertSame('3A', $table->number);
        $this->assertSame('Updated', $table->description);
        $this->assertSame(4, $table->capacity);
        $this->assertSame('KEEP-CODE', $table->code);
    }

    public function test_admin_can_toggle_active_status(): void
    {
        $table = Table::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole('admin'))
            ->patch('/admin/tables/'.$table->id.'/status', [
                'is_active' => 0,
            ])
            ->assertRedirect('/admin/tables')
            ->assertSessionHas('success');

        $this->assertFalse($table->fresh()->is_active);
    }

    public function test_delete_is_rejected_and_table_remains(): void
    {
        $table = Table::factory()->create(['number' => 'keep-history']);

        $this->actingAs($this->userWithRole('admin'))
            ->from('/admin/tables')
            ->delete('/admin/tables/'.$table->id)
            ->assertRedirect('/admin/tables')
            ->assertSessionHas('error', 'Tables cannot be permanently deleted. Deactivate the table instead.');

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'number' => 'keep-history',
        ]);
    }

    public function test_listing_paginates_and_preserves_query_string(): void
    {
        Table::factory()->count(21)->create();

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/tables?is_active=1')
            ->assertOk()
            ->assertSee('page=2');
    }

    public function test_actions_are_hidden_without_matching_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        Table::factory()->create(['number' => 'Visible Table']);

        $admin->role->permissions()->detach(
            Permission::query()->whereIn('slug', ['tables.create', 'tables.update', 'tables.delete'])->pluck('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/tables')
            ->assertOk()
            ->assertSee('Visible Table')
            ->assertSee('View only')
            ->assertDontSee('Add New')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_user_without_tables_view_cannot_list_tables(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'tables.view')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/tables')
            ->assertForbidden();
    }

    public function test_create_requires_tables_create(): void
    {
        $admin = $this->userWithRole('admin');
        $admin->role->permissions()->detach(
            Permission::query()->where('slug', 'tables.create')->value('id'),
        );

        $this->actingAs($admin->fresh())
            ->get('/admin/tables/create')
            ->assertForbidden();
    }
}
