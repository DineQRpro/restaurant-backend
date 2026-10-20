<?php

namespace App\Services\LegacyMigration;

use App\Models\Floor;
use App\Models\MenuCategory;
use App\Models\MenuType;
use App\Models\PaymentMethod;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class SourceAnalyzer
{
    public function __construct(private readonly LegacySource $source) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $legacy = $this->source->connection();
        $privileges = $legacy->getDriverName() === 'mysql'
            ? $this->source->privilegeSummary()
            : ['select_only' => true, 'summary' => 'Fixture connection'];

        $roleRows = $legacy->table('tbl_users')
            ->select('role', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('role')
            ->orderBy('role')
            ->get();

        $hotBeveragesId = MenuType::query()->where('slug', MenuType::SLUG_HOT_BEVERAGES)->value('id');
        $groundFloor = Floor::query()->find(1);

        return [
            'privileges' => $privileges,
            'counts' => [
                'tbl_users' => $legacy->table('tbl_users')->count(),
                'tbl_category' => $legacy->table('tbl_category')->count(),
                'tbl_products' => $legacy->table('tbl_products')->count(),
                'tbl_tables' => $legacy->table('tbl_tables')->count(),
                'tbl_tables_master' => $legacy->table('tbl_tables')->whereNull('tables_session_id')->count(),
                'tbl_tables_session' => $legacy->table('tbl_tables')->whereNotNull('tables_session_id')->count(),
                'tbl_discounts' => $legacy->table('tbl_discounts')->count(),
                'tbl_discounts_deleted' => $legacy->table('tbl_discounts')->where('deleted', 1)->count(),
            ],
            'roles' => $roleRows->map(fn (object $row): array => [
                'role' => $row->role,
                'count' => (int) $row->aggregate,
            ])->all(),
            'validation' => [
                'products_without_category' => $legacy->table('tbl_products as products')
                    ->leftJoin('tbl_category as categories', 'categories.category_id', '=', 'products.product_category')
                    ->whereNull('categories.category_id')
                    ->count(),
                'unsupported_dietary' => $legacy->table('tbl_products')
                    ->whereNotNull('product_main_category')
                    ->whereNotIn('product_main_category', [1, 2, 3])
                    ->count(),
                'unsupported_spice' => $legacy->table('tbl_products')
                    ->whereNotIn('product_spicy', [0, 1, 2])
                    ->count(),
                'unsupported_availability' => $legacy->table('tbl_products')
                    ->whereNotIn('product_avail', [1, 2])
                    ->count(),
                'discounts_missing_applies_to' => $legacy->table('tbl_discounts')
                    ->where(function ($query): void {
                        $query->whereNull('deleted')->orWhere('deleted', 0);
                    })
                    ->whereNull('applies_to')
                    ->count(),
            ],
            'destination' => [
                'roles' => Role::query()->orderBy('slug')->pluck('slug')->all(),
                'payment_methods' => PaymentMethod::query()->orderBy('slug')->pluck('slug')->all(),
                'menu_types' => MenuType::query()->orderBy('slug')->pluck('slug')->all(),
                'ground_floor' => $groundFloor === null ? null : [
                    'id' => $groundFloor->id,
                    'name' => $groundFloor->name,
                    'code' => $groundFloor->code,
                ],
                'hot_beverage_categories' => $hotBeveragesId === null
                    ? 0
                    : MenuCategory::query()->where('menu_type_id', $hotBeveragesId)->count(),
            ],
        ];
    }
}
