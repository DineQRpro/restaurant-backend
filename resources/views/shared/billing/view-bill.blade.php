@php
    $issuedAt = $bill->issued_at?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A')
        ?? $order->created_at?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A')
        ?? '—';
    $selectedDiscount = old('discount_id', $bill->discount_id);
    $isCustomSelected = $bill->discount?->type?->value === 'custom';
    $additionalChargesValue = old('additional_charges', number_format((float) $bill->additional_charges, 2, '.', ''));
@endphp

<div
    class="kot-popup"
    data-view-bill
    data-has-unapplied-bill-changes="false"
>
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

    @if ($canApplyAdjustments)
        <form
            class="bill-adjustments-form"
            data-bill-adjustments-form
            data-action="{{ $adjustmentsApplyUrl }}"
            data-applied="{{ json_encode($appliedState) }}"
        >
            @csrf
    @endif

    <div class="kot-items-wrap custom-scrollbar">
        <table class="kot-items-table bill-summary-table">
            <thead>
                <tr>
                    <th>Product Title</th>
                    <th class="num">Quantity</th>
                    <th class="num">Price</th>
                    <th class="num">Packing Charge</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bill->items as $item)
                    @php
                        $lineTotal = ((float) $item->unit_price * $item->quantity) + (float) $item->packing_fee;
                    @endphp
                    <tr>
                        <td data-label="Product Title">{{ $item->item_name }}</td>
                        <td class="num" data-label="Quantity">{{ $item->quantity }}</td>
                        <td class="num" data-label="Price">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="num" data-label="Packing Charge">{{ number_format((float) $item->packing_fee, 2) }}</td>
                        <td class="num bill-line-total" data-label="Total">{{ number_format($lineTotal, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="kot-items-empty">No billed items.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="bill-summary-foot">
                <tr>
                    <td colspan="4">Total Summary</td>
                    <td class="num bill-amount" data-bill-field="total_summary">{{ $summary['total_summary'] }}</td>
                </tr>
                <tr class="bill-discount-row">
                    <td>Discount</td>
                    <td colspan="3">
                        @if ($canApplyAdjustments)
                            <div class="bill-discount-fields">
                                <select
                                    name="discount_id"
                                    class="bill-discount-select"
                                    data-bill-discount-select
                                    aria-label="Select Discount"
                                >
                                    <option value="">Select Discount</option>
                                    @foreach ($discountOptions as $option)
                                        <option
                                            value="{{ $option->id }}"
                                            data-type="{{ $option->type->value }}"
                                            data-min="{{ $option->min_level }}"
                                            data-max="{{ $option->max_level }}"
                                            @selected((string) $selectedDiscount === (string) $option->id)
                                        >
                                            {{ $option->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div
                                    class="bill-discount-custom {{ $isCustomSelected ? '' : 'hidden' }}"
                                    data-bill-discount-custom
                                >
                                    <input
                                        type="number"
                                        name="custom_percentage"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="bill-discount-input"
                                        placeholder="Enter Discount (%)"
                                        value="{{ old('custom_percentage', $isCustomSelected ? $bill->other_discount_percentage : '') }}"
                                        data-bill-custom-percentage
                                    >
                                    <input
                                        type="text"
                                        name="comment"
                                        class="bill-discount-input"
                                        placeholder="Comments"
                                        maxlength="255"
                                        value="{{ old('comment', $bill->other_discount_comment) }}"
                                        data-bill-discount-comment
                                    >
                                </div>
                            </div>
                        @else
                            <span>{{ $bill->discount?->name ?? 'Select Discount' }}</span>
                        @endif
                    </td>
                    <td class="num bill-amount bill-discount-amount" data-bill-field="discount_amount">{{ $summary['discount_amount'] }}</td>
                </tr>
                <tr>
                    <td colspan="4">Taxable Amount</td>
                    <td class="num bill-amount" data-bill-field="taxable_amount">{{ $summary['taxable_amount'] }}</td>
                </tr>
                <tr>
                    <td colspan="4" data-bill-field="cgst_label">{{ $summary['cgst_label'] }}</td>
                    <td class="num bill-amount" data-bill-field="cgst_amount">{{ $summary['cgst_amount'] }}</td>
                </tr>
                <tr>
                    <td colspan="4" data-bill-field="sgst_label">{{ $summary['sgst_label'] }}</td>
                    <td class="num bill-amount" data-bill-field="sgst_amount">{{ $summary['sgst_amount'] }}</td>
                </tr>
                <tr>
                    <td colspan="4">GST Total</td>
                    <td class="num bill-amount" data-bill-field="gst_total">{{ $summary['gst_total'] }}</td>
                </tr>
                <tr>
                    <td colspan="4">Additional Charges</td>
                    <td class="num bill-amount">
                        @if ($canApplyAdjustments)
                            <input
                                type="number"
                                name="additional_charges"
                                min="0"
                                max="99999999.99"
                                step="0.01"
                                class="bill-additional-input"
                                value="{{ $additionalChargesValue }}"
                                data-bill-additional-charges
                                data-bill-field="additional_charges"
                                aria-label="Additional Charges"
                            >
                        @else
                            <span data-bill-field="additional_charges">{{ $summary['additional_charges'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr class="is-grand">
                    <td colspan="4">Grand Total</td>
                    <td class="num bill-amount" data-bill-field="grand_total">{{ $summary['grand_total'] }}</td>
                </tr>
                <tr>
                    <td colspan="4">Rounded Off</td>
                    <td class="num bill-amount" data-bill-field="round_off">{{ $summary['round_off'] }}</td>
                </tr>
                <tr class="is-grand">
                    <td colspan="4">Payable Amount</td>
                    <td class="num bill-amount" data-bill-field="payable_amount">{{ $summary['payable_amount'] }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <dl class="bill-payments-meta">
        <div>
            <dt>Paid Amount</dt>
            <dd data-bill-field="paid_amount">{{ number_format($paidAmount, 2) }}</dd>
        </div>
        <div>
            <dt>Remaining Balance</dt>
            <dd data-bill-field="remaining_balance">{{ number_format($remainingBalance, 2) }}</dd>
        </div>
    </dl>

    <div class="bill-payments">
        <h4>Payments</h4>
        @forelse ($bill->payments as $payment)
            <p>
                {{ $payment->paymentMethod?->name ?? 'Payment' }}
                {{ number_format((float) $payment->amount, 2) }}
            </p>
        @empty
            <p>No payments recorded.</p>
        @endforelse
    </div>

    @if ($canApplyAdjustments)
            <p class="bill-discount-error hidden" data-bill-adjustments-error></p>
            <div class="edit-kot-actions">
                <button type="submit" class="bill-apply" data-bill-apply disabled>
                    Apply
                </button>
                <a
                    href="{{ $printUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="edit-kot-save"
                    data-bill-print
                    data-print-blocked-reason="ready"
                    title="Print Bill"
                    aria-label="Print Bill"
                >
                    Print Bill
                </a>
            </div>
        </form>
    @else
        <div class="edit-kot-actions">
            <a
                href="{{ $printUrl }}"
                target="_blank"
                rel="noopener"
                class="edit-kot-save"
                data-bill-print
                data-print-blocked-reason="ready"
                title="Print Bill"
                aria-label="Print Bill"
            >
                Print Bill
            </a>
        </div>
    @endif
</div>
