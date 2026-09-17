@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')

@section('content')
    @php
        $recordCount = $totals['count'] ?? ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows->total() : $rows->count());
    @endphp

    <x-report-page :dashboard-route="$dashboardRoute" :title="$title">
        <x-slot:toolbar>
            @include('reports.partials.filters')
        </x-slot>

        <div class="report-record-count">Total Records: {{ $recordCount }}</div>
        <div class="report-table-wrap">
            <table class="report-table" style="min-width: 1100px">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bill No</th>
                        <th>Order No</th>
                        <th class="text-right">Bill Summary</th>
                        <th class="text-right">Discount</th>
                        <th>Disc. Type</th>
                        <th>Disc.Comments</th>
                        <th class="text-right">GST</th>
                        <th class="text-right">Additional Charges</th>
                        <th class="text-right">Grand Total</th>
                        <th>Payment Type</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y g:i A') }}</td>
                            <td>{{ $row->bill_number }}</td>
                            <td>{{ $row->order_number }}</td>
                            <td class="text-right">{{ number_format((float) $row->bill_summary, 2) }}</td>
                            <td class="text-right">{{ $row->discount_amount === null ? '-' : number_format((float) $row->discount_amount, 2) }}</td>
                            <td>{{ $row->discount_name ?: '-' }}</td>
                            <td>{{ $row->other_discount_comment ?: '-' }}</td>
                            <td class="text-right">{{ number_format((float) $row->gst, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->additional_charges, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->grand_total, 2) }}</td>
                            <td>{{ $row->payment_type }}</td>
                            <td>{{ $row->payment_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="report-empty">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($recordCount > 0)
                    <tfoot>
                        <tr class="report-total-row">
                            <td colspan="3">Totals</td>
                            <td class="text-right">{{ number_format((float) $totals['grand_total'], 2) }}</td>
                            <td class="text-right">{{ number_format((float) $totals['discount_total'], 2) }}</td>
                            <td colspan="2"></td>
                            <td class="text-right">{{ number_format((float) $totals['tax_total'], 2) }}</td>
                            <td class="text-right">0.00</td>
                            <td class="text-right">{{ number_format((float) $totals['grand_total'], 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->hasPages())
            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </x-report-page>
@endsection
