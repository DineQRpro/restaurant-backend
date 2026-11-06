@extends('layouts.admin')

@section('title', 'Inventory Overview')
@section('page-title', 'Inventory Overview')
@section('page-subtitle', 'Store & Inventory')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-3 rounded-xl bg-white p-4 shadow-sm">
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search item" class="rounded-md border px-3 py-2 text-sm">
        <select name="category_id" class="rounded-md border px-3 py-2 text-sm">
            <option value="">All categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <button class="rounded-md bg-[#16324f] px-4 py-2 text-sm font-semibold text-white">Filter</button>
    </form>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-[#eef1f5] text-left text-xs uppercase"><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Balance</th><th class="px-4 py-3">Reorder</th><th class="px-4 py-3">Maximum</th><th class="px-4 py-3">Low stock</th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    @php($balance = number_format((float) $item->current_stock, 3, '.', ''))
                    <tr class="border-t">
                        <td class="px-4 py-3">{{ $item->name }} ({{ $item->code }})</td>
                        <td class="px-4 py-3">{{ $item->category?->name }}</td>
                        <td class="px-4 py-3">{{ $item->unit?->symbol }}</td>
                        <td class="px-4 py-3">{{ $balance }}</td>
                        <td class="px-4 py-3">{{ $item->reorder_level }}</td>
                        <td class="px-4 py-3">{{ $item->maximum_level }}</td>
                        <td class="px-4 py-3">{{ bccomp($balance, (string) $item->reorder_level, 3) <= 0 ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-[#898989]">No inventory items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
@endsection
