@extends('layouts.admin')
@section('title', $item->name)
@section('page-title', 'Inventory Items')
@section('page-subtitle', 'View Item')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold">{{ $item->name }}</h2>
            @if (auth()->user()->hasPermission('inventory.update'))
                <a href="{{ route('admin.inventory.items.edit', $item) }}" class="rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Edit</a>
            @endif
        </div>
        <dl class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Code</dt><dd class="mt-1 font-semibold">{{ $item->code }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Status</dt><dd class="mt-1 font-semibold">{{ $item->is_active ? 'Active' : 'Inactive' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Category</dt><dd class="mt-1 font-semibold">{{ $item->category?->name }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Base unit</dt><dd class="mt-1 font-semibold">{{ $item->unit?->name }} ({{ $item->unit?->symbol }})</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Reorder level</dt><dd class="mt-1 font-semibold">{{ $item->reorder_level }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Maximum stock level</dt><dd class="mt-1 font-semibold">{{ $item->maximum_level }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-[#898989]">Description</dt><dd class="mt-1">{{ $item->description ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
