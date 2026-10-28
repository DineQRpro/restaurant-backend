@extends('layouts.counter')

@section('title', 'Cart')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-semibold">Cart</h1>
        <span class="takeaway-btn takeaway-btn-primary bg-[#04346a] text-white">Checkout <span class="takeaway-count">{{ $totalQuantity }}</span></span>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left">
                    <th class="px-3 py-2">Product Name</th>
                    <th class="px-3 py-2">Price</th>
                    <th class="px-3 py-2">Qty</th>
                    <th class="px-3 py-2">Order Type</th>
                    <th class="px-3 py-2">Packing</th>
                    <th class="px-3 py-2">Action</th>
                    <th class="px-3 py-2">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $line['menu_item']->name }}</td>
                        <td class="px-3 py-2">{{ number_format($line['unit_price'], 2) }}</td>
                        <td class="px-3 py-2">
                            <div class="takeaway-qty">
                                <form method="POST" action="{{ route('counter.takeaway.cart.decrease', $line['menu_item']) }}">@csrf<button type="submit" aria-label="Decrease quantity">−</button></form>
                                <span>{{ $line['quantity'] }}</span>
                                <form method="POST" action="{{ route('counter.takeaway.cart.increase', $line['menu_item']) }}">@csrf<button type="submit" aria-label="Increase quantity">+</button></form>
                            </div>
                        </td>
                        <td class="px-3 py-2">Take Away</td>
                        <td class="px-3 py-2">
                            <div class="takeaway-qty">
                                <form method="POST" action="{{ route('counter.takeaway.cart.packing-decrease', $line['menu_item']) }}">@csrf<button type="submit" aria-label="Decrease packing">−</button></form>
                                <span>{{ $line['packing_fee'] }}</span>
                                <form method="POST" action="{{ route('counter.takeaway.cart.packing-increase', $line['menu_item']) }}">@csrf<button type="submit" aria-label="Increase packing">+</button></form>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('counter.takeaway.cart.remove', $line['menu_item']) }}">
                                @csrf
                                <button type="submit" class="takeaway-remove">Remove</button>
                            </form>
                        </td>
                        <td class="px-3 py-2">{{ number_format($line['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-4 text-center">No cart items</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($lines !== [])
            <div class="flex flex-wrap items-center justify-end gap-4 border-t px-3 py-4">
                <p class="font-semibold">Total Price : {{ number_format($totalAmount, 2) }}</p>
                <a href="{{ route('counter.takeaway.menu') }}" class="takeaway-btn takeaway-btn-light bg-white text-[#1f2f3e]">Continue Shopping</a>
                <form method="POST" action="{{ route('counter.takeaway.place') }}">
                    @csrf
                    <button type="submit" class="takeaway-btn takeaway-btn-success bg-[#28a745] text-white">Place Order</button>
                </form>
            </div>
        @endif
    </div>
@endsection
