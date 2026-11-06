@extends('layouts.admin')
@section('title', 'Edit Vendor')
@section('page-title', 'Vendors')
@section('page-subtitle', 'Edit Vendor')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4"><h2 class="text-lg font-bold">Edit Vendor</h2></div>
        <div class="p-5">
            <form method="POST" action="{{ route('admin.inventory.vendors.update', $vendor) }}" class="rounded-lg border border-[#eef1f5] p-5">
                @csrf
                @method('PUT')
                @include('admin.inventory.vendors._form', ['vendor' => $vendor])
                <div class="mt-6 flex gap-3">
                    <button class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Update</button>
                    <a href="{{ route('admin.inventory.vendors.show', $vendor) }}" class="rounded-md border px-4 py-2.5 text-sm font-semibold">Cancel</a>
                </div>
            </form>
        </div>
    </section>
@endsection
