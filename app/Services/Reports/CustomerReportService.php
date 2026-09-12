<?php

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerReportService
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
     * @return array{count: int, total_bill: float, visit_count: int}
     */
    public function totals(ReportFilters $filters): array
    {
        $row = DB::query()
            ->fromSub($this->baseQuery($filters), 'customer_rows')
            ->selectRaw(
                'COUNT(*) AS aggregate_count,
                COALESCE(SUM(total_bill), 0) AS total_bill,
                COALESCE(SUM(visit_count), 0) AS visit_count',
            )
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'total_bill' => round((float) ($row->total_bill ?? 0), 2),
            'visit_count' => (int) ($row->visit_count ?? 0),
        ];
    }

    private function baseQuery(ReportFilters $filters): Builder
    {
        $query = DB::table('bills')
            ->join('orders', 'orders.id', '=', 'bills.order_id')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereNotNull('customers.phone')
            ->where('customers.phone', '!=', '')
            ->whereNull('orders.cancelled_at');

        $this->query->applyBillDateFilter($query, $filters);

        $dateSql = $this->dates->billCalendarDateSql('bills');

        return $query
            ->groupBy('customers.phone')
            ->selectRaw(
                "customers.phone AS customer_mobile,
                MAX(customers.name) AS customer_name,
                COUNT(DISTINCT bills.id) AS visit_count,
                MAX({$dateSql}) AS last_visit_date,
                COALESCE(SUM(bills.grand_total), 0) AS total_bill,
                ROUND(SUM(bills.grand_total) / COUNT(DISTINCT bills.id), 2) AS bill_average",
            )
            ->orderByDesc('visit_count')
            ->orderByDesc('total_bill');
    }
}
