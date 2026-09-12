@extends('layouts.admin')

@section('title', 'Cuisine List')
@section('page-title', 'Cuisine List')
@section('page-subtitle', 'Menu categories')

@section('content')
    @php
        $user = auth()->user();
        $canCreate = $user->hasPermission('menu.create');
        $canUpdate = $user->hasPermission('menu.update');
        $canDelete = $user->hasPermission('menu.delete');
    @endphp

    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li>Menu</li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Categories</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-bold text-[#1f2f3e]">Cuisine</h2>
                <p class="text-sm text-[#898989]">Manage the categories used when creating menu items.</p>
            </div>
            @if ($canCreate)
                <a
                    href="{{ route('admin.menu.categories.create') }}"
                    class="inline-flex items-center justify-center rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]"
                >
                    Add New
                </a>
            @endif
        </div>

        <div class="border-b border-[#eef1f5] px-5 py-4">
            <form method="GET" action="{{ route('admin.menu.categories.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Search cuisine title or description"
                    class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                >
                <select
                    name="is_active"
                    class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                >
                    <option value="">All statuses</option>
                    <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Filter
                    </button>
                    <a href="{{ route('admin.menu.categories.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-[#16324f] text-white">
                    <tr>
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">Image</th>
                        <th class="px-4 py-3 font-semibold">Title</th>
                        <th class="px-4 py-3 font-semibold">Description</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Updated</th>
                        <th class="px-4 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $index => $category)
                        <tr class="border-b border-[#eef1f5] last:border-0">
                            <td class="px-4 py-3 text-[#898989]">{{ $categories->firstItem() + $index }}</td>
                            <td class="px-4 py-3">
                                @if ($category->image)
                                    <img
                                        src="{{ str_starts_with($category->image, 'http') ? $category->image : asset('storage/'.$category->image) }}"
                                        alt="{{ $category->name }}"
                                        class="h-12 w-12 rounded object-cover"
                                    >
                                @else
                                    <span class="text-[#898989]">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold text-[#1f2f3e]">{{ $category->name }}</td>
                            <td class="max-w-xs px-4 py-3 text-[#425466]">{{ $category->description ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700' => $category->is_active,
                                    'bg-slate-100 text-slate-600' => ! $category->is_active,
                                ])>
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-[#898989]">{{ $category->updated_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canUpdate)
                                        <a
                                            href="{{ route('admin.menu.categories.edit', $category) }}"
                                            class="rounded-md border border-[#04346a]/20 px-2.5 py-1 text-xs font-semibold text-[#04346a] hover:bg-[#04346a]/5"
                                        >
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('admin.menu.categories.status', $category) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                            <button
                                                type="submit"
                                                class="rounded-md border border-[#dfdfdf] px-2.5 py-1 text-xs font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]"
                                            >
                                                {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    @endif
                                    @if ($canDelete)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.menu.categories.destroy', $category) }}"
                                            onsubmit="return confirm('Delete this cuisine? Categories that still have menu items cannot be deleted.')"
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
                            <td colspan="7" class="px-4 py-8 text-center font-semibold text-[#898989]">There is no cuisine</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="border-t border-[#eef1f5] px-5 py-4">
                {{ $categories->withQueryString()->links() }}
            </div>
        @endif
    </section>
@endsection
