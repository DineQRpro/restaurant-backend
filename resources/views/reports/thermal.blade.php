@extends($layout)

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Reports')
@section('body-class', 'thermal-report-page')

@section('content')
    @php
        $totalPages = max(1, (int) ceil($totalRows / max(1, $perPage)));
        $colCount = $reportType === 'bill_summary' ? 4 : 4;
    @endphp

    <nav class="mb-4 text-sm text-[#898989] no-print" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route($dashboardRoute) }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li>Reports</li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">{{ $title }}</li>
        </ol>
    </nav>

    <section class="no-print overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" action="{{ route($filterRoute) }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="sdate">Starting Date &amp; Time</label>
                    <input type="datetime-local" id="sdate" name="sdate" value="{{ $range['sdate'] }}" max="{{ $todayMax }}" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    @error('sdate')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="edate">Ending Date &amp; Time</label>
                    <input type="datetime-local" id="edate" name="edate" value="{{ $range['edate'] }}" max="{{ $todayMax }}" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                </div>
                <button type="submit" name="filter" value="1" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">Filter</button>
                <a href="{{ route($filterRoute) }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">Clear</a>
                <button type="button" onclick="window.print()" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800" data-thermal-print>
                    Print
                </button>
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

        <div class="px-5 py-4">
            <div class="mb-3 flex items-center justify-between text-sm font-semibold text-[#1f2f3e]">
                <span>Total Records: {{ $totalRows }}</span>
                @if ($isFiltered && $totalPages > 1)
                    <span class="text-[#898989]">Page {{ $page }} of {{ $totalPages }}</span>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[640px] w-full text-left text-sm">
                    <thead class="bg-[#16324f] text-white">
                        @if ($reportType === 'bill_summary')
                            <tr>
                                <th class="px-4 py-3 font-semibold">#</th>
                                <th class="px-4 py-3 font-semibold">Bill No</th>
                                <th class="px-4 py-3 font-semibold text-right">Amount</th>
                                <th class="px-4 py-3 font-semibold text-right">Tax</th>
                            </tr>
                        @else
                            <tr>
                                <th class="px-4 py-3 font-semibold">Sl.</th>
                                <th class="px-4 py-3 font-semibold">Item Name</th>
                                <th class="px-4 py-3 font-semibold text-right">Qty</th>
                                <th class="px-4 py-3 font-semibold text-right">Amount</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @forelse ($pageRows as $index => $row)
                            <tr class="border-t border-[#eef1f5]">
                                <td class="px-4 py-3">{{ ($isFiltered ? (($page - 1) * $perPage) : 0) + $index + 1 }}.</td>
                                @if ($reportType === 'bill_summary')
                                    <td class="px-4 py-3">{{ $row->bill_number }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $row->bill_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $row->tax_amount, 2) }}</td>
                                @else
                                    <td class="px-4 py-3">{{ $row->item_name }}</td>
                                    <td class="px-4 py-3 text-right">{{ $row->quantity_served }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format((float) $row->item_amount, 2) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $colCount }}" class="px-4 py-8 text-center text-[#898989]">No records in this period.</td>
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
                    <a class="rounded-md border border-[#dfdfdf] px-3 py-1 {{ $page <= 1 ? 'pointer-events-none opacity-40' : '' }}" href="{{ route($filterRoute, array_merge($queryBase, ['page' => max(1, $page - 1)])) }}">Prev</a>
                    <a class="rounded-md border border-[#dfdfdf] px-3 py-1 {{ $page >= $totalPages ? 'pointer-events-none opacity-40' : '' }}" href="{{ route($filterRoute, array_merge($queryBase, ['page' => min($totalPages, $page + 1)])) }}">Next</a>
                </nav>
            @endif
        </div>
    </section>

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
                <div class="col-sl">Sl</div>
                <div class="col-name">Item</div>
                <div class="col-qty">Qty</div>
                <div class="col-amt">Amt</div>
            </div>
            <hr>
            @forelse ($rows as $index => $row)
                <div class="item">
                    <div class="col-sl">{{ $index + 1 }}</div>
                    <div class="col-name">{{ $row->item_name }}</div>
                    <div class="col-qty">{{ $row->quantity_served }}</div>
                    <div class="col-amt">{{ number_format((float) $row->item_amount, 2) }}</div>
                </div>
            @empty
                <div class="center">No records in this period.</div>
            @endforelse
        @endif
    </div>
@endsection

@push('styles')
    <style>
        #thermal-print-root { display: none; }
        #thermal-print-root .center { text-align: center; }
        #thermal-print-root .bold { font-weight: bold; }
        #thermal-print-root hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
        #thermal-print-root .thermal-summary-line { display: flex; justify-content: space-between; margin: 2px 0; }
        #thermal-print-root .item { display: flex; margin: 1px 0; }
        #thermal-print-root .col-sl { width: 10%; }
        #thermal-print-root .col-amt { width: 22%; text-align: right; }
        #thermal-print-root .col-tax { width: 22%; text-align: right; }
        #thermal-print-root .col-billno { width: 28%; }
        #thermal-print-root .col-name { width: 42%; }
        #thermal-print-root .col-qty { width: 14%; text-align: center; }
        #thermal-print-root .big { font-size: 14px; font-weight: bold; }
        #thermal-print-root .meta { margin: 2px 0; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body.thermal-report-page aside,
            body.thermal-report-page header,
            body.thermal-report-page nav,
            body.thermal-report-page .no-print,
            #admin-sidebar,
            #counter-sidebar,
            #admin-sidebar-backdrop,
            #counter-sidebar-backdrop {
                display: none !important;
                visibility: hidden !important;
            }
            body * { visibility: hidden !important; }
            #thermal-print-root, #thermal-print-root * { visibility: visible !important; }
            #thermal-print-root {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 74mm;
                padding: 5mm 0 0 4mm;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 13px;
                font-weight: 600;
                color: #000;
            }
        }
    </style>
@endpush
