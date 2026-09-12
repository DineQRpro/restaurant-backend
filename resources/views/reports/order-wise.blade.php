@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')

@section('content')
    @php
        $recordCount = $totals['count'] ?? ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows->total() : $rows->count());
    @endphp

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
            <div class="mb-3 text-sm font-semibold text-[#1f2f3e]">Total Records: {{ $recordCount }}</div>
            <div class="overflow-x-auto">
                <table class="min-w-[1100px] w-full text-left text-sm">
                    <thead class="bg-[#16324f] text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Order No</th>
                            <th class="px-4 py-3 font-semibold">Bill No</th>
                            <th class="px-4 py-3 font-semibold">Order Type</th>
                            <th class="px-4 py-3 font-semibold">Payment Mode</th>
                            <th class="px-4 py-3 font-semibold text-right">Bill Summary</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Items</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Quantity</th>
                            <th class="px-4 py-3 font-semibold text-right">Packing Charges</th>
                            <th class="px-4 py-3 font-semibold text-right">PAX</th>
                            <th class="px-4 py-3 font-semibold text-right">PAX Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-t border-[#eef1f5]">
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $row->order_number }}</td>
                                <td class="px-4 py-3">{{ $row->bill_number }}</td>
                                <td class="px-4 py-3">{{ $row->order_type }}</td>
                                <td class="px-4 py-3">{{ $row->payment_mode }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->bill_summary, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->product_count }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->total_quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->packing_charges, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->pax }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->pax_average, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-8 text-center text-[#898989]">{{ $emptyMessage }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($recordCount > 0)
                        <tfoot class="bg-[#f4f6f9] font-semibold">
                            <tr>
                                <td class="px-4 py-3" colspan="5">Totals</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['grand_total'], 2) }}</td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ $totals['total_quantity'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['packing_charges'], 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->hasPages())
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </div>
    </section>
@endsection
