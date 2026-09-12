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
                <table class="min-w-[960px] w-full text-left text-sm">
                    <thead class="bg-[#16324f] text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Item Name</th>
                            <th class="px-4 py-3 font-semibold">Main Category</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold text-right">Dine-In Quantity</th>
                            <th class="px-4 py-3 font-semibold text-right">Take Away Quantity</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Quantity</th>
                            <th class="px-4 py-3 font-semibold text-right">Price per Item</th>
                            <th class="px-4 py-3 font-semibold text-right">Total Amount</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $dietary = \App\Enums\DietaryType::tryFrom((string) $row->main_category);
                            @endphp
                            <tr class="border-t border-[#eef1f5]">
                                <td class="px-4 py-3">{{ $row->item_name }}</td>
                                <td class="px-4 py-3">{{ $dietary?->label() ?? $row->main_category }}</td>
                                <td class="px-4 py-3">{{ $row->category }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->dine_in_quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->take_away_quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ $row->total_quantity }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->price_per_item, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->total_amount, 2) }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y \a\t g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-[#898989]">{{ $emptyMessage }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($recordCount > 0)
                        <tfoot class="bg-[#f4f6f9] font-semibold">
                            <tr>
                                <td class="px-4 py-3" colspan="5">Totals</td>
                                <td class="px-4 py-3 text-right">{{ $totals['total_quantity'] }}</td>
                                <td></td>
                                <td class="px-4 py-3 text-right">{{ number_format($totals['total_amount'], 2) }}</td>
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
