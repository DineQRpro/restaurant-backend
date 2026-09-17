@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')
@section('body-class', 'thermal-report-page')

@section('content')
    @php
        $totalPages = max(1, (int) ceil($totalRows / max(1, $perPage)));
        $colCount = 4;
    @endphp

    <div class="report-page no-print">
        <nav class="report-breadcrumb no-print" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ route($dashboardRoute) }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
                <li aria-hidden="true">/</li>
                <li>Reports</li>
                <li aria-hidden="true">/</li>
                <li class="font-semibold text-[#1f2f3e]">{{ $title }}</li>
            </ol>
        </nav>

        <section class="report-card no-print">
            <div class="report-toolbar">
                <form method="GET" action="{{ route($filterRoute) }}" class="report-filters">
                    <div class="report-field">
                        <label for="sdate">Starting Date &amp; Time</label>
                        <input type="datetime-local" id="sdate" name="sdate" value="{{ $range['sdate'] }}" max="{{ $todayMax }}">
                        @error('sdate')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="report-field">
                        <label for="edate">Ending Date &amp; Time</label>
                        <input type="datetime-local" id="edate" name="edate" value="{{ $range['edate'] }}" max="{{ $todayMax }}">
                    </div>
                    <div class="report-actions thermal-print-actions">
                        <button type="submit" name="filter" value="1" class="report-btn report-btn-primary">Filter</button>
                        <a href="{{ route($filterRoute) }}" class="report-btn report-btn-secondary">Clear</a>
                        <button type="button" class="report-btn report-btn-print" data-thermal-print onclick="window.print()">Print</button>
                    </div>
                </form>
                <p class="mt-2 text-xs text-[#898989]">
                    @if ($isFiltered)
                        Showing filtered range:
                    @else
                        Default range (last 36 hours):
                    @endif
                    {{ $range['from_display'] }} → {{ $range['to_display'] }}
                </p>
            </div>

            <div class="report-body">
                <div class="report-record-count">
                    <span>Total Records: {{ $totalRows }}</span>
                    @if ($isFiltered && $totalPages > 1)
                        <span class="ml-3 text-[#898989]">Page {{ $page }} of {{ $totalPages }}</span>
                    @endif
                </div>
                <div class="report-table-wrap">
                    <table class="report-table report-table--thermal">
                        <thead>
                            @if ($reportType === 'bill_summary')
                                <tr>
                                    <th>#</th>
                                    <th>Bill No</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Tax</th>
                                </tr>
                            @else
                                <tr>
                                    <th>Sl.</th>
                                    <th>Item Name</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody>
                            @forelse ($pageRows as $index => $row)
                                <tr>
                                    <td>{{ ($isFiltered ? (($page - 1) * $perPage) : 0) + $index + 1 }}.</td>
                                    @if ($reportType === 'bill_summary')
                                        <td>{{ $row->bill_number }}</td>
                                        <td class="text-right">{{ number_format((float) $row->bill_amount, 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $row->tax_amount, 2) }}</td>
                                    @else
                                        <td>{{ $row->item_name }}</td>
                                        <td class="text-right">{{ $row->quantity_served }}</td>
                                        <td class="text-right">{{ number_format((float) $row->item_amount, 2) }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $colCount }}" class="report-empty">No records in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($reportType === 'items_served')
                    <div class="mt-4 space-y-1 text-sm font-semibold text-[#1f2f3e]">
                        <div>No. of Items Sold: {{ $totals['item_count'] }}</div>
                        <div>No. of Qty Sold: {{ $totals['quantity_sold'] }}</div>
                        <div>Total Amount: {{ number_format($totals['total_amount'], 2) }}</div>
                    </div>
                @endif

                @if ($isFiltered && $totalPages > 1)
                    <nav class="mt-4 flex justify-end gap-2 text-sm">
                        @php
                            $queryBase = ['sdate' => $range['sdate'], 'edate' => $range['edate'], 'filter' => '1'];
                        @endphp
                        <a class="report-btn report-btn-secondary {{ $page <= 1 ? 'pointer-events-none opacity-40' : '' }}" href="{{ route($filterRoute, array_merge($queryBase, ['page' => max(1, $page - 1)])) }}">Prev</a>
                        <a class="report-btn report-btn-secondary {{ $page >= $totalPages ? 'pointer-events-none opacity-40' : '' }}" href="{{ route($filterRoute, array_merge($queryBase, ['page' => min($totalPages, $page + 1)])) }}">Next</a>
                    </nav>
                @endif
            </div>
        </section>
    </div>

    <div id="thermal-print-root" class="thermal-print-report">
        <div class="center big">Varsity Kitchen</div>
        <hr>
        <div class="center bold big">{{ $title }}</div>
        <hr>
        <div class="meta">Report Date: {{ $range['report_date_display'] }}</div>
        <div class="meta">From: {{ $range['from_display'] }}</div>
        <div class="meta">To: {{ $range['to_display'] }}</div>
        <hr>

        @if ($reportType === 'bill_summary')
            <div class="thermal-summary-line bold">
                <span class="thermal-summary-label">Total Bills:</span>
                <span class="thermal-summary-value">{{ $totals['total_bills'] }}</span>
            </div>
            <div class="thermal-summary-line bold">
                <span class="thermal-summary-label">Total Bill Amount:</span>
                <span class="thermal-summary-value">{{ number_format($totals['total_bill_amount'], 2) }}</span>
            </div>
            <div class="thermal-summary-line bold">
                <span class="thermal-summary-label">Total Tax Amount:</span>
                <span class="thermal-summary-value">{{ number_format($totals['total_tax_amount'], 2) }}</span>
            </div>
            <hr>
            <div class="item bold">
                <div class="col-sl">Sl</div>
                <div class="col-billno">Bill No</div>
                <div class="col-amt">Amount</div>
                <div class="col-tax">Tax</div>
            </div>
            <hr>
            @forelse ($rows as $index => $row)
                <div class="item">
                    <div class="col-sl">{{ $index + 1 }}</div>
                    <div class="col-billno">{{ $row->bill_number }}</div>
                    <div class="col-amt">{{ number_format((float) $row->bill_amount, 2) }}</div>
                    <div class="col-tax">{{ number_format((float) $row->tax_amount, 2) }}</div>
                </div>
            @empty
                <div class="center">No bills in this period.</div>
            @endforelse
        @else
            <div class="thermal-summary-line bold">
                <span class="thermal-summary-label">No. of Items Sold:</span>
                <span class="thermal-summary-value">{{ $totals['item_count'] }}</span>
            </div>
            <div class="thermal-summary-line bold">
                <span class="thermal-summary-label">No. of Qty Sold:</span>
                <span class="thermal-summary-value">{{ $totals['quantity_sold'] }}</span>
            </div>
            <div class="thermal-summary-line bold">
                <span class="thermal-summary-label">Total Amount:</span>
                <span class="thermal-summary-value">{{ number_format($totals['total_amount'], 2) }}</span>
            </div>
            <hr>
            <div class="item bold">
                <div class="col-sl">Sl.</div>
                <div class="col-name">Item Name</div>
                <div class="col-qty">Qty</div>
                <div class="col-amt">Amount</div>
            </div>
            <hr>
            @forelse ($rows as $index => $row)
                <div class="item">
                    <div class="col-sl">{{ $index + 1 }}.</div>
                    <div class="col-name">{{ $row->item_name }}</div>
                    <div class="col-qty">{{ $row->quantity_served }}</div>
                    <div class="col-amt">{{ number_format((float) $row->item_amount, 2) }}</div>
                </div>
            @empty
                <div class="center">No items served in this period.</div>
            @endforelse
        @endif

        <hr>
        <div class="center">*** End of Report ***</div>
    </div>
@endsection
