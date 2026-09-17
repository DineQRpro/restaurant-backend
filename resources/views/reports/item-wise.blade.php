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
            <table class="report-table" style="min-width: 960px">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Main Category</th>
                        <th>Category</th>
                        <th class="text-right">Dine-In Quantity</th>
                        <th class="text-right">Take Away Quantity</th>
                        <th class="text-right">Total Quantity</th>
                        <th class="text-right">Price per Item</th>
                        <th class="text-right">Total Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $dietary = \App\Enums\DietaryType::tryFrom((string) $row->main_category);
                        @endphp
                        <tr>
                            <td>{{ $row->item_name }}</td>
                            <td>{{ $dietary?->label() ?? $row->main_category }}</td>
                            <td>{{ $row->category }}</td>
                            <td class="text-right">{{ $row->dine_in_quantity }}</td>
                            <td class="text-right">{{ $row->take_away_quantity }}</td>
                            <td class="text-right">{{ $row->total_quantity }}</td>
                            <td class="text-right">{{ number_format((float) $row->price_per_item, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $row->total_amount, 2) }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('F j, Y \a\t g:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="report-empty">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($recordCount > 0)
                    <tfoot>
                        <tr class="report-total-row">
                            <td colspan="5">Totals</td>
                            <td class="text-right">{{ $totals['total_quantity'] }}</td>
                            <td></td>
                            <td class="text-right">{{ number_format($totals['total_amount'], 2) }}</td>
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
