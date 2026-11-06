@php
    /** @var \App\Models\GoodsReceipt|null $receipt */
    $receipt = $receipt ?? null;
    $lines = old('lines', $receipt?->lines?->map(fn ($line) => [
        'inventory_item_id' => $line->inventory_item_id,
        'label' => $line->item_name_snapshot.' ('.$line->item_code_snapshot.')',
        'category' => $line->category_name_snapshot,
        'unit' => $line->unit_symbol_snapshot,
        'quantity' => $line->quantity,
        'unit_price' => $line->unit_price,
        'gst_rate' => $line->gst_rate,
        'batch_number' => $line->batch_number,
        'expiry_date' => optional($line->expiry_date)->toDateString(),
        'reorder_level' => $line->item?->reorder_level,
        'maximum_level' => $line->item?->maximum_level,
    ])->all() ?? [[]]);
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="md:col-span-2">
        <label class="mb-1.5 block text-sm font-semibold" for="vendor-search">Vendor <span class="text-red-600">*</span></label>
        <input id="vendor-search" type="search" value="{{ old('vendor_label', $receipt ? $receipt->vendor_name_snapshot.' — '.$receipt->vendor_code_snapshot : '') }}" placeholder="Search vendor name or code" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm" autocomplete="off">
        <input type="hidden" name="vendor_id" id="vendor_id" value="{{ old('vendor_id', $receipt?->vendor_id) }}">
        <div id="vendor-results" class="mt-1 hidden rounded-md border border-[#eef1f5] bg-white"></div>
        <p class="mt-1 text-xs text-[#898989]">Displayed as Vendor Name — Vendor Code. The vendor code is not created here.</p>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-semibold" for="vendor_bill_number">Vendor bill number <span class="text-red-600">*</span></label>
        <input id="vendor_bill_number" name="vendor_bill_number" required maxlength="50" value="{{ old('vendor_bill_number', $receipt?->vendor_bill_number) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-semibold" for="vendor_bill_date">Vendor bill date <span class="text-red-600">*</span></label>
        <input id="vendor_bill_date" name="vendor_bill_date" type="date" required value="{{ old('vendor_bill_date', optional($receipt?->vendor_bill_date)->toDateString()) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-semibold" for="receipt_date">Receipt date <span class="text-red-600">*</span></label>
        <input id="receipt_date" name="receipt_date" type="date" required value="{{ old('receipt_date', optional($receipt?->receipt_date)->toDateString() ?? now()->toDateString()) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-semibold" for="payment_status">Payment status <span class="text-red-600">*</span></label>
        <select id="payment_status" name="payment_status" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">
            @foreach (\App\Enums\GoodsReceiptPaymentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('payment_status', $receipt?->payment_status?->value ?? 'unpaid') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1.5 block text-sm font-semibold" for="paid_amount">Paid amount <span class="text-red-600">*</span></label>
        <input id="paid_amount" name="paid_amount" type="number" min="0" step="0.01" required value="{{ old('paid_amount', $receipt?->paid_amount ?? '0.00') }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">
        <p class="mt-1 text-xs text-[#898989]">Paid equals the total. Unpaid and Credit stay at 0.00. Partially Paid is between those. Credit is not Unpaid.</p>
    </div>
    <div class="md:col-span-2">
        <label class="mb-1.5 block text-sm font-semibold" for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="2" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">{{ old('notes', $receipt?->notes) }}</textarea>
    </div>
</div>

<div class="mt-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-base font-bold text-[#1f2f3e]">Lines</h3>
        <div class="flex items-center gap-2 text-sm">
            <label><input type="radio" name="item_scope" value="low" checked> Low stock</label>
            <label><input type="radio" name="item_scope" value="all"> All items</label>
            <button type="button" id="add-line" class="rounded-md border border-[#dfdfdf] px-3 py-1.5 text-sm font-semibold">Add line</button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm" id="line-table">
            <thead class="text-left text-xs uppercase text-[#898989]">
                <tr>
                    <th class="px-2 py-2">Item</th>
                    <th class="px-2 py-2">Category</th>
                    <th class="px-2 py-2">Unit</th>
                    <th class="px-2 py-2">Stock</th>
                    <th class="px-2 py-2">Reorder</th>
                    <th class="px-2 py-2">Maximum</th>
                    <th class="px-2 py-2">Qty</th>
                    <th class="px-2 py-2">Price</th>
                    <th class="px-2 py-2">GST %</th>
                    <th class="px-2 py-2">Line total</th>
                    <th class="px-2 py-2">Batch</th>
                    <th class="px-2 py-2">Expiry</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="line-body"></tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-[#898989]">Displayed totals follow the same half-up line calculation used on save. The server recalculates them before the receipt is stored.</p>
    <dl class="mt-2 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
        <div><dt class="text-[#898989]">Subtotal</dt><dd id="display-subtotal" class="font-semibold">0.00</dd></div>
        <div><dt class="text-[#898989]">GST</dt><dd id="display-gst" class="font-semibold">0.00</dd></div>
        <div><dt class="text-[#898989]">Grand total</dt><dd id="display-grand" class="font-semibold">0.00</dd></div>
        <div><dt class="text-[#898989]">Outstanding</dt><dd id="display-outstanding" class="font-semibold">0.00</dd></div>
    </dl>
    <p id="zero-price-note" class="mt-3 hidden rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">A line has a zero unit price. Confirm below before posting. The quantity still updates stock.</p>
    <label id="zero-price-confirm" class="mt-2 hidden items-center gap-2 text-sm">
        <input type="checkbox" name="confirm_zero_prices" value="1" @checked(old('confirm_zero_prices'))>
        I confirm these items are being received at zero cost.
    </label>
</div>

<template id="line-template">
    <tr class="border-t border-[#eef1f5] align-top">
        <td class="px-2 py-2">
            <input class="item-search w-56 rounded-md border border-[#dfdfdf] px-2 py-1.5" placeholder="Search item">
            <input class="item-id" type="hidden" data-name="inventory_item_id">
            <div class="item-results mt-1 hidden rounded border bg-white"></div>
        </td>
        <td class="item-category px-2 py-2"></td>
        <td class="item-unit px-2 py-2"></td>
        <td class="item-stock px-2 py-2"></td>
        <td class="item-reorder px-2 py-2"></td>
        <td class="item-maximum px-2 py-2"></td>
        <td class="px-2 py-2"><input data-name="quantity" type="number" min="0.001" step="0.001" required class="w-24 rounded-md border border-[#dfdfdf] px-2 py-1.5"></td>
        <td class="px-2 py-2"><input data-name="unit_price" type="number" min="0" step="0.01" required class="price-input w-24 rounded-md border border-[#dfdfdf] px-2 py-1.5"></td>
        <td class="px-2 py-2"><input data-name="gst_rate" type="number" min="0" max="100" step="0.01" value="0" class="gst-input w-20 rounded-md border border-[#dfdfdf] px-2 py-1.5"></td>
        <td class="line-total px-2 py-2">0.00</td>
        <td class="px-2 py-2"><input data-name="batch_number" maxlength="50" class="w-24 rounded-md border border-[#dfdfdf] px-2 py-1.5"></td>
        <td class="px-2 py-2"><input data-name="expiry_date" type="date" class="rounded-md border border-[#dfdfdf] px-2 py-1.5"></td>
        <td class="px-2 py-2"><button type="button" class="remove-line text-red-700">Remove</button></td>
    </tr>
</template>

<script>
    const existingLines = @json($lines);
    const body = document.getElementById('line-body');
    const template = document.getElementById('line-template');

    function scope() {
        return document.querySelector('input[name="item_scope"]:checked')?.value || 'low';
    }

    function cents(value) {
        const [whole, fraction = ''] = String(value || '0').replace(/[^0-9.]/g, '').split('.');
        return (Number(whole) * 100) + Number((fraction + '00').slice(0, 2));
    }

    function formatCents(value) {
        return (value / 100).toFixed(2);
    }

    function lineAmounts(quantity, price, rate) {
        const qtyMilli = Math.round(Number(quantity || 0) * 1000);
        const taxableCents = Math.floor((qtyMilli * cents(price) + 500) / 1000);
        const gstCents = Math.floor((taxableCents * cents(rate) + 5000) / 10000);

        return {
            taxable: formatCents(taxableCents),
            gst: formatCents(gstCents),
            total: formatCents(taxableCents + gstCents),
        };
    }

    function renumber() {
        let subtotal = 0;
        let gstTotal = 0;
        let grand = 0;
        [...body.querySelectorAll('tr')].forEach((row, index) => {
            row.querySelectorAll('[data-name]').forEach((input) => {
                input.name = `lines[${index}][${input.dataset.name}]`;
            });
            const amounts = lineAmounts(
                row.querySelector('[data-name="quantity"]').value,
                row.querySelector('[data-name="unit_price"]').value,
                row.querySelector('[data-name="gst_rate"]').value,
            );
            row.querySelector('.line-total').textContent = amounts.total;
            subtotal += cents(amounts.taxable);
            gstTotal += cents(amounts.gst);
            grand += cents(amounts.total);
        });
        const paid = cents(document.getElementById('paid_amount').value);
        document.getElementById('display-subtotal').textContent = (subtotal / 100).toFixed(2);
        document.getElementById('display-gst').textContent = (gstTotal / 100).toFixed(2);
        document.getElementById('display-grand').textContent = (grand / 100).toFixed(2);
        document.getElementById('display-outstanding').textContent = ((grand - paid) / 100).toFixed(2);
        const zero = [...body.querySelectorAll('.price-input')].some((input) => input.value !== '' && Number(input.value) === 0);
        document.getElementById('zero-price-note').classList.toggle('hidden', !zero);
        document.getElementById('zero-price-confirm').classList.toggle('hidden', !zero);
        document.getElementById('zero-price-confirm').classList.toggle('flex', zero);
    }

    function addLine(data = {}) {
        const row = template.content.firstElementChild.cloneNode(true);
        body.appendChild(row);
        const search = row.querySelector('.item-search');
        search.value = data.label || '';
        row.querySelector('.item-id').value = data.inventory_item_id || '';
        row.querySelector('.item-category').textContent = data.category || '';
        row.querySelector('.item-unit').textContent = data.unit || '';
        row.querySelector('.item-reorder').textContent = data.reorder_level || '';
        row.querySelector('.item-maximum').textContent = data.maximum_level || '';
        row.querySelector('[data-name="quantity"]').value = data.quantity || '';
        row.querySelector('[data-name="unit_price"]').value = data.unit_price ?? '';
        row.querySelector('[data-name="gst_rate"]').value = data.gst_rate ?? '0';
        row.querySelector('[data-name="batch_number"]').value = data.batch_number || '';
        row.querySelector('[data-name="expiry_date"]').value = data.expiry_date || '';
        row.querySelector('.remove-line').addEventListener('click', () => {
            if (body.children.length > 1) {
                row.remove();
                renumber();
            }
        });
        search.addEventListener('input', () => searchItems(row, search.value));
        row.querySelector('.price-input').addEventListener('input', renumber);
        row.querySelector('[data-name="quantity"]').addEventListener('input', renumber);
        row.querySelector('.gst-input').addEventListener('input', renumber);
        renumber();
    }

    async function searchItems(row, query) {
        const results = row.querySelector('.item-results');
        if (query.trim().length < 1) {
            results.classList.add('hidden');
            return;
        }
        const response = await fetch(`{{ route('admin.inventory.receipts.items') }}?scope=${scope()}&q=${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json' } });
        const items = await response.json();
        results.innerHTML = items.map((item) => `<button type="button" class="block w-full px-2 py-1 text-left hover:bg-[#f4f6f9]" data-item='${JSON.stringify(item).replaceAll("'", "&#39;")}'>${item.name} (${item.code})</button>`).join('') || '<p class="px-2 py-1 text-[#898989]">No items</p>';
        results.classList.remove('hidden');
        results.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', () => {
                const item = JSON.parse(button.dataset.item);
                row.querySelector('.item-id').value = item.id;
                row.querySelector('.item-search').value = `${item.name} (${item.code})`;
                row.querySelector('.item-category').textContent = item.category || '';
                row.querySelector('.item-unit').textContent = item.symbol || '';
                row.querySelector('.item-stock').textContent = item.current_stock;
                row.querySelector('.item-reorder').textContent = item.reorder_level;
                row.querySelector('.item-maximum').textContent = item.maximum_level;
                results.classList.add('hidden');
            });
        });
    }

    document.getElementById('add-line').addEventListener('click', () => addLine());
    document.getElementById('paid_amount').addEventListener('input', renumber);
    (existingLines.length ? existingLines : [{}]).forEach((line) => addLine(line));

    const vendorSearch = document.getElementById('vendor-search');
    const vendorResults = document.getElementById('vendor-results');
    vendorSearch.addEventListener('input', async () => {
        const response = await fetch(`{{ route('admin.inventory.receipts.vendors') }}?q=${encodeURIComponent(vendorSearch.value)}`, { headers: { 'Accept': 'application/json' } });
        const vendors = await response.json();
        vendorResults.innerHTML = vendors.map((vendor) => `<button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-[#f4f6f9]" data-id="${vendor.id}" data-label="${vendor.label}">${vendor.label}</button>`).join('');
        vendorResults.classList.remove('hidden');
        vendorResults.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById('vendor_id').value = button.dataset.id;
                vendorSearch.value = button.dataset.label;
                vendorResults.classList.add('hidden');
            });
        });
    });
</script>
