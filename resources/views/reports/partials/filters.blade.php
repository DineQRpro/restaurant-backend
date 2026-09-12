@php
    $sdate = $filters->sdate;
    $edate = $filters->edate;
    $query = $filters->queryString();
@endphp

<form method="GET" action="{{ route($filterRoute) }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">
    <div>
        <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="sdate">Starting Date &amp; Time</label>
        <input
            type="datetime-local"
            id="sdate"
            name="sdate"
            value="{{ $sdate }}"
            max="{{ $todayMax }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
        >
        @error('sdate')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="edate">Ending Date &amp; Time</label>
        <input
            type="datetime-local"
            id="edate"
            name="edate"
            value="{{ $edate }}"
            max="{{ $todayMax }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
        >
        @error('edate')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if (! empty($billNoFilter))
        <div>
            <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="bill_no">Bill No</label>
            <input
                type="text"
                id="bill_no"
                name="bill_no"
                value="{{ $filters->billNo }}"
                placeholder="Filter by Bill No"
                class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
            >
        </div>
    @endif

    @if (! empty($billStatusFilter))
        <div>
            <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="bill_status">Bill Status</label>
            <select id="bill_status" name="bill_status" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                <option value="">All</option>
                <option value="paid" @selected($filters->billStatus === 'paid')>Paid</option>
                <option value="unpaid" @selected($filters->billStatus === 'unpaid')>Unpaid</option>
                <option value="credit" @selected($filters->billStatus === 'credit')>Credit</option>
                <option value="cancel" @selected($filters->billStatus === 'cancel')>Cancel</option>
                <option value="non_chargeable" @selected($filters->billStatus === 'non_chargeable')>Non Chargeable</option>
            </select>
        </div>
    @endif

    @if (! empty($orderTypeFilter))
        <div>
            <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="order_type">Order Type</label>
            <select id="order_type" name="order_type" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]">
                <option value="">All</option>
                <option value="through_waiter" @selected($filters->orderType === 'through_waiter')>Through Waiter</option>
                <option value="dine_in" @selected($filters->orderType === 'dine_in')>Dine-In</option>
                <option value="take_away" @selected($filters->orderType === 'take_away')>Take Away</option>
                <option value="candle_light_dinner" @selected($filters->orderType === 'candle_light_dinner')>Candle Light Dinner</option>
                <option value="couple_dine" @selected($filters->orderType === 'couple_dine')>Couple Dinning</option>
            </select>
        </div>
    @endif

    @if (! empty($itemNameFilter))
        <div>
            <label class="mb-1 block text-sm font-semibold text-[#1f2f3e]" for="item_name">Item</label>
            <input
                type="text"
                id="item_name"
                name="item_name"
                list="item_options"
                value="{{ $filters->itemName }}"
                placeholder="Search item..."
                autocomplete="off"
                class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
            >
            <datalist id="item_options">
                @foreach ($itemOptions as $option)
                    <option value="{{ $option->name }}"></option>
                @endforeach
            </datalist>
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-2">
        <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white hover:bg-[#03284f]">
            Filter
        </button>
        @if (! empty($showAll))
            <a href="{{ route($filterRoute, ['show_all' => 1]) }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                Show All
            </a>
        @endif
        @if (! empty($exportEnabled))
            <a href="{{ route($filterRoute, array_merge($query, ['export' => 'csv'])) }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                CSV
            </a>
            <a href="{{ route($filterRoute, array_merge($query, ['export' => 'excel'])) }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                Excel
            </a>
            <a href="{{ route($filterRoute, array_merge($query, ['export' => 'pdf'])) }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                PDF
            </a>
        @endif
        <a href="{{ route($filterRoute) }}" class="rounded-md border border-[#dfdfdf] px-4 py-2 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
            Reset
        </a>
    </div>
</form>
