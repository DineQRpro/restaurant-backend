@extends('layouts.admin')
@section('title', 'Inventory Items')
@section('page-title', 'Inventory Items')
@section('page-subtitle', 'Store & Inventory')
@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user->hasPermission('inventory.create');
        $canUpdate = $user->hasPermission('inventory.update');
        $canDelete = $user->hasPermission('inventory.delete');
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    @endphp
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold">Inventory Items</h2>
                <p class="text-sm text-[#898989]">Item masters with category, base unit, reorder level and maximum level. No stock balance is stored here.</p>
            </div>
            @if ($canCreate)
                <a href="{{ route('admin.inventory.items.create') }}" class="inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Add Item</a>
            @endif
        </div>
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or code" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
                <select name="is_active" class="rounded-md border border-[#dfdfdf] px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <div class="flex gap-2">
                    <button class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('admin.inventory.items.index') }}" class="rounded-md border px-4 py-2 text-sm font-semibold">Reset</a>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3">Reorder</th>
                        <th class="px-4 py-3">Maximum</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="border-b border-[#eef1f5]">
                            <td class="px-4 py-3 font-semibold">{{ $item->code }}</td>
                            <td class="px-4 py-3">{{ $item->name }}</td>
                            <td class="px-4 py-3">{{ $item->category?->name }}</td>
                            <td class="px-4 py-3">{{ $item->unit?->symbol }}</td>
                            <td class="px-4 py-3">{{ $item->reorder_level }}</td>
                            <td class="px-4 py-3">{{ $item->maximum_level }}</td>
                            <td class="px-4 py-3">{{ $item->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.inventory.items.show', $item) }}" class="rounded-md border px-2.5 py-1 text-xs font-semibold">View</a>
                                    @if ($canUpdate)
                                        <a href="{{ route('admin.inventory.items.edit', $item) }}" class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a]">Edit</a>
                                        <form method="POST" action="{{ route('admin.inventory.items.status', $item) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}"><button class="rounded-md border px-2.5 py-1 text-xs font-semibold">{{ $item->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                    @endif
                                    @if ($canDelete)
                                        <form method="POST" action="{{ route('admin.inventory.items.destroy', $item) }}" onsubmit="return confirm({{ json_encode('Delete item '.$item->name.'?') }})">@csrf @method('DELETE')<button class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center font-semibold">{{ $hasFilters ? 'No inventory items match your search or filters.' : 'No inventory items found.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())<div class="px-5 py-4">{{ $items->links() }}</div>@endif
    </section>
@endsection
