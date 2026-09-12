<?php

namespace App\Services\Reports;

use App\Enums\OrderItemStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemWiseReportService
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly ReportQuery $query,
        private readonly ReportDateRange $dates,
    ) {}

    /**
     * @return LengthAwarePaginator<int, object>
     */
    public function paginate(ReportFilters $filters): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * @return Collection<int, object>
     */
    public function all(ReportFilters $filters): Collection
    {
        return $this->baseQuery($filters)->get();
    }

    /**
     * @return Collection<int, object{name: string}>
     */
    public function itemOptions(): Collection
    {
        return DB::table('menu_items')
            ->select('name')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{count: int, total_quantity: float, total_amount: float}
     */
    public function totals(ReportFilters $filters): array
    {
        $row = DB::query()
            ->fromSub($this->baseQuery($filters), 'item_wise_rows')
            ->selectRaw(
                'COUNT(*) AS aggregate_count,
                COALESCE(SUM(total_quantity), 0) AS total_quantity,
                COALESCE(SUM(total_amount), 0) AS total_amount',
            )
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'total_quantity' => (float) ($row->total_quantity ?? 0),
            'total_amount' => round((float) ($row->total_amount ?? 0), 2),
        ];
    }

    private function baseQuery(ReportFilters $filters): Builder
    {
        $orderTimestamp = $this->dates->orderTimestampSql('orders');
        $dineInList = collect($this->query->dineInSlugs())
            ->map(fn (string $slug): string => "'".str_replace("'", "''", $slug)."'")
            ->implode(', ');

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->leftJoin('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->leftJoin('dine_types', 'dine_types.id', '=', 'orders.dine_type_id')
            ->where('order_items.status', '!=', OrderItemStatus::Cancelled->value);

        $this->query->applyOrderDateFilter($query, $filters);

        if ($filters->itemName !== null && $filters->itemName !== '') {
            $query->where('menu_items.name', 'like', '%'.$filters->itemName.'%');
        }

        return $query
            ->groupBy(
                'menu_items.id',
                'menu_items.name',
                'menu_items.dietary_type',
                'menu_categories.name',
            )
            ->selectRaw(
                "menu_items.name AS item_name,
                menu_items.dietary_type AS main_category,
                COALESCE(menu_categories.name, '-') AS category,
                SUM(CASE WHEN dine_types.slug IN ({$dineInList}) THEN order_items.quantity ELSE 0 END) AS dine_in_quantity,
                SUM(CASE WHEN dine_types.slug = 'take-away' THEN order_items.quantity ELSE 0 END) AS take_away_quantity,
                SUM(order_items.quantity) AS total_quantity,
                CASE
                    WHEN SUM(order_items.quantity) = 0 THEN 0
                    ELSE ROUND(SUM(order_items.quantity * order_items.unit_price) / SUM(order_items.quantity), 2)
                END AS price_per_item,
                SUM(order_items.quantity * order_items.unit_price) AS total_amount,
                MAX({$orderTimestamp}) AS report_date",
            )
            ->havingRaw('SUM(order_items.quantity) > 0')
            ->orderBy('menu_items.name');
    }
}
