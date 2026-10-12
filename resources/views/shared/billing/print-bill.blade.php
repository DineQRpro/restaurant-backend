<!DOCTYPE html>
<html lang="en" class="print-bill-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thermal Bill</title>
    <style>
        {!! file_get_contents(resource_path('css/print-bill.css')) !!}
    </style>
</head>
<body class="print-bill-page" data-print-bill onload="window.print()">
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
                Ph: {{ $restaurant['phone'] }}<br>
            @endif
            @if ($restaurant['gstin'] !== '')
                GSTIN: {{ $restaurant['gstin'] }}
            @endif
        </div>

        <hr class="print-bill-rule">

        <div class="print-bill-center print-bill-bold">TAX INVOICE</div>

        <div class="print-bill-row">
            <div class="print-bill-first">Date: {{ $issuedAt }}</div>
            <div>Bill No: {{ $bill->bill_number }}</div>
        </div>

        <hr class="print-bill-rule">

        @if ($isTakeaway)
            <div class="print-bill-row">
                <div>Take Away</div>
                <div>Order No: {{ $order->order_number }}</div>
            </div>
        @else
            <div class="print-bill-row">
                <div>Waiter: {{ $order->waiter?->name }}</div>
                <div style="min-width:0;overflow-wrap:anywhere;">Table: {{ $tableDisplay }}</div>
                <div>Order No: {{ $order->order_number }}</div>
            </div>
        @endif

        <div class="print-bill-row">
            <div class="print-bill-first">Customer: {{ $order->customer?->name }}</div>
            <div>Mobile: {{ $maskedPhone }}</div>
        </div>

        <hr class="print-bill-rule">

        <div class="print-bill-item print-bill-bold">
            <div class="print-bill-col-item">Item</div>
            <div class="print-bill-col-qty">Qty</div>
            <div class="print-bill-col-rate">Rate</div>
            <div class="print-bill-col-amt">Amt</div>
        </div>

        <hr class="print-bill-rule">

        @foreach ($bill->items as $item)
            @php
                $lineAmount = (float) $item->unit_price * $item->quantity;
            @endphp
            <div class="print-bill-item">
                <div class="print-bill-col-item">
                    {{ $item->item_name }}
                    @if ((float) $item->packing_fee != 0.0)
                        <span class="print-bill-packing">Pk {{ number_format((float) $item->packing_fee, 2) }}</span>
                    @endif
                </div>
                <div class="print-bill-col-qty">{{ $item->quantity }}</div>
                <div class="print-bill-col-rate">{{ number_format((float) $item->unit_price, 2) }}</div>
                <div class="print-bill-col-amt">{{ number_format($lineAmount, 2) }}</div>
            </div>
        @endforeach

        <hr class="print-bill-rule">

        <div class="print-bill-row print-bill-bold">
            <div class="print-bill-first">Total Summary</div>
            <div class="print-bill-second" data-print-field="total_summary">{{ $summary['total_summary'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">
                Discount
                @if ($discountLabel !== '' && $discountLabel !== 'Discount')
                    ({{ $discountLabel }})
                @endif
            </div>
            <div class="print-bill-second" data-print-field="discount_amount">{{ $summary['discount_amount'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">Taxable Amount</div>
            <div class="print-bill-second" data-print-field="taxable_amount">{{ $summary['taxable_amount'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first" data-print-field="cgst_label">{{ $summary['cgst_label'] }}</div>
            <div class="print-bill-second" data-print-field="cgst_amount">{{ $summary['cgst_amount'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first" data-print-field="sgst_label">{{ $summary['sgst_label'] }}</div>
            <div class="print-bill-second" data-print-field="sgst_amount">{{ $summary['sgst_amount'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">GST Total</div>
            <div class="print-bill-second" data-print-field="gst_total">{{ $summary['gst_total'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">Additional Charges</div>
            <div class="print-bill-second" data-print-field="additional_charges">{{ $summary['additional_charges'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">Grand Total</div>
            <div class="print-bill-second" data-print-field="grand_total">{{ $summary['grand_total'] }}</div>
        </div>
        <div class="print-bill-row">
            <div class="print-bill-first">Rounded Off</div>
            <div class="print-bill-second" data-print-field="round_off">{{ $summary['round_off'] }}</div>
        </div>

        <hr class="print-bill-rule">

        <div class="print-bill-row print-bill-bold print-bill-payable">
            <div class="print-bill-first">Payable Amount</div>
            <div class="print-bill-second" data-print-field="payable_amount">{{ $summary['payable_amount'] }}</div>
        </div>

        <hr class="print-bill-rule">

        <br>

        <div class="print-bill-thanks">Thank You! Visit Again</div>
    </div>
</body>
</html>
