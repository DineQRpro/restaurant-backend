<?php

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillStatusReportService
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

    public function count(ReportFilters $filters): int
    {
        return $this->baseQuery($filters)->getCountForPagination();
    }

    private function baseQuery(ReportFilters $filters): Builder
    {
        $timestamp = $this->dates->billTimestampSql('bills');

        $query = DB::table('bills')
            ->leftJoin('orders', 'orders.id', '=', 'bills.order_id')
            ->leftJoin('dine_types', 'dine_types.id', '=', 'orders.dine_type_id')
            ->leftJoin('users', 'users.id', '=', 'orders.waiter_id')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id');

        $this->query->joinPaymentTotals($query);
        $this->query->applyBillDateFilter($query, $filters);
        $this->query->applyBillStatusFilter($query, $filters);

        return $query
            ->selectRaw(
                "{$timestamp} AS report_date,
                bills.bill_number,
                orders.order_number,
                COALESCE(dine_types.name, '-') AS order_type,
                CASE WHEN roles.slug = 'waiter' THEN users.name ELSE '-' END AS waiter_name,
                CASE WHEN roles.slug = 'waiter' THEN users.id ELSE 0 END AS waiter_id,
                bills.subtotal AS bill_summary,
                ".$this->paymentTotals->paymentStatusSql().' AS bill_status',
            )
            ->orderByRaw($timestamp.' DESC')
            ->orderByDesc('bills.id');
    }
}
