<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
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

    // --- Relationship tests ---

    public function test_role_has_permissions_relationship(): void
    {
        $waiter = Role::where('slug', 'waiter')->first();

        $this->assertTrue($waiter->permissions()->exists());
        $this->assertContains('orders.create', $waiter->permissions->pluck('slug')->all());
    }

    public function test_permission_has_roles_relationship(): void
    {
        $permission = Permission::where('slug', 'dashboard.view')->first();

        $this->assertTrue($permission->roles()->exists());

        $roleSlugs = $permission->roles->pluck('slug')->all();
        $this->assertContains('admin', $roleSlugs);
        $this->assertContains('waiter', $roleSlugs);
    }

    // --- Waiter permissions ---

    public function test_waiter_has_expected_permissions(): void
    {
        $waiter = Role::where('slug', 'waiter')->first();
        $slugs = $waiter->permissions->pluck('slug')->all();

        $this->assertContains('orders.create', $slugs);
        $this->assertContains('orders.update', $slugs);
        $this->assertContains('menu.view', $slugs);
        $this->assertContains('tables.view', $slugs);
        $this->assertContains('kot.create', $slugs);
        $this->assertContains('bills.create', $slugs);
    }

    public function test_waiter_does_not_have_restricted_permissions(): void
    {
        $waiter = Role::where('slug', 'waiter')->first();
        $slugs = $waiter->permissions->pluck('slug')->all();

        $this->assertNotContains('orders.cancel', $slugs);
        $this->assertNotContains('bills.settle', $slugs);
        $this->assertNotContains('users.create', $slugs);
    }

    // --- Captain permissions ---

    public function test_captain_has_expected_permissions(): void
    {
        $captain = Role::where('slug', 'captain')->first();
        $slugs = $captain->permissions->pluck('slug')->all();

        $this->assertContains('tables.merge', $slugs);
        $this->assertContains('orders.cancel', $slugs);
        $this->assertContains('kot.update', $slugs);
        $this->assertContains('bills.create', $slugs);
    }

    public function test_captain_does_not_have_restricted_permissions(): void
    {
        $captain = Role::where('slug', 'captain')->first();
        $slugs = $captain->permissions->pluck('slug')->all();

        $this->assertNotContains('users.create', $slugs);
        $this->assertNotContains('bills.settle', $slugs);
    }

    // --- Counter permissions ---

    public function test_counter_has_expected_permissions(): void
    {
        $counter = Role::where('slug', 'counter')->first();
        $slugs = $counter->permissions->pluck('slug')->all();

        $this->assertContains('bills.settle', $slugs);
        $this->assertContains('bills.split', $slugs);
        $this->assertContains('receipts.print', $slugs);
        $this->assertContains('reports.view', $slugs);
    }

    public function test_counter_does_not_have_restricted_permissions(): void
    {
        $counter = Role::where('slug', 'counter')->first();
        $slugs = $counter->permissions->pluck('slug')->all();

        $this->assertNotContains('orders.create', $slugs);
        $this->assertNotContains('users.create', $slugs);
    }

    // --- Admin permissions ---

    public function test_admin_has_all_permissions(): void
    {
        $admin = Role::where('slug', 'admin')->first();
        $allPermissions = Permission::pluck('slug')->sort()->values()->all();
        $adminPermissions = $admin->permissions->pluck('slug')->sort()->values()->all();

        $this->assertEquals($allPermissions, $adminPermissions);
    }
}
