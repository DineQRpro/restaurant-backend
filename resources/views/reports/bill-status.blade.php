@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')

@section('content')
    <x-report-page :dashboard-route="$dashboardRoute" :title="$title">
        <x-slot:toolbar>
            @include('reports.partials.filters')
        </x-slot>

        <div class="report-record-count">Total Records: {{ $recordCount }}</div>
        <div class="report-table-wrap">
            <table class="report-table" style="min-width: 960px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Bill No</th>
                        <th>Order No</th>
                        <th>Order Type</th>
                        <th>Waiter Name</th>
                        <th>Waiter Id</th>
                        <th class="text-right">Bill Summary</th>
                        <th>Bill Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows->firstItem() + $index : $index + 1 }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y g:i A') }}</td>
                            <td>{{ $row->bill_number }}</td>
                            <td>{{ $row->order_number }}</td>
                            <td>{{ $row->order_type }}</td>
                            <td>{{ $row->waiter_name }}</td>
                            <td>{{ $row->waiter_id }}</td>
                            <td class="text-right">{{ number_format((float) $row->bill_summary, 2) }}</td>
                            <td>{{ $row->bill_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="report-empty">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->hasPages())
            <div class="mt-4">{{ $rows->links() }}</div>
        @endif
    </x-report-page>
@endsection
