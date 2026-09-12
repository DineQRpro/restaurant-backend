<?php

namespace App\Support;

use App\Models\User;

class CounterNavigation
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
                'route' => 'counter.dashboard',
            ],
            [
                'label' => 'Orders',
                'permission' => 'orders.view',
                'available' => false,
            ],
            [
                'label' => 'Billing',
                'permission' => 'bills.view',
                'available' => false,
            ],
            [
                'label' => 'Receipts',
                'permission' => 'receipts.view',
                'available' => false,
            ],
            [
                'label' => 'Reports',
                'permission' => 'reports.view',
                'available' => true,
                'match' => 'counter.reports.*',
                'children' => ReportCatalog::counterNavChildren(),
            ],
        ];

        return array_values(array_filter(
            $items,
            fn (array $item) => in_array($item['permission'], $permissions, true),
        ));
    }
}
