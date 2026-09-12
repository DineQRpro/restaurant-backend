<?php

namespace Tests\Feature\Admin;

use App\Enums\TableOperationalStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAuthorization;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use InteractsWithAuthorization, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorization();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/admin/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_authorized_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('Tables');
    }

    public function test_dashboard_shows_real_user_and_menu_counts(): void
    {
        $admin = $this->userWithRole('admin');
        $this->userWithRole('waiter', ['username' => 'floor-waiter']);

        $category = MenuCategory::factory()->create(['name' => 'Mains']);
        MenuItem::factory()->create([
            'name' => 'Chicken Biriyani',
            'category_id' => $category->id,
            'is_available' => true,
        ]);
        MenuItem::factory()->unavailable()->create([
            'name' => 'Sold Out Soup',
            'category_id' => $category->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Total Users')
            ->assertSee('Sold Out Dishes')
            ->assertSee('Sold Out Soup')
            ->assertSee('Total Amount Received')
            ->assertSee('Available when Orders are implemented')
            ->assertSee('No current orders.')
            ->assertSee('Users')
            ->assertSee('Soon');
    }

    public function test_dashboard_table_board_shows_active_operational_statuses(): void
    {
        $admin = $this->userWithRole('admin');

        Table::factory()->create([
            'number' => 'T1',
            'capacity' => 4,
            'sort_order' => 1,
            'status' => TableOperationalStatus::Available,
        ]);
        Table::factory()->occupied()->create([
            'number' => 'T2',
            'capacity' => 4,
            'sort_order' => 2,
        ]);
        Table::factory()->cleaning()->create([
            'number' => 'T3',
            'capacity' => 2,
            'sort_order' => 3,
        ]);
        Table::factory()->inactive()->create([
            'number' => 'T99',
            'capacity' => 8,
            'sort_order' => 4,
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Table T1')
            ->assertSee('Table T2')
            ->assertSee('Table T3')
            ->assertDontSee('Table T99')
            ->assertSee('4 Chairs')
            ->assertSee('2 Chairs')
            ->assertSee('Total Active Tables')
            ->assertSee('Available Tables')
            ->assertSee('Occupied Tables')
            ->assertSee('Cleaning Tables')
            ->assertSee('Available')
            ->assertSee('Occupied')
            ->assertSee('Cleaning')
            ->assertDontSee('Reserved')
            ->assertDontSee('Table status will appear here when Tables are implemented.');
    }

    public function test_dashboard_table_counts_match_active_tables(): void
    {
        $admin = $this->userWithRole('admin');

        Table::factory()->count(2)->create(['status' => TableOperationalStatus::Available]);
        Table::factory()->occupied()->create();
        Table::factory()->cleaning()->create();
        Table::factory()->inactive()->occupied()->create();

        $html = $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<p class="text-xl font-bold text-\[#04346a\]">4<\/p>\s*<p class="mt-1 text-xs font-semibold text-\[#1f2f3e\]">Total Active Tables<\/p>/', $html);
        $this->assertMatchesRegularExpression('/<p class="text-xl font-bold text-\[#04346a\]">2<\/p>\s*<p class="mt-1 text-xs font-semibold text-\[#1f2f3e\]">Available Tables<\/p>/', $html);
        $this->assertMatchesRegularExpression('/<p class="text-xl font-bold text-\[#04346a\]">1<\/p>\s*<p class="mt-1 text-xs font-semibold text-\[#1f2f3e\]">Occupied Tables<\/p>/', $html);
        $this->assertMatchesRegularExpression('/<p class="text-xl font-bold text-\[#04346a\]">1<\/p>\s*<p class="mt-1 text-xs font-semibold text-\[#1f2f3e\]">Cleaning Tables<\/p>/', $html);
    }

    public function test_dashboard_reflects_table_status_change_on_next_request(): void
    {
        $admin = $this->userWithRole('admin');
        $table = Table::factory()->create([
            'number' => 'T5',
            'capacity' => 4,
            'status' => TableOperationalStatus::Available,
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Table T5')
            ->assertSee('admin-table-board-card admin-table-status-available', false);

        $table->update(['status' => TableOperationalStatus::Occupied]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Table T5')
            ->assertSee('admin-table-board-card admin-table-status-occupied', false)
            ->assertDontSee('admin-table-board-card admin-table-status-available', false);

        $table->update(['status' => TableOperationalStatus::Cleaning]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Table T5')
            ->assertSee('admin-table-board-card admin-table-status-cleaning', false);
    }

    public function test_waiter_session_cannot_open_the_admin_dashboard(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_navigation_is_permission_based(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Menu')
            ->assertSee('Users')
            ->assertSee('Logout');
    }

    public function test_admin_without_dashboard_view_cannot_access_the_dashboard(): void
    {
        $admin = $this->userWithRole('admin');
        $permission = Permission::query()->where('slug', 'dashboard.view')->firstOrFail();
        $admin->role->permissions()->detach($permission->id);

        $this->actingAs($admin->fresh())
            ->get('/admin/dashboard')
            ->assertForbidden();
    }
}
