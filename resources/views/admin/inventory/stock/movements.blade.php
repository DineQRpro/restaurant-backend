@extends('layouts.admin')

@section('title', 'Stock Movements')
@section('page-title', 'Stock Movements')
@section('page-subtitle', 'Store & Inventory')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-xl bg-white p-4 shadow-sm">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Item or receipt" class="rounded-md border px-3 py-2 text-sm">
        <select name="item_id" class="rounded-md border px-3 py-2 text-sm">
            <option value="">All items</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected((string) ($filters['item_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
        <input name="receipt_id" value="{{ $filters['receipt_id'] ?? '' }}" placeholder="Receipt id" class="rounded-md border px-3 py-2 text-sm">
        <button class="rounded-md bg-[#16324f] px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-[#eef1f5] text-left text-xs uppercase"><tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Item</th><th class="px-4 py-3">Receipt</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">User</th></tr></thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $movement->posted_at }}</td>
                        <td class="px-4 py-3">{{ $movement->item?->name }}</td>
                        <td class="px-4 py-3"><a class="text-[#04346a]" href="{{ route('admin.inventory.receipts.show', $movement->goods_receipt_id) }}">{{ $movement->receipt?->receipt_number }}</a></td>
                        <td class="px-4 py-3">{{ $movement->movement_type->label() }}</td>
                        <td class="px-4 py-3">{{ $movement->quantity_in }}</td>
                        <td class="px-4 py-3">{{ $movement->quantity_out }}</td>
                        <td class="px-4 py-3">{{ $movement->poster?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-[#898989]">No stock movements found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movements->links() }}</div>
@endsection
