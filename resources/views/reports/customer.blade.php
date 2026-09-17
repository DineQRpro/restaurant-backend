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
            <table class="report-table" style="min-width: 880px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer Name</th>
                        <th>Mobile Number</th>
                        <th class="text-right">Number of Visits</th>
                        <th>Last Visit Date</th>
                        <th class="text-right">Total Bill</th>
                        <th class="text-right">Bill Average</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows->firstItem() + $index : $index + 1 }}</td>
                            <td>{{ $row->customer_name }}</td>
                            <td>{{ $row->customer_mobile }}</td>
                            <td class="text-right">{{ $row->visit_count }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->last_visit_date)->format('M d, Y') }}</td>
                            <td class="text-right">{{ number_format((float) $row->total_bill, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->bill_average, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="report-empty">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($recordCount > 0)
                    <tfoot>
                        <tr class="report-total-row">
                            <td colspan="3">Totals</td>
                            <td class="text-right">{{ $totals['visit_count'] }}</td>
                            <td></td>
                            <td class="text-right">{{ number_format($totals['total_bill'], 2) }}</td>
                            <td></td>
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
