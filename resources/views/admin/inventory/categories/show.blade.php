@extends('layouts.admin')

@section('title', $category->name)
@section('page-title', 'Item Categories')
@section('page-subtitle', 'View Category')

@section('content')
    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('admin.inventory.categories.index') }}" class="font-semibold text-[#04346a] hover:underline">Item Categories</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">{{ $category->name }}</li>
        </ol>
    </nav>
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-[#eef1f5] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-bold text-[#1f2f3e]">{{ $category->name }}</h2>
            @if (auth()->user()->hasPermission('inventory.update'))
                <a href="{{ route('admin.inventory.categories.edit', $category) }}" class="inline-flex rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">Edit</a>
            @endif
        </div>
        <dl class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Code</dt><dd class="mt-1 text-sm font-semibold">{{ $category->code ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Status</dt><dd class="mt-1 text-sm font-semibold">{{ $category->is_active ? 'Active' : 'Inactive' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Inventory items</dt><dd class="mt-1 text-sm font-semibold">{{ $category->items_count }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-[#898989]">Description</dt><dd class="mt-1 text-sm">{{ $category->description ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
