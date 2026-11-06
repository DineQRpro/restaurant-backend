@extends('layouts.admin')
@section('title', 'Add Vendor')
@section('page-title', 'Vendors')
@section('page-subtitle', 'Add Vendor')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold">Add Vendor</h2>
            <p class="text-sm text-[#898989]">The vendor code is assigned when you save. It cannot be typed or changed later.</p>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('admin.inventory.vendors.store') }}" class="rounded-lg border border-[#eef1f5] p-5">
                @csrf
                @include('admin.inventory.vendors._form')
                <div class="mt-6 flex gap-3">
                    <button class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Submit</button>
                    <a href="{{ route('admin.inventory.vendors.index') }}" class="rounded-md border px-4 py-2.5 text-sm font-semibold">Cancel</a>
                </div>
            </form>
        </div>
    </section>
@endsection
