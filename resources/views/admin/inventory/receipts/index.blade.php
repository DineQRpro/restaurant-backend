@extends('layouts.admin')

@section('title', 'Goods Receipts')
@section('page-title', 'Goods Receipts')
@section('page-subtitle', 'Store & Inventory')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-[#1f2f3e]">Goods Receipts</h2>
        @if (auth()->user()->hasPermission('inventory.receipt.create'))
            <a href="{{ route('admin.inventory.receipts.create') }}" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">New receipt</a>
        @endif
    </div>
    <form method="GET" class="mb-4 grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm md:grid-cols-4">
        <input name="receipt_number" value="{{ $filters['receipt_number'] ?? '' }}" placeholder="Receipt number" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
        <select name="vendor_id" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
            <option value="">All vendors</option>
            @foreach ($vendors as $vendor)
                <option value="{{ $vendor->id }}" @selected((string) ($filters['vendor_id'] ?? '') === (string) $vendor->id)>{{ $vendor->name }} — {{ $vendor->code }}</option>
            @endforeach
        </select>
        <input name="vendor_bill_number" value="{{ $filters['vendor_bill_number'] ?? '' }}" placeholder="Vendor bill number" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
        <select name="status" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
            <option value="">All posting statuses</option>
            @foreach (\App\Enums\GoodsReceiptStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <select name="payment_status" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
            <option value="">All payment statuses</option>
            @foreach (\App\Enums\GoodsReceiptPaymentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['payment_status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
        <button class="rounded-md bg-[#16324f] px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-[#eef1f5] text-left text-xs uppercase text-[#16324f]">
                <tr>
                    <th class="px-4 py-3">Receipt</th>
                    <th class="px-4 py-3">Vendor</th>
                    <th class="px-4 py-3">Bill</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipts as $receipt)
                    <tr class="border-t border-[#eef1f5]">
                        <td class="px-4 py-3 font-semibold">{{ $receipt->receipt_number }}</td>
                        <td class="px-4 py-3">{{ $receipt->vendor_name_snapshot }} — {{ $receipt->vendor_code_snapshot }}</td>
                        <td class="px-4 py-3">{{ $receipt->vendor_bill_number }}</td>
                        <td class="px-4 py-3">{{ $receipt->receipt_date->toDateString() }}</td>
                        <td class="px-4 py-3">{{ $receipt->status->label() }}</td>
                        <td class="px-4 py-3">{{ $receipt->payment_status->label() }}</td>
                        <td class="px-4 py-3">{{ $receipt->grand_total }}</td>
                        <td class="px-4 py-3 text-right"><a class="font-semibold text-[#04346a]" href="{{ route('admin.inventory.receipts.show', $receipt) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-[#898989]">No goods receipts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $receipts->links() }}</div>
@endsection
