<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentBreakdownReportService
{
    public function __construct(
        private readonly ReportQuery $query,
        private readonly ReportDateRange $dates,
    ) {}

    /**
     * Daily payment-mode totals. Split bills contribute Cash/UPI/Card from
     * bill_payments, never a single Split bucket.
     *
     * @return Collection<int, object>
     */
    public function paymentMode(ReportFilters $filters): Collection
    {
        $dateSql = $this->dates->billCalendarDateSql('bills');

        $query = $this->billsWithPayments($filters);

        return $query
            ->selectRaw($this->breakdownSelect($dateSql))
            ->groupByRaw($dateSql)
            ->orderByRaw($dateSql.' DESC')
            ->get();
    }

    /**
     * @return array{
     *     total_bills: int,
     *     total_orders: int,
     *     order_value: float,
     *     cash_value: float,
     *     upi_value: float,
     *     card_value: float,
     *     credit_value: float,
     *     nc_value: float,
     *     cancel_value: float,
     *     net_sales: float
     * }
     */
    public function paymentModeTotals(Collection $rows): array
    {
        return [
            'total_bills' => (int) $rows->sum(fn (object $row): int => (int) $row->total_bills),
            'total_orders' => (int) $rows->sum(fn (object $row): int => (int) $row->total_orders),
            'order_value' => $this->sumMoney($rows, 'order_value'),
            'cash_value' => $this->sumMoney($rows, 'cash_value'),
            'upi_value' => $this->sumMoney($rows, 'upi_value'),
            'card_value' => $this->sumMoney($rows, 'card_value'),
            'credit_value' => 0.0,
            'nc_value' => $this->sumMoney($rows, 'nc_value'),
            'cancel_value' => 0.0,
            'net_sales' => $this->sumMoney($rows, 'net_sales'),
        ];
    }

    /**
     * Daily waiter totals. Same payment split rules as payment-mode report.
     *
     * @return Collection<int, object>
     */
    public function waiter(ReportFilters $filters): Collection
    {
        $dateSql = $this->dates->billCalendarDateSql('bills');

        $query = $this->billsWithPayments($filters)
            ->join('users', 'users.id', '=', 'orders.waiter_id')
            ->join('roles', function ($join): void {
                $join->on('roles.id', '=', 'users.role_id')
                    ->where('roles.slug', '=', 'waiter');
            })
            ->whereNotNull('orders.waiter_id');

        return $query
            ->selectRaw(
                $this->breakdownSelect($dateSql).',
                users.name AS waiter_name,
                users.id AS waiter_id',
            )
            ->groupByRaw($dateSql.', users.id, users.name')
            ->orderByRaw($dateSql.' DESC')
            ->orderBy('users.name')
            ->get();
    }

    /**
     * @return array{
     *     total_orders: int,
     *     order_value: float,
     *     cash_value: float,
     *     upi_value: float,
     *     card_value: float,
     *     credit_value: float,
     *     nc_value: float,
     *     cancel_value: float,
     *     net_sales: float
     * }
     */
    public function waiterTotals(Collection $rows): array
    {
        return [
            'total_orders' => (int) $rows->sum(fn (object $row): int => (int) $row->total_orders),
            'order_value' => $this->sumMoney($rows, 'order_value'),
            'cash_value' => $this->sumMoney($rows, 'cash_value'),
            'upi_value' => $this->sumMoney($rows, 'upi_value'),
            'card_value' => $this->sumMoney($rows, 'card_value'),
            'credit_value' => 0.0,
            'nc_value' => $this->sumMoney($rows, 'nc_value'),
            'cancel_value' => 0.0,
            'net_sales' => $this->sumMoney($rows, 'net_sales'),
        ];
    }

    private function billsWithPayments(ReportFilters $filters): Builder
    {
        $query = DB::table('bills')
            ->leftJoin('orders', 'orders.id', '=', 'bills.order_id');

        $this->query->joinPaymentTotals($query);
        $this->query->applyBillDateFilter($query, $filters);

        return $query;
    }

    private function breakdownSelect(string $dateSql): string
    {
        return "
            {$dateSql} AS report_date,
            COUNT(DISTINCT bills.id) AS total_bills,
            COUNT(DISTINCT orders.id) AS total_orders,
            COALESCE(SUM(bills.grand_total), 0) AS order_value,
            COALESCE(SUM(payment_totals.cash_amount), 0) AS cash_value,
            COALESCE(SUM(payment_totals.upi_amount), 0) AS upi_value,
            COALESCE(SUM(payment_totals.card_amount), 0) AS card_value,
            0 AS credit_value,
            COALESCE(SUM(payment_totals.nc_amount), 0) AS nc_value,
            0 AS cancel_value,
            COALESCE(SUM(
                CASE
                    WHEN COALESCE(payment_totals.nc_amount, 0) > 0
                        AND COALESCE(payment_totals.cash_amount, 0) = 0
                        AND COALESCE(payment_totals.upi_amount, 0) = 0
                        AND COALESCE(payment_totals.card_amount, 0) = 0
                    THEN 0
                    ELSE bills.grand_total
                END
            ), 0) AS net_sales
        ";
    }

    private function sumMoney(Collection $rows, string $column): float
    {
        return round((float) $rows->sum(fn (object $row): float => (float) $row->{$column}), 2);
    }
}
