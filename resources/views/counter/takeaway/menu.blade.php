@extends('layouts.counter')

@section('title', 'Counter Menu')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Add New Orders</h1>
            <p class="text-sm text-slate-500">{{ $draft['customer_name'] !== '' ? $draft['customer_name'] : 'Walk-in' }} · Take Away</p>
        </div>
        <a href="{{ route('counter.takeaway.cart') }}" class="takeaway-btn takeaway-btn-primary bg-[#04346a] text-white">
            Your Order <span class="takeaway-count">{{ $totalQuantity }}</span>
        </a>
    </div>

    <div class="mb-3">
        <input id="searchInput" type="search" placeholder="Search dish..." class="w-full rounded-full border border-slate-300 px-4 py-2">
    </div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="takeaway-filter is-active bg-[#1f2937] text-[#e5e7eb]" data-type="all">All Menu</button>
            <button type="button" class="takeaway-filter bg-[#1f2937] text-[#e5e7eb]" data-type="vegetarian">Veg</button>
            <button type="button" class="takeaway-filter bg-[#1f2937] text-[#e5e7eb]" data-type="non_vegetarian">Non Veg</button>
            <button type="button" class="takeaway-filter bg-[#1f2937] text-[#e5e7eb]" data-type="eggetarian">Egg</button>
        </div>
        <select id="categoryFilter" class="rounded-full border border-slate-300 px-4 py-2">
            <option value="">All Cuisine</option>
            @foreach ($categories as $category)
                <option value="{{ $category->name }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-800 text-left text-white">
                <tr>
                    <th class="px-3 py-2">Product Name</th>
                    <th class="px-3 py-2">Category</th>
                    <th class="px-3 py-2">Price</th>
                    <th class="px-3 py-2">Packaging Charges</th>
                    <th class="px-3 py-2">Qty</th>
                    <th class="px-3 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($menus as $menu)
                    @php
                        $qty = $cartQuantities[$menu->id] ?? 0;
                        $packing = $qty > 0
                            ? (int) ($draft['items'][$menu->id]['packing_fee'] ?? 0)
                            : (int) ($draft['packing'][$menu->id] ?? 0);
                        $basePrice = (float) $menu->price;
                    @endphp
                    <tr class="product-card border-t" data-name="{{ $menu->name }}" data-type="{{ $menu->dietary_type?->value }}" data-category="{{ $menu->category?->name }}">
                        <td class="px-3 py-2">{{ $menu->name }}</td>
                        <td class="px-3 py-2">{{ $menu->category?->name }}</td>
                        <td class="px-3 py-2">
                            @if ($packing > 0)
                                {{ number_format($basePrice, 2) }} + {{ $packing }} (packing) = {{ number_format($basePrice + $packing, 2) }}
                            @else
                                {{ number_format($basePrice, 2) }}
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <form method="POST" action="{{ route('counter.takeaway.menu.packing', $menu) }}">
                                @csrf
                                <input type="hidden" name="enabled" value="{{ $packing > 0 ? 0 : 1 }}">
                                <input type="checkbox" class="takeaway-pack" aria-label="Packing charge for {{ $menu->name }}" onchange="this.form.submit()" @checked($packing > 0)>
                            </form>
                        </td>
                        <td class="px-3 py-2">
                            @if ($qty > 0)
                                <div class="takeaway-qty">
                                    <form method="POST" action="{{ route('counter.takeaway.menu.decrease', $menu) }}">@csrf<button type="submit" aria-label="Decrease">−</button></form>
                                    <span>{{ $qty }}</span>
                                    <form method="POST" action="{{ route('counter.takeaway.menu.increase', $menu) }}">@csrf<button type="submit" aria-label="Increase">+</button></form>
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($qty === 0)
                                <form method="POST" action="{{ route('counter.takeaway.menu.add', $menu) }}">
                                    @csrf
                                    <button type="submit" class="takeaway-add bg-[#d1e7dd] text-[#0f5132]">Add</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-4">There is no menu items</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const buttons = document.querySelectorAll('.takeaway-filter');
            const products = document.querySelectorAll('.product-card');
            let selectedType = 'all';

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    buttons.forEach(function (other) { other.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    selectedType = btn.dataset.type;
                    filterProducts();
                });
            });

            searchInput.addEventListener('input', filterProducts);
            categoryFilter.addEventListener('change', filterProducts);

            function filterProducts() {
                const search = searchInput.value.toLowerCase();
                const category = categoryFilter.value;
                products.forEach(function (product) {
                    const name = (product.dataset.name || '').toLowerCase();
                    const type = product.dataset.type || '';
                    const cat = product.dataset.category || '';
                    const visible = name.includes(search)
                        && (selectedType === 'all' || type === selectedType)
                        && (!category || cat === category);
                    product.style.display = visible ? '' : 'none';
                });
            }
        });
    </script>
@endsection
