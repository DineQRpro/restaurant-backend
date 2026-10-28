@extends('layouts.counter')

@section('title', 'View Bill')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">View Bill</h1>
            <p class="text-sm text-slate-500">
                {{ $order->order_number }}
                @if ($canApplyAdjustments)
                    · Edit Bill
                @endif
            </p>
        </div>
        <a href="{{ route('counter.dashboard') }}" class="takeaway-btn takeaway-btn-primary bg-[#04346a] text-white">Track Orders</a>
    </div>

    @include('shared.billing.view-bill')
    @include('shared.dashboard.order-action-modals')
@endsection
