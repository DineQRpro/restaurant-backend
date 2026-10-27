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
                        <th>Order No</th>
                        <th>Bill No</th>
                        <th>Order Type</th>
                        <th>Payment Mode</th>
                        <th class="text-right">Bill Summary</th>
                        <th class="text-right">Total Items</th>
                        <th class="text-right">Total Quantity</th>
                        <th class="text-right">Packing Charges</th>
                        <th class="text-right">PAX</th>
                        <th class="text-right">PAX Average</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y g:i A') }}</td>
                            <td>{{ $row->order_number }}</td>
                            <td>{{ $row->bill_number }}</td>
                            <td>{{ $row->order_type }}</td>
                            <td>{{ $row->payment_mode }}</td>
                            <td class="text-right">{{ number_format((float) $row->bill_summary, 2) }}</td>
                            <td class="text-right">{{ $row->product_count }}</td>
                            <td class="text-right">{{ $row->total_quantity }}</td>
                            <td class="text-right">{{ number_format((float) $row->packing_charges, 2) }}</td>
                            <td class="text-right">{{ $row->pax }}</td>
                            <td class="text-right">{{ $row->pax_average === null ? '' : number_format((float) $row->pax_average, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="report-empty">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($recordCount > 0)
                    <tfoot>
                        <tr class="report-total-row">
                            <td colspan="5">Totals</td>
                            <td class="text-right">{{ number_format($totals['grand_total'], 2) }}</td>
                            <td></td>
                            <td class="text-right">{{ $totals['total_quantity'] }}</td>
                            <td class="text-right">{{ number_format($totals['packing_charges'], 2) }}</td>
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
