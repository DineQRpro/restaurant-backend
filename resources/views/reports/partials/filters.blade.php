@php
    $sdate = $filters->sdate;
    $edate = $filters->edate;
    $query = $filters->queryString();
@endphp

<form method="GET" action="{{ route($filterRoute) }}" class="report-filters">
    <div class="report-field">
        <label for="sdate">Starting Date &amp; Time</label>
        <input
            type="datetime-local"
            id="sdate"
            name="sdate"
            value="{{ $sdate }}"
            max="{{ $todayMax }}"
        >
        @error('sdate')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>
    <div class="report-field">
        <label for="edate">Ending Date &amp; Time</label>
        <input
            type="datetime-local"
            id="edate"
            name="edate"
            value="{{ $edate }}"
            max="{{ $todayMax }}"
        >
        @error('edate')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if (! empty($billNoFilter))
        <div class="report-field">
            <label for="bill_no">Bill No</label>
            <input
                type="text"
                id="bill_no"
                name="bill_no"
                value="{{ $filters->billNo }}"
                placeholder="Filter by Bill No"
            >
        </div>
    @endif

    @if (! empty($billStatusFilter))
        <div class="report-field">
            <label for="bill_status">Bill Status</label>
            <select id="bill_status" name="bill_status">
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
        <div class="report-field">
            <label for="order_type">Order Type</label>
            <select id="order_type" name="order_type">
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
        <div class="report-field">
            <label for="item_name">Item</label>
            <input
                type="text"
                id="item_name"
                name="item_name"
                list="item_options"
                value="{{ $filters->itemName }}"
                placeholder="Search item..."
                autocomplete="off"
            >
            <datalist id="item_options">
                @foreach ($itemOptions as $option)
                    <option value="{{ $option->name }}"></option>
                @endforeach
            </datalist>
        </div>
    @endif

    <div class="report-actions">
        <button type="submit" class="report-btn report-btn-primary">Filter</button>
        @if (! empty($showAll))
            <a href="{{ route($filterRoute, ['show_all' => 1]) }}" class="report-btn report-btn-secondary">Show All</a>
        @endif
        @if (! empty($exportEnabled))
            <a href="{{ route($filterRoute, array_merge($query, ['export' => 'csv'])) }}" class="report-btn report-btn-export">CSV</a>
            <a href="{{ route($filterRoute, array_merge($query, ['export' => 'excel'])) }}" class="report-btn report-btn-export">Excel</a>
            <a href="{{ route($filterRoute, array_merge($query, ['export' => 'pdf'])) }}" class="report-btn report-btn-export">PDF</a>
        @endif
        <a href="{{ route($filterRoute) }}" class="report-btn report-btn-secondary">Reset</a>
    </div>
</form>
