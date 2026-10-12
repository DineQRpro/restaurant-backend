<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $mapping = $this->getRolePermissionMapping();

        foreach ($mapping as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();

            if (! $role) {
                continue;
            }

            $permissionIds = Permission::whereIn('slug', $permissionSlugs)
                ->pluck('id');

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function getRolePermissionMapping(): array
    {
        return [
            'waiter' => [
                'dashboard.view',
                'menu.view',
                'tables.view',
                'tables.update',
                'tables.transfer',
                'tables.clean',
                'tables.merge',
                'orders.view',
                'orders.create',
                'orders.update',
                'orders.item_status',
                'kot.view',
                'kot.create',
                'bills.create',
            ],

            'captain' => [
                'dashboard.view',
                'menu.view',
                'tables.view',
                'tables.update',
                'tables.merge',
                'tables.transfer',
                'tables.clean',
                'orders.view',
                'orders.create',
                'orders.update',
                'orders.cancel',
                'orders.item_status',
                'kot.view',
                'kot.create',
                'kot.update',
                'bills.view',
                'bills.create',
            ],

            'counter' => [
                'dashboard.view',
                'tables.view',
                'orders.view',
                'orders.update',
                'kot.view',
                'kot.update',
                'bills.view',
                'bills.create',
                'bills.discount',
                'bills.split',
                'bills.settle',
                'receipts.view',
                'receipts.print',
                'reports.view',
            ],

            'admin' => Permission::pluck('slug')->all(),
        ];
    }
}
