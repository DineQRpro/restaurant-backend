@extends('layouts.admin')

@section('title', 'Low Stock')
@section('page-title', 'Low Stock')
@section('page-subtitle', 'Store & Inventory')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-[#898989]">Active items at or below their reorder level, largest shortage first.</p>
        @if (auth()->user()->hasPermission('inventory.receipt.create'))
            <a href="{{ route('admin.inventory.receipts.create') }}" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">New goods receipt</a>
        @endif
    </div>
    <form method="GET" class="mb-4"><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search" class="rounded-md border px-3 py-2 text-sm"><button class="ml-2 rounded-md bg-[#16324f] px-4 py-2 text-sm font-semibold text-white">Filter</button></form>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-[#eef1f5] text-left text-xs uppercase"><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Reorder</th><th class="px-4 py-3">Shortage</th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    @php($balance = number_format((float) $item->current_stock, 3, '.', ''))
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $item->name }}</td>
                        <td class="px-4 py-3">{{ $item->category?->name }}</td>
                        <td class="px-4 py-3">{{ $item->unit?->symbol }}</td>
                        <td class="px-4 py-3">{{ $balance }}</td>
                        <td class="px-4 py-3">{{ $item->reorder_level }}</td>
                        <td class="px-4 py-3">{{ bcsub((string) $item->reorder_level, $balance, 3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-[#898989]">No items are at or below reorder level.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
@endsection
