@extends('layouts.counter')

@section('title', 'Place Take Away Order')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold">Place Take Away Order</h1>
        <p class="text-sm text-slate-500">Enter customer details to continue</p>
    </div>

    <form method="POST" action="{{ route('counter.takeaway.store') }}" class="max-w-5xl rounded-lg bg-white p-6 shadow-sm">
        @csrf
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-semibold" for="order_type">Order Type</label>
                <select id="order_type" name="order_type" class="w-full rounded border border-slate-300 px-3 py-2">
                    <option value="">Select Order Type</option>
                    <option value="take-away" @selected(old('order_type') === 'take-away')>Take Away</option>
                </select>
                @error('order_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold" for="customer_name">Customer Name</label>
                <input id="customer_name" name="customer_name" type="text" value="{{ old('customer_name', $draft['customer_name']) }}" placeholder="Enter name" class="w-full rounded border border-slate-300 px-3 py-2">
                @error('customer_name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold" for="mobile_number">Mobile Number</label>
                <input id="mobile_number" name="mobile_number" type="tel" maxlength="10" inputmode="numeric" value="{{ old('mobile_number', $draft['mobile']) }}" placeholder="10 digit mobile" class="w-full rounded border border-slate-300 px-3 py-2">
                @error('mobile_number')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <a href="{{ route('counter.dashboard') }}" class="takeaway-btn takeaway-btn-light bg-white text-[#1f2f3e]">Cancel</a>
            <button type="submit" class="takeaway-btn takeaway-btn-primary bg-[#04346a] text-white">Submit Order</button>
        </div>
    </form>
@endsection
