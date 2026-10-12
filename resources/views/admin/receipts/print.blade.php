<!DOCTYPE html>
<html lang="en" class="print-bill-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thermal Receipt</title>
    <style>
        {!! file_get_contents(resource_path('css/print-bill.css')) !!}
    </style>
</head>
<body class="print-bill-page" data-print-receipt onload="window.print()">
    <div class="print-bill-screen-actions no-print">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <div class="print-bill-receipt">
        @if ($restaurant['name'] !== '')
            <div class="print-bill-center print-bill-big">{{ $restaurant['name'] }}</div>
        @endif
        <div class="print-bill-center">
            @if ($restaurant['address'] !== '')
                {{ $restaurant['address'] }}<br>
            @endif
            @if ($restaurant['phone'] !== '')
                Ph: {{ $restaurant['phone'] }}
            @endif
            @if ($receipt->include_gst && $restaurant['gstin'] !== '')
                <br>GSTIN: {{ $restaurant['gstin'] }}
            @endif
        </div>

        <hr class="print-bill-rule">

        <div class="print-bill-center print-bill-bold">RECEIPT</div>

        <div class="print-bill-row">
            <div class="print-bill-first">Date: {{ $receipt->receipt_date?->format('d/m/Y') }}</div>
            <div>Reciept No: {{ $receipt->receiptNumber() }}</div>
        </div>

        <hr class="print-bill-rule">

        <div class="print-bill-row">
            <div class="print-bill-first">Customer: {{ $receipt->customer_name }}</div>
            <div>Mobile: {{ $receipt->mobile }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">PAX: {{ $receipt->pax_count }}</div>
        </div>

        @if ($receipt->program !== '')
            <div style="margin-top:4px;">
                <div class="print-bill-center print-bill-bold">Program:</div>
                <div>{!! nl2br(e($receipt->program)) !!}</div>
            </div>
        @endif

        @if (filled($receipt->other_instructions))
            <div style="margin-top:4px;">
                <div class="print-bill-center print-bill-bold">Other Instructions:</div>
                <div>{!! nl2br(e($receipt->other_instructions)) !!}</div>
            </div>
        @endif

        <hr class="print-bill-rule">

        <div class="print-bill-item print-bill-bold">
            <div class="print-bill-col-item">Item</div>
            <div class="print-bill-col-qty">Qty</div>
            <div class="print-bill-col-rate">Rate</div>
            <div class="print-bill-col-amt">Amt</div>
        </div>

        <hr class="print-bill-rule">

        @foreach ($receipt->items as $item)
            <div class="print-bill-item">
                <div class="print-bill-col-item">{{ $item->item_name }}</div>
                <div class="print-bill-col-qty">{{ $item->formattedQuantity() }}</div>
                <div class="print-bill-col-rate">{{ number_format((float) $item->rate_price, 2) }}</div>
                <div class="print-bill-col-amt">{{ number_format((float) $item->amount, 2) }}</div>
            </div>
        @endforeach

        <hr class="print-bill-rule">

        <div class="print-bill-row">
            <div class="print-bill-bold">Total</div>
            <div class="print-bill-bold">{{ number_format($itemsTotal, 2) }}</div>
        </div>
        @if ($receipt->include_gst)
            <div class="print-bill-row">
                <div>GST (5%)</div>
                <div>{{ number_format($gstAmount, 2) }}</div>
            </div>
            <div class="print-bill-row">
                <div class="print-bill-bold">Grand Total</div>
                <div class="print-bill-bold">{{ number_format($grandTotal, 2) }}</div>
            </div>
        @endif
        <div class="print-bill-row">
            <div>Advance Paid</div>
            <div>{{ number_format((float) $receipt->advance_paid, 2) }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-bold">Balance</div>
            <div class="print-bill-bold">{{ number_format((float) $receipt->balance, 2) }}</div>
        </div>

        <hr class="print-bill-rule">
        <div class="print-bill-center">Thank You</div>
    </div>
</body>
</html>
