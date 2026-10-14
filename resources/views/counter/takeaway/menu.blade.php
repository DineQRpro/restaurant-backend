@extends('layouts.counter')

@section('title', 'Counter Menu')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Add New Orders</h1>
            <p class="text-sm text-slate-500">{{ $draft['customer_name'] }} · Take Away</p>
        </div>
        <a href="{{ route('counter.takeaway.cart') }}" class="rounded bg-indigo-600 px-4 py-2 text-white">
            Your Order <span class="ml-1 rounded bg-red-600 px-2">{{ $totalQuantity }}</span>
        </a>
    </div>

    <div class="mb-3">
        <input id="searchInput" type="search" placeholder="Search dish..." class="w-full rounded-full border border-slate-300 px-4 py-2">
    </div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            <button type="button" class="filter-btn rounded-full bg-slate-800 px-4 py-1 text-sm text-white" data-type="all">All Menu</button>
            <button type="button" class="filter-btn rounded-full bg-slate-200 px-4 py-1 text-sm" data-type="vegetarian">Veg</button>
            <button type="button" class="filter-btn rounded-full bg-slate-200 px-4 py-1 text-sm" data-type="non_vegetarian">Non Veg</button>
            <button type="button" class="filter-btn rounded-full bg-slate-200 px-4 py-1 text-sm" data-type="eggetarian">Egg</button>
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
                    <th class="px-3 py-2">Qty</th>
                    <th class="px-3 py-2">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($menus as $menu)
                    @php $qty = $cartQuantities[$menu->id] ?? 0; @endphp
                    <tr class="product-card border-t" data-name="{{ $menu->name }}" data-type="{{ $menu->dietary_type?->value }}" data-category="{{ $menu->category?->name }}">
                        <td class="px-3 py-2">{{ $menu->name }}</td>
                        <td class="px-3 py-2">{{ $menu->category?->name }}</td>
                        <td class="px-3 py-2">{{ number_format((float) $menu->price, 2) }}</td>
                        <td class="px-3 py-2">
                            @if ($qty > 0)
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('counter.takeaway.menu.decrease', $menu) }}">@csrf<button type="submit">−</button></form>
                                    <span>{{ $qty }}</span>
                                    <form method="POST" action="{{ route('counter.takeaway.menu.increase', $menu) }}">@csrf<button type="submit">+</button></form>
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($qty === 0)
                                <form method="POST" action="{{ route('counter.takeaway.menu.add', $menu) }}">
                                    @csrf
                                    <button type="submit" class="rounded bg-emerald-100 px-3 py-1">Add</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-4">There is no menu items</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const categoryFilter = document.getElementById('categoryFilter');
            const buttons = document.querySelectorAll('.filter-btn');
            const products = document.querySelectorAll('.product-card');
            let selectedType = 'all';

            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    buttons.forEach(function (other) { other.classList.remove('bg-slate-800', 'text-white'); });
                    btn.classList.add('bg-slate-800', 'text-white');
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
