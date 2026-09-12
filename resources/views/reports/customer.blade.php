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
                <table class="min-w-[880px] w-full text-left text-sm">
                    <thead class="bg-[#16324f] text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">#</th>
                            <th class="px-4 py-3 font-semibold">Customer Name</th>
                            <th class="px-4 py-3 font-semibold">Mobile Number</th>
                            <th class="px-4 py-3 font-semibold text-right">Number of Visits</th>
                            <th class="px-4 py-3 font-semibold">Last Visit Date</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Bill</th>
                            <th class="px-4 py-3 font-semibold text-right">Bill Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $index => $row)
                            <tr class="border-t border-[#eef1f5]">
                                <td class="px-4 py-3">{{ $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows->firstItem() + $index : $index + 1 }}</td>
                                <td class="px-4 py-3">{{ $row->customer_name }}</td>
                                <td class="px-4 py-3">{{ $row->customer_mobile }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->visit_count }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->last_visit_date)->format('M d, Y') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->total_bill, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->bill_average, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-[#898989]">{{ $emptyMessage }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($recordCount > 0)
                        <tfoot class="bg-[#f4f6f9] font-semibold">
                            <tr>
                                <td class="px-4 py-3" colspan="3">Totals</td>
                                <td class="px-4 py-3 text-right">{{ $totals['visit_count'] }}</td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['total_bill'], 2) }}</td>
                                <td></td>
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
