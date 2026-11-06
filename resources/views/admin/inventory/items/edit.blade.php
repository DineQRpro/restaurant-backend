@extends('layouts.admin')
@section('title', 'Edit Inventory Item')
@section('page-title', 'Inventory Items')
@section('page-subtitle', 'Edit Item')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4"><h2 class="text-lg font-bold">Edit Inventory Item</h2></div>
        <div class="p-5">
            <form method="POST" action="{{ route('admin.inventory.items.update', $item) }}" class="rounded-lg border border-[#eef1f5] p-5">
                @csrf
                @method('PUT')
                @include('admin.inventory.items._form', ['item' => $item, 'categories' => $categories, 'units' => $units])
                <div class="mt-6 flex gap-3">
                    <button class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Update</button>
                    <a href="{{ route('admin.inventory.items.show', $item) }}" class="rounded-md border px-4 py-2.5 text-sm font-semibold">Cancel</a>
                </div>
            </form>
        </div>
    </section>
@endsection
