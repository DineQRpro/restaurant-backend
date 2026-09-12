<?php

namespace App\Services\Reports;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportQuery
{
    public function __construct(
        private readonly ReportDateRange $dates,
        private readonly BillPaymentTotals $paymentTotals,
    ) {}

    public function applyBillDateFilter(Builder $query, ReportFilters $filters, string $alias = 'bills'): Builder
    {
        if (! $filters->hasDateRange()) {
            return $query;
        }

        $column = $this->dates->billTimestampSql($alias);

        return $query->whereRaw(
            "{$column} >= ? AND {$column} <= ?",
            [$filters->from->format('Y-m-d H:i:s'), $filters->to->format('Y-m-d H:i:s')],
        );
    }

    public function applyOrderDateFilter(Builder $query, ReportFilters $filters, string $alias = 'orders'): Builder
    {
        if (! $filters->hasDateRange()) {
            return $query;
        }

        $column = $this->dates->orderTimestampSql($alias);

        return $query->whereRaw(
            "{$column} >= ? AND {$column} <= ?",
            [$filters->from->format('Y-m-d H:i:s'), $filters->to->format('Y-m-d H:i:s')],
        );
    }

    public function applyBillNoFilter(Builder $query, ReportFilters $filters): Builder
    {
        if ($filters->billNo === null || $filters->billNo === '') {
            return $query;
        }

        return $query->where('bills.bill_number', 'like', '%'.$filters->billNo.'%');
    }

    public function applyOrderTypeFilter(Builder $query, ReportFilters $filters): Builder
    {
        return match ($filters->orderType) {
            'through_waiter' => $query
                ->where('dine_types.slug', 'dine-in')
                ->whereNotNull('orders.waiter_id'),
            'dine_in' => $query->where('dine_types.slug', 'dine-in'),
            'take_away' => $query->where('dine_types.slug', 'take-away'),
            'candle_light_dinner' => $query->where('dine_types.slug', 'candle-light-dinner'),
            'couple_dine' => $query->where('dine_types.slug', 'couple-dine'),
            default => $query,
        };
    }

    public function applyBillStatusFilter(Builder $query, ReportFilters $filters): Builder
    {
        return match ($filters->billStatus) {
            'paid' => $query->where('bills.status', 'settled')
                ->whereRaw('NOT (
                    COALESCE(payment_totals.nc_amount, 0) > 0
                    AND COALESCE(payment_totals.cash_amount, 0) = 0
                    AND COALESCE(payment_totals.upi_amount, 0) = 0
                    AND COALESCE(payment_totals.card_amount, 0) = 0
                )'),
            'unpaid' => $query->where('bills.status', 'open')
                ->whereRaw('COALESCE(payment_totals.nc_amount, 0) = 0'),
            'non_chargeable' => $query->where('payment_totals.nc_amount', '>', 0)
                ->whereRaw('COALESCE(payment_totals.cash_amount, 0) = 0')
                ->whereRaw('COALESCE(payment_totals.upi_amount, 0) = 0')
                ->whereRaw('COALESCE(payment_totals.card_amount, 0) = 0'),
            'credit', 'cancel' => $query->whereRaw('1 = 0'),
            default => $query,
        };
    }

    public function joinPaymentTotals(Builder $query): Builder
    {
        return $query->leftJoinSub(
            $this->paymentTotals->subquery(),
            'payment_totals',
            'payment_totals.bill_id',
            '=',
            'bills.id',
        );
    }

    public function activeItemStatsSubquery(): Builder
    {
        return DB::table('order_items')
            ->where('status', '!=', OrderItemStatus::Cancelled->value)
            ->groupBy('order_id')
            ->selectRaw(
                'order_id,
                COUNT(*) AS product_count,
                SUM(quantity) AS total_quantity,
                SUM(packing_fee) AS packing_charges',
            );
    }

    /**
     * Dine-in quantity covers waiter dine-in, couple dine, and candle light
     * (legacy order_user_status IN 1, 2, 5, 6).
     *
     * @return list<string>
     */
    public function dineInSlugs(): array
    {
        return ['dine-in', 'couple-dine', 'candle-light-dinner'];
    }
}
