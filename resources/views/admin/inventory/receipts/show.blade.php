@extends('layouts.admin')

@section('title', $receipt->receipt_number)
@section('page-title', 'Goods Receipts')
@section('page-subtitle', 'View receipt')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold">{{ $receipt->receipt_number }}</h2>
            <p class="text-sm text-[#898989]">{{ $receipt->status->label() }} · {{ $receipt->payment_status->label() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.inventory.receipts.print', $receipt) }}" class="rounded-md border px-3 py-2 text-sm font-semibold">Print</a>
            @if ($receipt->status->value === 'draft' && auth()->user()->hasPermission('inventory.receipt.update'))
                <a href="{{ route('admin.inventory.receipts.edit', $receipt) }}" class="rounded-md border px-3 py-2 text-sm font-semibold">Edit</a>
            @endif
        </div>
    </div>
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
            <div><dt class="text-[#898989]">Vendor</dt><dd class="font-semibold">{{ $receipt->vendor_name_snapshot }} — {{ $receipt->vendor_code_snapshot }}</dd></div>
            <div><dt class="text-[#898989]">Vendor bill</dt><dd>{{ $receipt->vendor_bill_number }} · {{ $receipt->vendor_bill_date->toDateString() }}</dd></div>
            <div><dt class="text-[#898989]">Receipt date</dt><dd>{{ $receipt->receipt_date->toDateString() }}</dd></div>
            <div><dt class="text-[#898989]">Subtotal</dt><dd>{{ $receipt->subtotal }}</dd></div>
            <div><dt class="text-[#898989]">GST</dt><dd>{{ $receipt->gst_total }}</dd></div>
            <div><dt class="text-[#898989]">Grand total</dt><dd class="font-semibold">{{ $receipt->grand_total }}</dd></div>
            <div><dt class="text-[#898989]">Paid</dt><dd>{{ $receipt->paid_amount }}</dd></div>
            <div><dt class="text-[#898989]">Outstanding</dt><dd>{{ $receipt->outstanding_amount }}</dd></div>
            <div><dt class="text-[#898989]">Created by</dt><dd>{{ $receipt->creator?->name }}</dd></div>
        </dl>
        @if ($receipt->void_reason)
            <p class="mt-4 text-sm">Voided {{ $receipt->voided_at }} by {{ $receipt->voider?->name }}: {{ $receipt->void_reason }}</p>
        @endif
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-[#898989]"><tr><th class="py-2">Item</th><th>Category</th><th>Unit</th><th>Qty</th><th>Price</th><th>GST</th><th>Line total</th><th>Batch</th><th>Expiry</th></tr></thead>
                <tbody>
                    @foreach ($receipt->lines as $line)
                        <tr class="border-t">
                            <td class="py-2">{{ $line->item_name_snapshot }} ({{ $line->item_code_snapshot }})</td>
                            <td>{{ $line->category_name_snapshot }}</td>
                            <td>{{ $line->unit_symbol_snapshot }}</td>
                            <td>{{ $line->quantity }}</td>
                            <td>{{ $line->unit_price }}</td>
                            <td>{{ $line->gst_rate }}% / {{ $line->gst_amount }}</td>
                            <td>{{ $line->line_total }}</td>
                            <td>{{ $line->batch_number }}</td>
                            <td>{{ optional($line->expiry_date)->toDateString() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($receipt->status->value === 'draft' && auth()->user()->hasPermission('inventory.receipt.post'))
            <form method="POST" action="{{ route('admin.inventory.receipts.post', $receipt) }}" class="mt-5">
                @csrf
                @if ($receipt->lines->contains(fn ($line) => (float) $line->unit_price == 0.0))
                    <label class="mb-3 flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_zero_prices" value="1"> I confirm zero-cost lines should be posted.</label>
                @endif
                <button class="rounded-md bg-[#04346a] px-4 py-2 text-sm font-semibold text-white">Post receipt</button>
            </form>
            @if (auth()->user()->hasPermission('inventory.receipt.update'))
                <form method="POST" action="{{ route('admin.inventory.receipts.destroy', $receipt) }}" class="mt-3" onsubmit="return confirm('Delete this draft?')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm font-semibold text-red-700">Delete draft</button>
                </form>
            @endif
        @endif
        @if ($receipt->status->value === 'posted' && auth()->user()->hasPermission('inventory.receipt.void'))
            <form method="POST" action="{{ route('admin.inventory.receipts.void', $receipt) }}" class="mt-5 max-w-xl">
                @csrf
                <label class="mb-1 block text-sm font-semibold" for="void_reason">Void reason</label>
                <input id="void_reason" name="void_reason" required maxlength="255" class="w-full rounded-md border px-3 py-2 text-sm">
                <button class="mt-3 rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-700">Void receipt</button>
            </form>
        @endif
    </section>
@endsection
