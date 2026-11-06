<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        body { font-family: sans-serif; color: #16324f; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #eef1f5; text-align: left; padding: 6px; font-size: 13px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>{{ $receipt->receipt_number }}</h1>
    <p>{{ $receipt->vendor_name_snapshot }} — {{ $receipt->vendor_code_snapshot }}</p>
    <p>Bill {{ $receipt->vendor_bill_number }} dated {{ $receipt->vendor_bill_date->toDateString() }}</p>
    <p>Receipt date {{ $receipt->receipt_date->toDateString() }} · {{ $receipt->status->label() }} · {{ $receipt->payment_status->label() }}</p>
    <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Price</th><th>GST</th><th>Total</th></tr></thead>
        <tbody>
            @foreach ($receipt->lines as $line)
                <tr>
                    <td>{{ $line->item_name_snapshot }}</td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ $line->unit_symbol_snapshot }}</td>
                    <td>{{ $line->unit_price }}</td>
                    <td>{{ $line->gst_amount }}</td>
                    <td>{{ $line->line_total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>Subtotal {{ $receipt->subtotal }} · GST {{ $receipt->gst_total }} · Grand total {{ $receipt->grand_total }}</p>
    <p>Paid {{ $receipt->paid_amount }} · Outstanding {{ $receipt->outstanding_amount }}</p>
    @if ($receipt->void_reason)
        <p>Voided: {{ $receipt->void_reason }}</p>
    @endif
</body>
</html>
