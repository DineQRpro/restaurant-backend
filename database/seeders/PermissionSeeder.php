<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view'],

            // Tables
            ['name' => 'View Tables', 'slug' => 'tables.view'],
            ['name' => 'Create Tables', 'slug' => 'tables.create'],
            ['name' => 'Update Tables', 'slug' => 'tables.update'],
            ['name' => 'Delete Tables', 'slug' => 'tables.delete'],
            ['name' => 'Merge Tables', 'slug' => 'tables.merge'],
            ['name' => 'Transfer Tables', 'slug' => 'tables.transfer'],

            // Orders
            ['name' => 'View Orders', 'slug' => 'orders.view'],
            ['name' => 'Create Orders', 'slug' => 'orders.create'],
            ['name' => 'Update Orders', 'slug' => 'orders.update'],
            ['name' => 'Cancel Orders', 'slug' => 'orders.cancel'],
            ['name' => 'Update Order Item Status', 'slug' => 'orders.item_status'],

            // KOT
            ['name' => 'View KOT', 'slug' => 'kot.view'],
            ['name' => 'Create KOT', 'slug' => 'kot.create'],
            ['name' => 'Update KOT', 'slug' => 'kot.update'],

            // Billing
            ['name' => 'View Bills', 'slug' => 'bills.view'],
            ['name' => 'Create Bills', 'slug' => 'bills.create'],
            ['name' => 'Apply Discounts', 'slug' => 'bills.discount'],
            ['name' => 'Split Bills', 'slug' => 'bills.split'],
            ['name' => 'Settle Bills', 'slug' => 'bills.settle'],

            // Menu
            ['name' => 'View Menu', 'slug' => 'menu.view'],
            ['name' => 'Create Menu Items', 'slug' => 'menu.create'],
            ['name' => 'Update Menu Items', 'slug' => 'menu.update'],
            ['name' => 'Delete Menu Items', 'slug' => 'menu.delete'],

            // Users
            ['name' => 'View Users', 'slug' => 'users.view'],
            ['name' => 'Create Users', 'slug' => 'users.create'],
            ['name' => 'Update Users', 'slug' => 'users.update'],
            ['name' => 'Delete Users', 'slug' => 'users.delete'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view'],

            // Receipts
            ['name' => 'View Receipts', 'slug' => 'receipts.view'],
            ['name' => 'Print Receipts', 'slug' => 'receipts.print'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission,
            );
        }
    }
}
