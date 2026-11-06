@extends('layouts.admin')

@section('title', 'Units of Measure')
@section('page-title', 'Units of Measure')
@section('page-subtitle', 'Store & Inventory')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user->hasPermission('inventory.create');
        $canUpdate = $user->hasPermission('inventory.update');
        $canDelete = $user->hasPermission('inventory.delete');
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    @endphp
    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Units of Measure</li>
        </ol>
    </nav>
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#1f2f3e]">Units of Measure</h2>
                <p class="text-sm text-[#898989]">Base units for inventory items. Conversion between units is not available in this phase.</p>
            </div>
            @if ($canCreate)
                <a href="{{ route('admin.inventory.units.create') }}" class="inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">Add Unit</a>
            @endif
        </div>
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, code or symbol" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                <select name="is_active" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Filter</button>
                    <a href="{{ route('admin.inventory.units.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold">Reset</a>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[820px] w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Name</th>
                        <th class="px-4 py-3 font-semibold">Code</th>
                        <th class="px-4 py-3 font-semibold">Symbol</th>
                        <th class="px-4 py-3 font-semibold">Dimension</th>
                        <th class="px-4 py-3 font-semibold">Items</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr class="border-b border-[#eef1f5]">
                            <td class="px-4 py-3 font-semibold">{{ $unit->name }}</td>
                            <td class="px-4 py-3">{{ $unit->code }}</td>
                            <td class="px-4 py-3">{{ $unit->symbol }}</td>
                            <td class="px-4 py-3">{{ $unit->dimension->label() }}</td>
                            <td class="px-4 py-3">{{ $unit->items_count }}</td>
                            <td class="px-4 py-3">{{ $unit->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.inventory.units.show', $unit) }}" class="rounded-md border border-[#dfdfdf] px-2.5 py-1 text-xs font-semibold">View</a>
                                    @if ($canUpdate)
                                        <a href="{{ route('admin.inventory.units.edit', $unit) }}" class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a]">Edit</a>
                                        <form method="POST" action="{{ route('admin.inventory.units.status', $unit) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $unit->is_active ? 0 : 1 }}"><button class="rounded-md border border-[#dfdfdf] px-2.5 py-1 text-xs font-semibold">{{ $unit->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                    @endif
                                    @if ($canDelete)
                                        <form method="POST" action="{{ route('admin.inventory.units.destroy', $unit) }}" onsubmit="return confirm({{ json_encode('Delete unit '.$unit->name.'?') }})">@csrf @method('DELETE')<button class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center font-semibold">{{ $hasFilters ? 'No units match your search or filters.' : 'No units of measure found.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($units->hasPages())<div class="border-t border-[#eef1f5] px-5 py-4">{{ $units->links() }}</div>@endif
    </section>
@endsection
