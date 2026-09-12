<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_permissions_are_seeded(): void
    {
        $this->seed(PermissionSeeder::class);

        $expectedSlugs = [
            'dashboard.view',
            'tables.view', 'tables.create', 'tables.update', 'tables.delete', 'tables.merge', 'tables.transfer',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'orders.item_status',
            'kot.view', 'kot.create', 'kot.update',
            'bills.view', 'bills.create', 'bills.discount', 'bills.split', 'bills.settle',
            'menu.view', 'menu.create', 'menu.update', 'menu.delete',
            'users.view', 'users.create', 'users.update', 'users.delete',
            'reports.view',
            'receipts.view', 'receipts.print',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }

        $this->assertCount(count($expectedSlugs), Permission::all());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PermissionSeeder::class);
        $countAfterFirst = Permission::count();

        $this->seed(PermissionSeeder::class);
        $countAfterSecond = Permission::count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }
}
