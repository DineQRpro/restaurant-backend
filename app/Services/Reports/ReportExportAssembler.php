<?php

namespace App\Services\Reports;

use App\Enums\DietaryType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportExportAssembler
{
    /**
     * @param  Collection<int, object>  $rows
     * @param  array{count: int, grand_total: float, discount_total: float, tax_total: float}  $totals
     */
    public function transaction(string $title, ReportFilters $filters, Collection $rows, array $totals): ReportExportDocument
    {
        return new ReportExportDocument(
            title: $title,
            slug: 'transaction-report',
            headers: ['Date', 'Bill No', 'Order No', 'Bill Summary', 'Discount', 'Disc. Type', 'Disc.Comments', 'GST', 'Additional Charges', 'Grand Total', 'Payment Type', 'Payment Status'],
            rows: $rows->map(fn (object $row): array => [
                $this->dateTime($row->report_date),
                $row->bill_number,
                $row->order_number,
                $row->bill_summary,
                $row->discount_amount,
                $row->discount_name,
                $row->other_discount_comment,
                $row->gst,
                $row->additional_charges,
                $row->grand_total,
                $row->payment_type,
                $row->payment_status,
            ])->all(),
            totals: ['Totals', '', '', $totals['grand_total'], $totals['discount_total'], '', '', $totals['tax_total'], 0, $totals['grand_total'], '', ''],
            meta: $this->meta($filters),
            moneyColumns: [3, 4, 7, 8, 9],
            landscape: true,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public function billStatus(ReportFilters $filters, Collection $rows): ReportExportDocument
    {
        return new ReportExportDocument(
            title: 'Bill Status Report',
            slug: 'bill-status-report',
            headers: ['Date', 'Bill No', 'Order No', 'Order Type', 'Waiter Name', 'Waiter Id', 'Bill Summary', 'Bill Status'],
            rows: $rows->map(fn (object $row): array => [
                $this->dateTime($row->report_date),
                $row->bill_number,
                $row->order_number,
                $row->order_type,
                $row->waiter_name,
                $row->waiter_id,
                $row->bill_summary,
                $row->bill_status,
            ])->all(),
            meta: $this->meta($filters, extra: $filters->billStatus ? 'Bill Status: '.$filters->billStatus : null),
            moneyColumns: [6],
            integerColumns: [5],
            landscape: true,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<string, float|int>  $totals
     */
    public function paymentMode(ReportFilters $filters, Collection $rows, array $totals): ReportExportDocument
    {
        return new ReportExportDocument(
            title: 'Payment Mode Report',
            slug: 'payment-mode-report',
            headers: ['Date', 'Total Bill', 'Total Orders', 'Order Value', 'Cash', 'UPI', 'Card', 'Credit', 'Non Chargeable', 'Cancel', 'Net Sales'],
            rows: $rows->map(fn (object $row): array => [
                $this->date($row->report_date),
                $row->total_bills,
                $row->total_orders,
                $row->order_value,
                $row->cash_value,
                $row->upi_value,
                $row->card_value,
                $row->credit_value,
                $row->nc_value,
                $row->cancel_value,
                $row->net_sales,
            ])->all(),
            totals: [
                'Totals',
                $totals['total_bills'],
                $totals['total_orders'],
                $totals['order_value'],
                $totals['cash_value'],
                $totals['upi_value'],
                $totals['card_value'],
                $totals['credit_value'],
                $totals['nc_value'],
                $totals['cancel_value'],
                $totals['net_sales'],
            ],
            meta: $this->meta($filters),
            moneyColumns: [3, 4, 5, 6, 7, 8, 9, 10],
            integerColumns: [1, 2],
            landscape: true,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<string, float|int>  $totals
     */
    public function waiter(ReportFilters $filters, Collection $rows, array $totals): ReportExportDocument
    {
        return new ReportExportDocument(
            title: 'Waiter Report',
            slug: 'waiter-report',
            headers: ['Date', 'Waiter Name', 'Waiter Id', 'Total Orders', 'Order Value', 'Cash', 'UPI', 'Card', 'Credit', 'Non Chargeable', 'Cancel', 'Net Sales'],
            rows: $rows->map(fn (object $row): array => [
                $this->date($row->report_date),
                $row->waiter_name,
                $row->waiter_id,
                $row->total_orders,
                $row->order_value,
                $row->cash_value,
                $row->upi_value,
                $row->card_value,
                $row->credit_value,
                $row->nc_value,
                $row->cancel_value,
                $row->net_sales,
            ])->all(),
            totals: [
                'Totals',
                '',
                '',
                $totals['total_orders'],
                $totals['order_value'],
                $totals['cash_value'],
                $totals['upi_value'],
                $totals['card_value'],
                $totals['credit_value'],
                $totals['nc_value'],
                $totals['cancel_value'],
                $totals['net_sales'],
            ],
            meta: $this->meta($filters),
            moneyColumns: [4, 5, 6, 7, 8, 9, 10, 11],
            integerColumns: [2, 3],
            landscape: true,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{count: int, grand_total: float, packing_charges: float, total_quantity: float}  $totals
     */
    public function orderWise(ReportFilters $filters, Collection $rows, array $totals): ReportExportDocument
    {
        return new ReportExportDocument(
            title: 'Order Wise Report',
            slug: 'order-wise-report',
            headers: ['Date', 'Order No', 'Bill No', 'Order Type', 'Payment Mode', 'Bill Summary', 'Total Items', 'Total Quantity', 'Packing Charges', 'PAX', 'PAX Average'],
            rows: $rows->map(fn (object $row): array => [
                $this->dateTime($row->report_date),
                $row->order_number,
                $row->bill_number,
                $row->order_type,
                $row->payment_mode,
                $row->bill_summary,
                $row->product_count,
                $row->total_quantity,
                $row->packing_charges,
                $row->pax,
                $row->pax_average,
            ])->all(),
            totals: ['Totals', '', '', '', '', $totals['grand_total'], '', $totals['total_quantity'], $totals['packing_charges'], '', ''],
            meta: $this->meta($filters, extra: $this->joinedExtras([
                $filters->billNo ? 'Bill No: '.$filters->billNo : null,
                $filters->orderType ? 'Order Type: '.$filters->orderType : null,
            ])),
            moneyColumns: [5, 8, 10],
            integerColumns: [6, 7, 9],
            landscape: true,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{count: int, total_quantity: float, total_amount: float}  $totals
     */
    public function itemWise(string $title, ReportFilters $filters, Collection $rows, array $totals): ReportExportDocument
    {
        return new ReportExportDocument(
            title: $title,
            slug: str_contains(strtolower($title), 'product') ? 'product-wise-report' : 'item-wise-report',
            headers: ['Item Name', 'Main Category', 'Category', 'Dine-In Quantity', 'Take Away Quantity', 'Total Quantity', 'Price per Item', 'Total Amount', 'Date'],
            rows: $rows->map(fn (object $row): array => [
                $row->item_name,
                DietaryType::tryFrom((string) $row->main_category)?->label() ?? $row->main_category,
                $row->category,
                $row->dine_in_quantity,
                $row->take_away_quantity,
                $row->total_quantity,
                $row->price_per_item,
                $row->total_amount,
                $this->dateTime($row->report_date),
            ])->all(),
            totals: ['Totals', '', '', '', '', $totals['total_quantity'], '', $totals['total_amount'], ''],
            meta: $this->meta($filters, extra: $filters->itemName ? 'Item: '.$filters->itemName : null),
            moneyColumns: [6, 7],
            integerColumns: [3, 4, 5],
            landscape: true,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array{count: int, total_bill: float, visit_count: int}  $totals
     */
    public function customer(string $title, ReportFilters $filters, Collection $rows, array $totals): ReportExportDocument
    {
        return new ReportExportDocument(
            title: $title,
            slug: 'customer-report',
            headers: ['Customer Name', 'Mobile Number', 'Number of Visits', 'Last Visit Date', 'Total Bill', 'Bill Average'],
            rows: $rows->map(fn (object $row): array => [
                $row->customer_name,
                $row->customer_mobile,
                $row->visit_count,
                $this->date($row->last_visit_date),
                $row->total_bill,
                $row->bill_average,
            ])->all(),
            totals: ['Totals', '', $totals['visit_count'], '', $totals['total_bill'], ''],
            meta: $this->meta($filters),
            moneyColumns: [4, 5],
            integerColumns: [2],
            landscape: false,
        );
    }

    /**
     * @return list<string>
     */
    private function meta(ReportFilters $filters, ?string $extra = null): array
    {
        $lines = [];

        if ($filters->showAll) {
            $lines[] = 'Date range: All';
        } elseif ($filters->from !== null && $filters->to !== null) {
            $lines[] = 'Date range: '.$filters->from->format('Y-m-d H:i').' to '.$filters->to->format('Y-m-d H:i');
        }

        if ($extra) {
            $lines[] = $extra;
        }

        $lines[] = 'Generated: '.now()->format('Y-m-d H:i');

        return $lines;
    }

    /**
     * @param  list<?string>  $parts
     */
    private function joinedExtras(array $parts): ?string
    {
        $parts = array_values(array_filter($parts));

        return $parts === [] ? null : implode(' | ', $parts);
    }

    private function dateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse((string) $value)->format('F j, Y g:i A');
    }

    private function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse((string) $value)->format('M d, Y');
    }
}
