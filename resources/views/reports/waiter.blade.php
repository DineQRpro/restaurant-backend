@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')

@section('content')
    <x-report-page :dashboard-route="$dashboardRoute" :title="$title">
        <x-slot:toolbar>
            @include('reports.partials.filters')
        </x-slot>

        <div class="report-table-wrap">
            <table class="report-table" style="min-width: 1100px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Waiter Name</th>
                        <th>Waiter Id</th>
                        <th class="text-right">Total Orders</th>
                        <th class="text-right">Order Value</th>
                        <th class="text-right">Cash</th>
                        <th class="text-right">UPI</th>
                        <th class="text-right">Card</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Non Chargeable</th>
                        <th class="text-right">Cancel</th>
                        <th class="text-right">Net Sales</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('M d, Y') }}</td>
                            <td>{{ $row->waiter_name }}</td>
                            <td>{{ $row->waiter_id }}</td>
                            <td class="text-right">{{ $row->total_orders }}</td>
                            <td class="text-right">{{ number_format((float) $row->order_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->cash_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->upi_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->card_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->credit_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->nc_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->cancel_value, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->net_sales, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="report-empty">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="report-total-row">
                            <td colspan="4">Totals</td>
                            <td class="text-right">{{ $totals['total_orders'] }}</td>
                            <td class="text-right">{{ number_format($totals['order_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['cash_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['upi_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['card_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['credit_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['nc_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['cancel_value'], 2) }}</td>
                            <td class="text-right">{{ number_format($totals['net_sales'], 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-report-page>
@endsection
