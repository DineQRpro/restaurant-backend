@extends('layouts.admin')
@section('title', $unit->name)
@section('page-title', 'Units of Measure')
@section('page-subtitle', 'View Unit')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold">{{ $unit->name }}</h2>
            @if (auth()->user()->hasPermission('inventory.update'))
                <a href="{{ route('admin.inventory.units.edit', $unit) }}" class="rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Edit</a>
            @endif
        </div>
        <dl class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Symbol</dt><dd class="mt-1 font-semibold">{{ $unit->symbol }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Code</dt><dd class="mt-1 font-semibold">{{ $unit->code }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Dimension</dt><dd class="mt-1 font-semibold">{{ $unit->dimension->label() }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Status</dt><dd class="mt-1 font-semibold">{{ $unit->is_active ? 'Active' : 'Inactive' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Inventory items</dt><dd class="mt-1 font-semibold">{{ $unit->items_count }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-[#898989]">Description</dt><dd class="mt-1">{{ $unit->description ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
