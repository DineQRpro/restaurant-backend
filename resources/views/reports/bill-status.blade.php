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
            <div class="mb-3 text-sm font-semibold text-[#1f2f3e]">Total Records: {{ $recordCount }}</div>
            <div class="overflow-x-auto">
                <table class="min-w-[960px] w-full text-left text-sm">
                    <thead class="bg-[#16324f] text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">#</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Bill No</th>
                            <th class="px-4 py-3 font-semibold">Order No</th>
                            <th class="px-4 py-3 font-semibold">Order Type</th>
                            <th class="px-4 py-3 font-semibold">Waiter Name</th>
                            <th class="px-4 py-3 font-semibold">Waiter Id</th>
                            <th class="px-4 py-3 font-semibold text-right">Bill Summary</th>
                            <th class="px-4 py-3 font-semibold">Bill Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $index => $row)
                            <tr class="border-t border-[#eef1f5]">
                                <td class="px-4 py-3">{{ $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows->firstItem() + $index : $index + 1 }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y g:i A') }}</td>
                                <td class="px-4 py-3">{{ $row->bill_number }}</td>
                                <td class="px-4 py-3">{{ $row->order_number }}</td>
                                <td class="px-4 py-3">{{ $row->order_type }}</td>
                                <td class="px-4 py-3">{{ $row->waiter_name }}</td>
                                <td class="px-4 py-3">{{ $row->waiter_id }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->bill_summary, 2) }}</td>
                                <td class="px-4 py-3">{{ $row->bill_status }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-[#898989]">{{ $emptyMessage }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->hasPages())
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </div>
    </section>
@endsection
