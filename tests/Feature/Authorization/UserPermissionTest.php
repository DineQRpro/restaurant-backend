<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    public function test_user_has_permission_returns_true_for_assigned_permission(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->first();
        $user = User::factory()->create(['role_id' => $waiterRole->id]);

        $this->assertTrue($user->hasPermission('orders.create'));
    }

    public function test_user_has_permission_returns_false_for_unassigned_permission(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->first();
        $user = User::factory()->create(['role_id' => $waiterRole->id]);

        $this->assertFalse($user->hasPermission('users.create'));
    }

    public function test_user_has_permission_returns_false_for_nonexistent_permission(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->first();
        $user = User::factory()->create(['role_id' => $waiterRole->id]);

        $this->assertFalse($user->hasPermission('nonexistent.permission'));
    }

    public function test_get_permission_slugs_returns_all_role_permissions(): void
    {
        $waiterRole = Role::where('slug', 'waiter')->first();
        $user = User::factory()->create(['role_id' => $waiterRole->id]);

        $slugs = $user->getPermissionSlugs();

        $this->assertContains('orders.create', $slugs);
        $this->assertContains('tables.view', $slugs);
        $this->assertNotContains('users.create', $slugs);
    }

    public function test_admin_user_has_all_permissions(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $allSlugs = Permission::pluck('slug')->all();

        foreach ($allSlugs as $slug) {
            $this->assertTrue($user->hasPermission($slug), "Admin should have permission: {$slug}");
        }
    }
}
