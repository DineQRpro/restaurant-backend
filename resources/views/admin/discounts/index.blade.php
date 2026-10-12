@extends('layouts.admin')

@section('title', 'Restaurant Discount List')
@section('page-title', 'Restaurant Discount List')
@section('page-subtitle', 'Discount management')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user->hasPermission('discounts.create');
        $canUpdate = $user->hasPermission('discounts.update');
        $canDelete = $user->hasPermission('discounts.delete');
        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
    @endphp

    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Discounts</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#1f2f3e]">Restaurant Discounts</h2>
                <p class="text-sm text-[#898989]">Manage named percentage discounts. Custom discounts let staff enter a percentage later; fixed discounts store the percentage on the master.</p>
            </div>
            @if ($canCreate)
                <a
                    href="{{ route('admin.discounts.create') }}"
                    class="inline-flex items-center justify-center rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]"
                >
                    Add Discount
                </a>
            @endif
        </div>

        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" action="{{ route('admin.discounts.index') }}" class="grid grid-cols-1 gap-3 lg:grid-cols-5">
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search discount name"
                    class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                >
                <select name="is_active" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <select name="type" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                <select name="applies_to" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                    <option value="">All applies to</option>
                    @foreach ($appliesToOptions as $appliesTo)
                        <option value="{{ $appliesTo->value }}" @selected(($filters['applies_to'] ?? '') === $appliesTo->value)>{{ $appliesTo->label() }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Filter
                    </button>
                    <a href="{{ route('admin.discounts.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Discount Name</th>
                        <th class="px-4 py-3 font-semibold">Type</th>
                        <th class="px-4 py-3 font-semibold">Value</th>
                        <th class="px-4 py-3 font-semibold">Applies To</th>
                        <th class="px-4 py-3 font-semibold">Min Level</th>
                        <th class="px-4 py-3 font-semibold">Max Level</th>
                        <th class="px-4 py-3 font-semibold">Start Date</th>
                        <th class="px-4 py-3 font-semibold">End Date</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($discounts as $discount)
                        <tr class="border-b border-[#eef1f5] last:border-0">
                            <td class="px-4 py-3 font-semibold text-[#1f2f3e]">{{ $discount->name }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $discount->type->label() }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ rtrim(rtrim(number_format((float) $discount->value, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $discount->applies_to->label() }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ (int) $discount->min_level === 0 ? '—' : $discount->min_level }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ (int) $discount->max_level === 0 ? '—' : $discount->max_level }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $discount->start_date?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="px-4 py-3 text-[#425466]">{{ $discount->end_date?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700' => $discount->is_active,
                                    'bg-slate-100 text-slate-600' => ! $discount->is_active,
                                ])>
                                    {{ $discount->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canUpdate)
                                        <a
                                            href="{{ route('admin.discounts.edit', $discount) }}"
                                            class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a] hover:bg-[#04346a]/5"
                                        >
                                            Edit
                                        </a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.discounts.status', $discount) }}"
                                            onsubmit="return confirm({{ json_encode('Update active status for '.$discount->name.'?') }})"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $discount->is_active ? 0 : 1 }}">
                                            <button type="submit" class="rounded-md border border-[#dfdfdf] px-2.5 py-1 text-xs font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                                                {{ $discount->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endif
                                    @if ($canDelete)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.discounts.destroy', $discount) }}"
                                            onsubmit="return confirm({{ json_encode('Delete discount '.$discount->name.'? This cannot be undone.') }})"
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
                                    <p class="font-semibold text-[#1f2f3e]">No discounts match your search or filters.</p>
                                @else
                                    <p class="font-semibold text-[#1f2f3e]">No discounts found.</p>
                                    @if ($canCreate)
                                        <a href="{{ route('admin.discounts.create') }}" class="mt-3 inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                                            Add Discount
                                        </a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($discounts->hasPages())
            <div class="border-t border-[#eef1f5] px-5 py-4">
                {{ $discounts->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
