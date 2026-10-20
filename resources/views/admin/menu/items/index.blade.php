@extends('layouts.admin')

@section('title', 'Restaurant Menu List')
@section('page-title', 'Restaurant Menu List')
@section('page-subtitle', 'Menu items')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user->hasPermission('menu.create');
        $canUpdate = $user->hasPermission('menu.update');
        $canDelete = $user->hasPermission('menu.delete');
        $hasFilters = collect($filters)
            ->except('menu_type')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->isNotEmpty()
            || (($filters['menu_type'] ?? 'food') !== 'food');
        $spicyLabels = [1 => 'Spicy', 2 => 'Very Spicy'];
    @endphp

    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li>Menu</li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Items</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#1f2f3e]">Menu Items</h2>
                <p class="text-sm text-[#898989]">Manage dishes offered on the restaurant menu.</p>
            </div>
            @if ($canCreate)
                <a
                    href="{{ route('admin.menu.items.create') }}"
                    class="inline-flex items-center justify-center rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]"
                >
                    Add Menu Item
                </a>
            @endif
        </div>

        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" action="{{ route('admin.menu.items.index') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-7">
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search menu name"
                    class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a] xl:col-span-2"
                >
                <select name="menu_type" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    @foreach ($menuTypes as $menuType)
                        <option value="{{ $menuType->slug }}" @selected(($filters['menu_type'] ?? 'food') === $menuType->slug)>
                            {{ $menuType->name }}
                        </option>
                    @endforeach
                </select>
                <select name="category_id" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All cuisines</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <select name="dietary_type" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All dietary types</option>
                    @foreach (\App\Enums\DietaryType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(($filters['dietary_type'] ?? '') === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
                <select name="is_available" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All availability</option>
                    <option value="1" @selected(($filters['is_available'] ?? '') === '1')>Available</option>
                    <option value="0" @selected(($filters['is_available'] ?? '') === '0')>Unavailable</option>
                </select>
                <select name="is_chef_special" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">Chef special: all</option>
                    <option value="1" @selected(($filters['is_chef_special'] ?? '') === '1')>Chef Special</option>
                    <option value="0" @selected(($filters['is_chef_special'] ?? '') === '0')>Regular</option>
                </select>
                <div class="flex gap-2 xl:col-span-6">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Filter
                    </button>
                    <a href="{{ route('admin.menu.items.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[880px] w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">Menu Name</th>
                        <th class="px-4 py-3 font-semibold">Cuisine</th>
                        <th class="px-4 py-3 font-semibold">Price</th>
                        <th class="px-4 py-3 font-semibold">Dietary</th>
                        <th class="px-4 py-3 font-semibold">Spicy</th>
                        <th class="px-4 py-3 font-semibold">Chef Special</th>
                        <th class="px-4 py-3 font-semibold">Sort</th>
                        <th class="px-4 py-3 font-semibold">Availability</th>
                        <th class="px-4 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $index => $item)
                        <tr class="border-b border-[#eef1f5] last:border-0">
                            <td class="px-4 py-3 text-[#898989]">{{ $items->firstItem() + $index }}</td>
                            <td class="px-4 py-3 font-semibold text-[#1f2f3e]">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $item->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-semibold">₹{{ number_format((float) $item->price, 2) }}</td>
                            <td class="px-4 py-3">{{ $item->dietary_type?->label() ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $spicyLabels[$item->spicy_level] ?? 'Not Spicy' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-amber-50 text-amber-800' => $item->is_chef_special,
                                    'bg-slate-100 text-slate-600' => ! $item->is_chef_special,
                                ])>
                                    {{ $item->is_chef_special ? 'Chef Special' : 'Regular' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[#898989]">{{ $item->sort_order }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700' => $item->is_available,
                                    'bg-slate-100 text-slate-600' => ! $item->is_available,
                                ])>
                                    {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canUpdate)
                                        <a
                                            href="{{ route('admin.menu.items.edit', $item) }}"
                                            class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a] hover:bg-[#04346a]/5"
                                        >
                                            Edit
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.menu.items.status', $item) }}"
                                            onsubmit="return confirm('Update availability for this menu item?')"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_available" value="{{ $item->is_available ? 0 : 1 }}">
                                            <button type="submit" class="rounded-md border border-[#dfdfdf] px-2.5 py-1 text-xs font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                                                {{ $item->is_available ? 'Mark Unavailable' : 'Mark Available' }}
                                            </button>
                                        </form>
                                    @endif
                                    @if ($canDelete)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.menu.items.destroy', $item) }}"
                                            onsubmit="return confirm('Delete this menu item?')"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                    @if (! $canUpdate && ! $canDelete)
                                        <span class="text-xs text-[#898989]">View only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center">
                                @if ($hasFilters)
                                    <p class="font-semibold text-[#1f2f3e]">No menu items match your search or filters.</p>
                                @else
                                    <p class="font-semibold text-[#1f2f3e]">No menu items found.</p>
                                    @if ($canCreate)
                                        <a href="{{ route('admin.menu.items.create') }}" class="mt-3 inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                                            Add Menu Item
                                        </a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="border-t border-[#eef1f5] px-5 py-4">
                {{ $items->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
