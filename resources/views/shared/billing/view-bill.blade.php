@php
    $issuedAt = $bill->issued_at?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A')
        ?? $order->created_at?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A')
        ?? '—';
@endphp

<div class="kot-popup">
    <dl class="kot-meta">
        <div>
            <dt>Bill Number</dt>
            <dd>{{ $bill->bill_number }}</dd>
        </div>
        <div>
            <dt>Order Number</dt>
            <dd>{{ $order->order_number }}</dd>
        </div>
        <div>
            <dt>Table Number</dt>
            <dd>{{ $tableDisplay }}</dd>
        </div>
        <div>
            <dt>Dine Type</dt>
            <dd>{{ $order->dineType?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt>Date/Time</dt>
            <dd>{{ $issuedAt }}</dd>
        </div>
        @if ($order->customer?->name)
            <div>
                <dt>Customer</dt>
                <dd>{{ $order->customer->name }}</dd>
            </div>
        @endif
        <div>
            <dt>Bill Status</dt>
            <dd>{{ $bill->status->label() }}</dd>
        </div>
    </dl>

    <div class="kot-items-wrap custom-scrollbar">
        <table class="kot-items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Packing Fee</th>
                    <th>Line Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bill->items as $item)
                    @php
                        $lineAmount = ((float) $item->unit_price * $item->quantity) + (float) $item->packing_fee;
                    @endphp
                    <tr>
                        <td data-label="Item">{{ $item->item_name }}</td>
                        <td data-label="Quantity">{{ $item->quantity }}</td>
                        <td data-label="Unit Price">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td data-label="Packing Fee">Rs. {{ number_format((float) $item->packing_fee, 2) }}</td>
                        <td data-label="Line Amount">Rs. {{ number_format($lineAmount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="kot-items-empty">No billed items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <dl class="bill-totals">
        <div>
            <dt>Subtotal</dt>
            <dd>Rs. {{ number_format((float) $bill->subtotal, 2) }}</dd>
        </div>
        <div>
            <dt>Discount</dt>
            <dd>Rs. {{ number_format((float) $bill->discount_amount, 2) }}</dd>
        </div>
        <div>
            <dt>Tax/GST</dt>
            <dd>Rs. {{ number_format((float) $bill->tax_amount, 2) }}</dd>
        </div>
        <div>
            <dt>Round Off</dt>
            <dd>Rs. {{ number_format((float) $bill->round_off, 2) }}</dd>
        </div>
        <div>
            <dt>Grand Total</dt>
            <dd>Rs. {{ number_format((float) $bill->grand_total, 2) }}</dd>
        </div>
        <div>
            <dt>Paid Amount</dt>
            <dd>Rs. {{ number_format($paidAmount, 2) }}</dd>
        </div>
        <div>
            <dt>Remaining Balance</dt>
            <dd>Rs. {{ number_format($remainingBalance, 2) }}</dd>
        </div>
    </dl>

    <div class="bill-payments">
        <h4>Payments</h4>
        @forelse ($bill->payments as $payment)
            <p>
                {{ $payment->paymentMethod?->name ?? 'Payment' }}
                Rs. {{ number_format((float) $payment->amount, 2) }}
            </p>
        @empty
            <p>No payments recorded.</p>
        @endforelse
    </div>

    <div class="edit-kot-actions">
        <button type="button" class="edit-kot-save" disabled title="Coming soon" aria-label="Print Bill coming soon">
            Print Bill
        </button>
    </div>
</div>
