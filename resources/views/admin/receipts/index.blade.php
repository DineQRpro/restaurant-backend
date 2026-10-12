@extends('layouts.admin')

@section('title', 'Receipts')
@section('page-title', 'Receipts')
@section('page-subtitle', 'Receipt management')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user->hasPermission('receipts.create');
        $canUpdate = $user->hasPermission('receipts.update');
        $canPrint = $user->hasPermission('receipts.print');
        $hasFilters = ($filters['sdate'] ?? '') !== ''
            || ($filters['edate'] ?? '') !== ''
            || ($filters['receipt_no'] ?? '') !== '';
    @endphp

    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Receipts</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#1f2f3e]">Receipts</h2>
                <p class="text-sm text-[#898989]">Banquet and event receipts. These records are independent of dine-in bills and payments.</p>
            </div>
            @if ($canCreate)
                <a
                    href="{{ route('admin.receipts.create') }}"
                    class="inline-flex items-center justify-center rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]"
                >
                    Add New
                </a>
            @endif
        </div>

        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" action="{{ route('admin.receipts.index') }}" class="grid grid-cols-1 gap-3 lg:grid-cols-4">
                <div>
                    <label for="sdate" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">From Date</label>
                    <input
                        id="sdate"
                        type="date"
                        name="sdate"
                        value="{{ $filters['sdate'] ?? '' }}"
                        class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                    >
                </div>
                <div>
                    <label for="edate" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">To Date</label>
                    <input
                        id="edate"
                        type="date"
                        name="edate"
                        value="{{ $filters['edate'] ?? '' }}"
                        class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                    >
                </div>
                <div>
                    <label for="receipt_no" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Receipt Number</label>
                    <input
                        id="receipt_no"
                        type="text"
                        name="receipt_no"
                        value="{{ $filters['receipt_no'] ?? '' }}"
                        placeholder="Receipt No"
                        class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                    >
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Filter
                    </button>
                    <a href="{{ route('admin.receipts.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">Receipt No</th>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Customer Name</th>
                        <th class="px-4 py-3 font-semibold">Mobile</th>
                        <th class="px-4 py-3 font-semibold">PAX</th>
                        <th class="px-4 py-3 font-semibold">Advance Paid</th>
                        <th class="px-4 py-3 font-semibold">Balance</th>
                        <th class="px-4 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $index => $receipt)
                        <tr class="border-b border-[#eef1f5] last:border-0">
                            <td class="px-4 py-3 text-[#425466]">{{ $receipts->firstItem() + $index }}</td>
                            <td class="px-4 py-3 font-semibold text-[#1f2f3e]">{{ $receipt->receiptNumber() }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $receipt->receipt_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $receipt->customer_name }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $receipt->mobile }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $receipt->pax_count }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ number_format((float) $receipt->advance_paid, 2) }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ number_format((float) $receipt->balance, 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canUpdate)
                                        <a
                                            href="{{ route('admin.receipts.edit', $receipt) }}"
                                            class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a] hover:bg-[#04346a]/5"
                                        >
                                            Edit
                                        </a>
                                    @endif
                                    @if ($canPrint)
                                        <a
                                            href="{{ route('admin.receipts.print', $receipt) }}"
                                            target="_blank"
                                            class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a] hover:bg-[#04346a]/5"
                                        >
                                            Print Receipt
                                        </a>
                                    @endif
                                    @if (! $canUpdate && ! $canPrint)
                                        <span class="text-xs text-[#898989]">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center">
                                @if ($hasFilters)
                                    <p class="font-semibold text-[#1f2f3e]">No receipts found for the selected filters.</p>
                                @else
                                    <p class="font-semibold text-[#1f2f3e]">No receipts found.</p>
                                @endif
                                @if ($canCreate)
                                    <a href="{{ route('admin.receipts.create') }}" class="mt-3 inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                                        Add New
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($receipts->hasPages())
            <div class="border-t border-[#eef1f5] px-5 py-4">
                {{ $receipts->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
