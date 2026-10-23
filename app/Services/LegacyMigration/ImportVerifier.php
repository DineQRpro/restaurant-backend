<?php

namespace App\Services\LegacyMigration;

use App\Models\Discount;
use App\Models\Floor;
use App\Models\LegacyMigrationIdMap;
use App\Models\LegacyMigrationRun;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\PaymentMethod;
use App\Models\Table;
use App\Models\User;

class ImportVerifier
{
    /**
     * @return array{ok: bool, checks: list<array{name: string, ok: bool, detail: string}>}
     */
    public function verify(LegacyMigrationRun $run): array
    {
        $tables = $run->summary['tables'] ?? [];
        $foodId = MenuType::query()->where('slug', MenuType::SLUG_FOOD)->value('id');
        $hotId = MenuType::query()->where('slug', MenuType::SLUG_HOT_BEVERAGES)->value('id');
        $checks = [
            $this->check('categories', MenuCategory::query()->count(), (int) ($tables['tbl_category']['imported'] ?? 0)),
            $this->check('items', MenuItem::query()->count(), (int) ($tables['tbl_products']['imported'] ?? 0)),
            $this->check('tables', Table::query()->count(), (int) ($tables['tbl_tables']['imported'] ?? 0)),
            $this->check('users', User::query()->count(), (int) ($tables['tbl_users']['imported'] ?? 0) + User::query()->where('username', 'legacy-unassigned')->count()),
            $this->check('inactive discounts', Discount::query()->where('is_active', false)->count(), (int) ($tables['tbl_discounts']['imported'] ?? 0)),
            $this->check('active discounts', Discount::query()->where('is_active', true)->count(), 0),
            $this->check('hot beverage categories', $hotId === null ? 0 : MenuCategory::query()->where('menu_type_id', $hotId)->count(), 0),
            $this->check('categories outside Food Menu', $foodId === null ? MenuCategory::query()->count() : MenuCategory::query()->where('menu_type_id', '!=', $foodId)->count(), 0),
            $this->check('tables off Ground Floor', Table::query()->where(function ($query): void {
                $query->whereNull('floor_id')->orWhere('floor_id', '!=', 1);
            })->count(), 0),
            $this->check('items without a category', MenuItem::query()->whereDoesntHave('category')->count(), 0),
            $this->check('category id maps', LegacyMigrationIdMap::query()->where('source_table', 'tbl_category')->count(), (int) ($tables['tbl_category']['imported'] ?? 0)),
            $this->check('payment methods', PaymentMethod::query()->count(), 6),
            $this->check('inactive credit method', PaymentMethod::query()->where('slug', PaymentMethod::SLUG_CREDIT)->where('is_active', false)->count(), 1),
            $this->check('placed historical orders', \App\Models\Order::query()->where('status', 'placed')->count(), 0),
            $this->check('orders', \App\Models\Order::query()->count(), (int) ($tables['tbl_order']['imported'] ?? 0)),
            $this->check('order items', \App\Models\OrderItem::query()->count(), (int) ($tables['tbl_order_items']['imported'] ?? 0)),
            $this->check('bills', \App\Models\Bill::query()->count(), (int) ($tables['tbl_bill']['imported'] ?? 0)),
            $this->check('receipts', \App\Models\Receipt::query()->count(), (int) ($tables['tbl_receipts']['imported'] ?? 0)),
            $this->check('ground floor', Floor::query()->whereKey(1)->where('code', 'ground-floor')->count(), 1),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $check): bool => $check['ok']),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    private function check(string $name, int $actual, int $expected): array
    {
        return [
            'name' => $name,
            'ok' => $actual === $expected,
            'detail' => $actual.' of '.$expected,
        ];
    }
}
