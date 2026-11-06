@extends('layouts.admin')
@section('title', $vendor->name)
@section('page-title', 'Vendors')
@section('page-subtitle', 'View Vendor')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold">{{ $vendor->name }}</h2>
            @if (auth()->user()->hasPermission('inventory.update'))
                <a href="{{ route('admin.inventory.vendors.edit', $vendor) }}" class="rounded-full bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Edit</a>
            @endif
        </div>
        <dl class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Vendor code</dt><dd class="mt-1 font-semibold">{{ $vendor->code }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Status</dt><dd class="mt-1 font-semibold">{{ $vendor->is_active ? 'Active' : 'Inactive' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Shop name</dt><dd class="mt-1">{{ $vendor->shop_name ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">POC name</dt><dd class="mt-1">{{ $vendor->poc_name ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Phone</dt><dd class="mt-1">{{ $vendor->phone ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">GST number</dt><dd class="mt-1">{{ $vendor->gst_number ?: '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-[#898989]">Address</dt><dd class="mt-1">{{ $vendor->address ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">Bank name</dt><dd class="mt-1">{{ $vendor->bank_name ?: '—' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-[#898989]">IFSC code</dt><dd class="mt-1">{{ $vendor->ifsc_code ?: '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-[#898989]">Bank account number</dt><dd class="mt-1 font-mono">{{ $vendor->maskedBankAccountNumber() ?: '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-[#898989]">Notes</dt><dd class="mt-1">{{ $vendor->notes ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
