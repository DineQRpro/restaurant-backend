<?php

namespace App\Support;

use App\Models\User;

class AdminNavigation
{
    /**
     * @return list<array{
     *     label: string,
     *     permission: string,
     *     available: bool,
     *     route?: string,
     *     match?: string,
     *     children?: list<array{label: string, available: bool, route?: string, match?: string}>
     * }>
     */
    public function items(User $user): array
    {
        $permissions = $user->getPermissionSlugs();

        $items = [
            [
                'label' => 'Dashboard',
                'permission' => 'dashboard.view',
                'available' => true,
                'route' => 'admin.dashboard',
            ],
            [
                'label' => 'Menu',
                'permission' => 'menu.view',
                'available' => true,
                'children' => [
                    [
                        'label' => 'Categories',
                        'available' => true,
                        'route' => 'admin.menu.categories.index',
                        'match' => 'admin.menu.categories.*',
                    ],
                    [
                        'label' => 'Items',
                        'available' => true,
                        'route' => 'admin.menu.items.index',
                        'match' => 'admin.menu.items.*',
                    ],
                ],
            ],
            [
                'label' => 'Users',
                'permission' => 'users.view',
                'available' => true,
                'route' => 'admin.users.index',
                'match' => 'admin.users.*',
            ],
            [
                'label' => 'Tables',
                'permission' => 'tables.view',
                'available' => true,
                'route' => 'admin.tables.index',
                'match' => 'admin.tables.*',
            ],
            [
                'label' => 'Discounts',
                'permission' => 'discounts.view',
                'available' => true,
                'route' => 'admin.discounts.index',
                'match' => 'admin.discounts.*',
            ],
            [
                'label' => 'Orders',
                'permission' => 'orders.view',
                'available' => false,
            ],
            [
                'label' => 'KOT / Kitchen',
                'permission' => 'kot.view',
                'available' => false,
            ],
            [
                'label' => 'Billing',
                'permission' => 'bills.view',
                'available' => false,
            ],
            [
                'label' => 'Payments',
                'permission' => 'bills.settle',
                'available' => false,
            ],
            [
                'label' => 'Reports',
                'permission' => 'reports.view',
                'available' => true,
                'match' => 'admin.reports.*',
                'children' => ReportCatalog::adminNavChildren(),
            ],
            [
                'label' => 'Receipts',
                'permission' => 'receipts.view',
                'available' => true,
                'route' => 'admin.receipts.index',
                'match' => 'admin.receipts.*',
            ],
            [
                'label' => 'Settings',
                'permission' => 'dashboard.view',
                'available' => false,
            ],
        ];

        return array_values(array_filter(
            $items,
            fn (array $item) => in_array($item['permission'], $permissions, true),
        ));
    }
}
