<?php

namespace App\Services\Reports;

use App\Enums\OrderItemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ThermalReportService
{
    public const PER_PAGE = 50;

    public function __construct(
        private readonly ReportDateRange $dates,
    ) {}

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Collection<int, object{bill_number: string, bill_amount: float|string, tax_amount: float|string}>
     */
    public function billSummary(array $range): Collection
    {
        $column = $this->dates->billTimestampSql('bills');

        return DB::table('bills')
            ->selectRaw('bills.bill_number, bills.grand_total AS bill_amount, bills.tax_amount')
            ->whereRaw("{$column} >= ? AND {$column} <= ?", [
                $range['from']->format('Y-m-d H:i:s'),
                $range['to']->format('Y-m-d H:i:s'),
            ])
            ->orderByRaw($column.' ASC')
            ->orderBy('bills.id')
            ->get();
    }

    /**
     * Non-cancelled order items for bills in the window.
     * Amount uses historical order_items.unit_price * quantity.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Collection<int, object{item_name: string, quantity_served: int|float|string, item_amount: float|string}>
     */
    public function itemSummary(array $range): Collection
    {
        $column = $this->dates->billTimestampSql('bills');

        return DB::table('bills')
            ->join('orders', 'orders.id', '=', 'bills.order_id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('order_items.status', '!=', OrderItemStatus::Cancelled->value)
            ->whereRaw("{$column} >= ? AND {$column} <= ?", [
                $range['from']->format('Y-m-d H:i:s'),
                $range['to']->format('Y-m-d H:i:s'),
            ])
            ->groupBy('menu_items.id', 'menu_items.name')
            ->selectRaw(
                "COALESCE(menu_items.name, 'Unknown Item') AS item_name,
                SUM(order_items.quantity) AS quantity_served,
                SUM(order_items.quantity * order_items.unit_price) AS item_amount",
            )
            ->orderByDesc('quantity_served')
            ->orderBy('item_name')
            ->get();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{total_bills: int, total_bill_amount: float, total_tax_amount: float}
     */
    public function billSummaryTotals(Collection $rows): array
    {
        return [
            'total_bills' => $rows->count(),
            'total_bill_amount' => round((float) $rows->sum(fn (object $row): float => (float) $row->bill_amount), 2),
            'total_tax_amount' => round((float) $rows->sum(fn (object $row): float => (float) $row->tax_amount), 2),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{item_count: int, quantity_sold: float, total_amount: float}
     */
    public function itemSummaryTotals(Collection $rows): array
    {
        return [
            'item_count' => $rows->count(),
            'quantity_sold' => (float) $rows->sum(fn (object $row): float => (float) $row->quantity_served),
            'total_amount' => round((float) $rows->sum(fn (object $row): float => (float) $row->item_amount), 2),
        ];
    }
}
