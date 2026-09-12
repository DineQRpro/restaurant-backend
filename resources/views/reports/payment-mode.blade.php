@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')

@section('content')
    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route($dashboardRoute) }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">{{ $title }}</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            @include('reports.partials.filters')
        </div>

        <div class="px-5 py-4">
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-left text-sm">
                    <thead class="bg-[#16324f] text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">#</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Bill</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Orders</th>
                            <th class="px-4 py-3 font-semibold text-right">Order Value</th>
                            <th class="px-4 py-3 font-semibold text-right">Cash</th>
                            <th class="px-4 py-3 font-semibold text-right">UPI</th>
                            <th class="px-4 py-3 font-semibold text-right">Card</th>
                            <th class="px-4 py-3 font-semibold text-right">Credit</th>
                            <th class="px-4 py-3 font-semibold text-right">Non Chargeable</th>
                            <th class="px-4 py-3 font-semibold text-right">Cancel</th>
                            <th class="px-4 py-3 font-semibold text-right">Net Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $index => $row)
                            <tr class="border-t border-[#eef1f5]">
                                <td class="px-4 py-3">{{ $index + 1 }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->total_bills }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->total_orders }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->order_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->cash_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->upi_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->card_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->credit_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->nc_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->cancel_value, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->net_sales, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-8 text-center text-[#898989]">{{ $emptyMessage }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-[#f4f6f9] font-semibold">
                            <tr>
                                <td class="px-4 py-3" colspan="2">Totals</td>
                                <td class="px-4 py-3 text-right">{{ $totals['total_bills'] }}</td>
                                <td class="px-4 py-3 text-right">{{ $totals['total_orders'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['order_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['cash_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['upi_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['card_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['credit_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['nc_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['cancel_value'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['net_sales'], 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </section>
@endsection
