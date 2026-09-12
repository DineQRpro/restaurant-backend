<?php

namespace App\Support;

class ReportCatalog
{
    public const PERMISSION = 'reports.view';

    /**
     * Admin Reports submenu, matching VarsityKitchen sidebar.php order.
     *
     * @return list<array{slug: string, label: string, method: string}>
     */
    public static function admin(): array
    {
        return [
            ['slug' => 'item-summary', 'label' => 'Item Summary Report', 'method' => 'itemSummary'],
            ['slug' => 'bill-summary', 'label' => 'Bill Summary Report', 'method' => 'billSummary'],
            ['slug' => 'bill-status', 'label' => 'Bill Status Report', 'method' => 'billStatus'],
            ['slug' => 'transaction', 'label' => 'Transaction Report', 'method' => 'transaction'],
            ['slug' => 'payment-mode', 'label' => 'Payment Mode Report', 'method' => 'paymentMode'],
            ['slug' => 'waiter', 'label' => 'Waiter Report', 'method' => 'waiter'],
            ['slug' => 'order-wise', 'label' => 'Order Wise Report', 'method' => 'orderWise'],
            ['slug' => 'item-wise', 'label' => 'Item Wise Detail Report', 'method' => 'itemWise'],
            ['slug' => 'customer', 'label' => 'Customer Data Report', 'method' => 'customer'],
        ];
    }

    /**
     * Counter Reports submenu, matching VarsityKitchen countersidebar.php
     * non-captain (else) branch order.
     *
     * @return list<array{slug: string, label: string, method: string}>
     */
    public static function counter(): array
    {
        return [
            ['slug' => 'transaction', 'label' => 'Transaction Reports', 'method' => 'transaction'],
            ['slug' => 'order-wise', 'label' => 'Order Wise Report', 'method' => 'orderWise'],
            ['slug' => 'item-wise', 'label' => 'Product Wise Report', 'method' => 'itemWise'],
            ['slug' => 'waiter', 'label' => 'Waiter Report', 'method' => 'waiter'],
            ['slug' => 'payment-mode', 'label' => 'Payment Mode Report', 'method' => 'paymentMode'],
            ['slug' => 'bill-status', 'label' => 'Bill Status Report', 'method' => 'billStatus'],
            ['slug' => 'customer', 'label' => 'Customer Report', 'method' => 'customer'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function adminLabels(): array
    {
        return array_column(self::admin(), 'label');
    }

    /**
     * @return list<string>
     */
    public static function counterLabels(): array
    {
        return array_column(self::counter(), 'label');
    }

    /**
     * @return list<array{label: string, available: bool, route: string, match: string}>
     */
    public static function adminNavChildren(): array
    {
        return array_map(
            fn (array $report): array => [
                'label' => $report['label'],
                'available' => true,
                'route' => 'admin.reports.'.$report['slug'],
                'match' => 'admin.reports.'.$report['slug'],
            ],
            self::admin(),
        );
    }

    /**
     * @return list<array{label: string, available: bool, route: string, match: string}>
     */
    public static function counterNavChildren(): array
    {
        return array_map(
            fn (array $report): array => [
                'label' => $report['label'],
                'available' => true,
                'route' => 'counter.reports.'.$report['slug'],
                'match' => 'counter.reports.'.$report['slug'],
            ],
            self::counter(),
        );
    }
}
