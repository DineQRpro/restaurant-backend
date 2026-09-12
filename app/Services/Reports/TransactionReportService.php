<?php

namespace App\Services\Reports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionReportService
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
     * @return array{count: int, grand_total: float, discount_total: float, tax_total: float}
     */
    public function totals(ReportFilters $filters): array
    {
        $row = $this->filteredBills($filters)
            ->selectRaw(
                'COUNT(*) AS aggregate_count,
                COALESCE(SUM(bills.grand_total), 0) AS grand_total,
                COALESCE(SUM(bills.discount_amount), 0) AS discount_total,
                COALESCE(SUM(bills.tax_amount), 0) AS tax_total',
            )
            ->first();

        return [
            'count' => (int) ($row->aggregate_count ?? 0),
            'grand_total' => round((float) ($row->grand_total ?? 0), 2),
            'discount_total' => round((float) ($row->discount_total ?? 0), 2),
            'tax_total' => round((float) ($row->tax_total ?? 0), 2),
        ];
    }

    private function baseQuery(ReportFilters $filters): Builder
    {
        $timestamp = $this->dates->billTimestampSql('bills');

        $query = DB::table('bills')
            ->leftJoin('orders', 'orders.id', '=', 'bills.order_id')
            ->leftJoin('discounts', 'discounts.id', '=', 'bills.discount_id');

        $this->query->joinPaymentTotals($query);
        $this->query->applyBillDateFilter($query, $filters);
        $this->query->applyBillNoFilter($query, $filters);

        return $query
            ->selectRaw(
                "{$timestamp} AS report_date,
                bills.bill_number,
                orders.order_number,
                bills.subtotal AS bill_summary,
                bills.discount_amount,
                discounts.name AS discount_name,
                bills.other_discount_comment,
                bills.tax_amount AS gst,
                0 AS additional_charges,
                bills.grand_total,
                ".$this->paymentTotals->paymentTypeSql().' AS payment_type,
                '.$this->paymentTotals->paymentStatusSql().' AS payment_status',
            )
            ->orderByRaw($timestamp.' DESC')
            ->orderByDesc('bills.id');
    }

    private function filteredBills(ReportFilters $filters): Builder
    {
        $query = DB::table('bills');
        $this->query->applyBillDateFilter($query, $filters);
        $this->query->applyBillNoFilter($query, $filters);

        return $query;
    }
}
