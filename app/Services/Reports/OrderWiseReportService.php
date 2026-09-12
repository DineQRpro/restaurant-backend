<?php

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderWiseReportService
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly ReportQuery $query,
        private readonly ReportDateRange $dates,
        private readonly BillPaymentTotals $paymentTotals,
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
     * @return array{count: int, grand_total: float, packing_charges: float, total_quantity: float}
     */
    public function totals(ReportFilters $filters): array
    {
        $row = DB::query()
            ->fromSub($this->baseQuery($filters), 'order_wise_rows')
            ->selectRaw(
                'COUNT(*) AS aggregate_count,
                COALESCE(SUM(bill_summary), 0) AS grand_total,
                COALESCE(SUM(packing_charges), 0) AS packing_charges,
                COALESCE(SUM(total_quantity), 0) AS total_quantity',
            )
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'grand_total' => round((float) ($row->grand_total ?? 0), 2),
            'packing_charges' => round((float) ($row->packing_charges ?? 0), 2),
            'total_quantity' => (float) ($row->total_quantity ?? 0),
        ];
    }

    private function baseQuery(ReportFilters $filters): Builder
    {
        $timestamp = $this->dates->billTimestampSql('bills');

        $query = DB::table('bills')
            ->leftJoin('orders', 'orders.id', '=', 'bills.order_id')
            ->leftJoin('dine_types', 'dine_types.id', '=', 'orders.dine_type_id')
            ->leftJoin('tables', 'tables.id', '=', 'orders.table_id')
            ->leftJoinSub(
                $this->query->activeItemStatsSubquery(),
                'item_stats',
                'item_stats.order_id',
                '=',
                'orders.id',
            );

        $this->query->joinPaymentTotals($query);
        $this->query->applyBillDateFilter($query, $filters);
        $this->query->applyBillNoFilter($query, $filters);
        $this->query->applyOrderTypeFilter($query, $filters);

        return $query
            ->selectRaw(
                "{$timestamp} AS report_date,
                orders.order_number,
                bills.bill_number,
                COALESCE(dine_types.name, '-') AS order_type,
                ".$this->paymentTotals->paymentTypeSql().' AS payment_mode,
                bills.grand_total AS bill_summary,
                COALESCE(item_stats.product_count, 0) AS product_count,
                COALESCE(item_stats.total_quantity, 0) AS total_quantity,
                COALESCE(item_stats.packing_charges, 0) AS packing_charges,
                COALESCE(orders.pax, 0) AS pax,
                CASE
                    WHEN COALESCE(orders.pax, 0) = 0 THEN 0
                    ELSE ROUND(bills.grand_total / orders.pax, 2)
                END AS pax_average,
                tables.number AS table_number',
            )
            ->orderByDesc('bills.id');
    }
}
