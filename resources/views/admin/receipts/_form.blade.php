@php
    /** @var \App\Models\Receipt|null $receipt */
    $receipt = $receipt ?? null;
    $isEdit = $receipt !== null;
    $itemNames = old('item_name');
    $quantities = old('quantity');
    $rates = old('rate_price');

    if (! is_array($itemNames) || $itemNames === []) {
        if ($receipt?->items->isNotEmpty()) {
            $itemNames = $receipt->items->pluck('item_name')->all();
            $quantities = $receipt->items->map(fn ($item) => $item->formattedQuantity())->all();
            $rates = $receipt->items->map(fn ($item) => number_format((float) $item->rate_price, 2, '.', ''))->all();
        } else {
            $itemNames = [''];
            $quantities = [''];
            $rates = [''];
        }
    }

    $quantities = is_array($quantities) ? $quantities : [''];
    $rates = is_array($rates) ? $rates : [''];
    $rowCount = max(1, count($itemNames), count($quantities), count($rates));
    if ($rowCount > 10) {
        $rowCount = 10;
    }
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-12">
    @if ($isEdit)
        <div class="md:col-span-12">
            <p class="text-sm font-semibold text-[#425466]">Receipt Form #{{ $receipt->receiptNumber() }}</p>
        </div>
    @endif

    <div class="md:col-span-4">
        <label for="receipt_date" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Date <span class="text-red-600">*</span>
        </label>
        <input
            id="receipt_date"
            type="date"
            name="receipt_date"
            value="{{ old('receipt_date', $receipt?->receipt_date?->format('Y-m-d') ?? now()->toDateString()) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('receipt_date') border-red-400 @enderror"
        >
        @error('receipt_date')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-4">
        <label for="customer_name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Customer Name <span class="text-red-600">*</span>
        </label>
        <input
            id="customer_name"
            type="text"
            name="customer_name"
            value="{{ old('customer_name', $receipt?->customer_name) }}"
            maxlength="150"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('customer_name') border-red-400 @enderror"
        >
        @error('customer_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-4">
        <label for="mobile" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Mobile <span class="text-red-600">*</span>
        </label>
        <input
            id="mobile"
            type="text"
            name="mobile"
            inputmode="numeric"
            pattern="[0-9]{10}"
            maxlength="10"
            minlength="10"
            value="{{ old('mobile', $receipt?->mobile) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('mobile') border-red-400 @enderror"
        >
        <p class="mt-1 text-xs text-[#898989]">Enter exactly 10 digits.</p>
        @error('mobile')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-8">
        <label for="program" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Program <span class="text-red-600">*</span>
        </label>
        <textarea
            id="program"
            name="program"
            rows="3"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('program') border-red-400 @enderror"
        >{{ old('program', $receipt?->program) }}</textarea>
        @error('program')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-4">
        <label for="pax_count" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            PAX Count <span class="text-red-600">*</span>
        </label>
        <input
            id="pax_count"
            type="number"
            name="pax_count"
            min="1"
            step="1"
            value="{{ old('pax_count', $receipt?->pax_count) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('pax_count') border-red-400 @enderror"
        >
        @error('pax_count')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-12">
        <label for="other_instructions" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Other Instructions</label>
        <textarea
            id="other_instructions"
            name="other_instructions"
            rows="3"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('other_instructions') border-red-400 @enderror"
        >{{ old('other_instructions', $receipt?->other_instructions) }}</textarea>
        @error('other_instructions')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-12">
        <label class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Items <span class="text-red-600">*</span>
        </label>
        @error('items')
            <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('items.0.item_name')
            <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('items.0.quantity')
            <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('items.0.rate_price')
            <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="receiptItemsTable">
                <thead class="bg-[#f4f6f9] text-[#1f2f3e]">
                    <tr>
                        <th class="px-3 py-2 font-semibold" style="width:45%">Item Name</th>
                        <th class="px-3 py-2 font-semibold" style="width:15%">Quantity</th>
                        <th class="px-3 py-2 font-semibold" style="width:20%">Rate Price</th>
                        <th class="px-3 py-2 font-semibold" style="width:15%">Amount</th>
                        <th class="px-3 py-2 font-semibold" style="width:5%"></th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rowCount; $i++)
                        <tr class="item-row border-b border-[#eef1f5]">
                            <td class="px-2 py-2">
                                <input
                                    type="text"
                                    class="item-name w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                                    name="item_name[]"
                                    value="{{ $itemNames[$i] ?? '' }}"
                                    required
                                >
                            </td>
                            <td class="px-2 py-2">
                                <input
                                    type="number"
                                    class="item-qty w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                                    name="quantity[]"
                                    min="0.01"
                                    step="0.01"
                                    value="{{ $quantities[$i] ?? '' }}"
                                    required
                                >
                            </td>
                            <td class="px-2 py-2">
                                <input
                                    type="number"
                                    class="item-rate w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a]"
                                    name="rate_price[]"
                                    min="0"
                                    step="0.01"
                                    value="{{ $rates[$i] ?? '' }}"
                                    required
                                >
                            </td>
                            <td class="px-2 py-2">
                                <input
                                    type="text"
                                    class="item-amount w-full rounded-md border border-[#dfdfdf] bg-[#f4f6f9] px-3 py-2 text-sm"
                                    readonly
                                    value=""
                                >
                            </td>
                            <td class="px-2 py-2 text-center">
                                <button type="button" class="remove-row rounded-md border border-red-200 px-2 py-1 text-sm font-semibold text-red-700 hover:bg-red-50" title="Remove">&times;</button>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <button type="button" id="addMoreItems" class="rounded-md border border-[#04346a] px-3 py-1.5 text-sm font-semibold text-[#04346a] hover:bg-[#04346a]/5">
                Add More
            </button>
            <span class="text-xs text-[#898989]">Maximum 10 rows.</span>
        </div>
    </div>

    <div class="md:col-span-12">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
            <input
                id="include_gst"
                type="checkbox"
                name="include_gst"
                value="1"
                class="rounded border-[#dfdfdf] text-[#04346a]"
                @checked((bool) old('include_gst', $receipt?->include_gst))
            >
            Include GST (5%)
        </label>
    </div>

    <div class="md:col-span-4">
        <label for="items_total" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Items Total</label>
        <input id="items_total" type="text" readonly class="w-full rounded-md border border-[#dfdfdf] bg-[#f4f6f9] px-3 py-2.5 text-sm" value="0.00">
    </div>

    <div class="md:col-span-4" id="gst_amount_wrap">
        <label for="gst_amount" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">GST (5%)</label>
        <input
            id="gst_amount"
            type="text"
            readonly
            class="w-full rounded-md border border-[#dfdfdf] bg-[#f4f6f9] px-3 py-2.5 text-sm"
            value="0.00"
            @disabled(! old('include_gst', $receipt?->include_gst))
        >
    </div>

    <div class="md:col-span-4">
        <label for="grand_total" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Grand Total</label>
        <input id="grand_total" type="text" readonly class="w-full rounded-md border border-[#dfdfdf] bg-[#f4f6f9] px-3 py-2.5 text-sm" value="0.00">
    </div>

    <div class="md:col-span-4">
        <label for="advance_paid" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Advance Paid <span class="text-red-600">*</span>
        </label>
        <input
            id="advance_paid"
            type="number"
            name="advance_paid"
            min="0"
            step="0.01"
            value="{{ old('advance_paid', $receipt ? number_format((float) $receipt->advance_paid, 2, '.', '') : '') }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('advance_paid') border-red-400 @enderror"
        >
        @error('advance_paid')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-4">
        <label for="balance" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Balance <span class="text-red-600">*</span>
        </label>
        <input
            id="balance"
            type="number"
            name="balance"
            step="0.01"
            value="{{ old('balance', $receipt ? number_format((float) $receipt->balance, 2, '.', '') : '') }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('balance') border-red-400 @enderror"
        >
        <p class="mt-1 text-xs text-[#898989]" id="balance_hint">Auto-calculated as Total − Advance (editable).</p>
        @error('balance')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
