@php
    $canShowPaymentSelect = $canShowPaymentSelect ?? false;
    $paymentInteractive = $paymentInteractive ?? false;
    $chooseMethodLabel = $chooseMethodLabel ?? 'Choose Method';
    $paymentStoreRouteName = $paymentStoreRouteName ?? 'admin.bills.payments.store';
    $splitStoreRouteName = $splitStoreRouteName ?? null;
    $selectablePaymentMethods = $selectablePaymentMethods ?? $dashboard['payment_methods'];
@endphp

<div class="orders-table-shell overflow-x-auto">
    <table class="orders-table w-full text-left text-sm">
        <thead>
            <tr>
                <th>Order#</th>
                <th>Table#</th>
                <th>Type</th>
                <th>KOT</th>
                <th>View Bill</th>
                <th>Date</th>
                <th>Status</th>
                <th>Payment Mode</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($dashboard['track_orders'] as $row)
                <tr class="order-row" data-order-number="{{ $row['order_number'] }}">
                    <td data-label="Order#">
                        <span class="order-id-chip">{{ $row['order_number'] }}</span>
                    </td>
                    <td data-label="Table#">
                        <span class="order-table-chip">{{ $row['table_display'] }}</span>
                    </td>
                    <td data-label="Type">
                        <span class="order-type-pill">{{ $row['dine_type_name'] }}</span>
                    </td>
                    <td data-label="KOT">
                        <button type="button" class="order-action-btn" disabled title="Coming soon" aria-label="KOT coming soon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 0 1 2-2zm2 5h6v2H9V9zm0 4h6v2H9v-2z"/></svg>
                        </button>
                    </td>
                    <td data-label="View Bill">
                        <button type="button" class="order-action-btn" disabled title="Coming soon" aria-label="View Bill coming soon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7-9.5-5.2-9.5-7 4-7 9.5-7zm0 3.5A3.5 3.5 0 1 0 15.5 12 3.5 3.5 0 0 0 12 8.5z"/></svg>
                        </button>
                    </td>
                    <td data-label="Date">
                        <span class="order-date-text">{{ $row['display_date'] }}</span>
                    </td>
                    <td data-label="Status">
                        <span class="order-status-pill {{ $row['order_status_css'] }}">{{ $row['order_status_label'] }}</span>
                    </td>
                    <td data-label="Payment Mode">
                        <div class="payment-mode-cell">
                            @if ($row['payment_mode_label'] !== '')
                                <span class="payment-mode-locked">{{ $row['payment_mode_label'] }}</span>
                            @endif

                            @if ($row['can_pay'] && $canShowPaymentSelect && $selectablePaymentMethods->isNotEmpty())
                                <select
                                    class="payment-mode-select"
                                    @if ($paymentInteractive)
                                        data-action="{{ route($paymentStoreRouteName, $row['bill_id']) }}"
                                        @if ($splitStoreRouteName)
                                            data-split-action="{{ route($splitStoreRouteName, $row['bill_id']) }}"
                                        @endif
                                        data-bill-id="{{ $row['bill_id'] }}"
                                        data-order-number="{{ $row['order_number'] }}"
                                        data-table-number="{{ $row['table_display'] }}"
                                        data-bill-number="{{ $row['bill_number'] }}"
                                        data-grand-total="{{ number_format($row['grand_total'], 2, '.', '') }}"
                                        data-paid-amount="{{ number_format($row['paid_amount'], 2, '.', '') }}"
                                        data-balance="{{ number_format($row['balance'], 2, '.', '') }}"
                                    @else
                                        data-display-only
                                    @endif
                                >
                                    <option value="">{{ $chooseMethodLabel }}</option>
                                    @foreach ($selectablePaymentMethods as $method)
                                        <option
                                            value="{{ $method->id }}"
                                            data-slug="{{ $method->slug }}"
                                            data-requires-reference="{{ $method->requiresReference() ? '1' : '0' }}"
                                        >{{ $method->displayLabel() }}</option>
                                    @endforeach
                                </select>
                            @elseif ($row['payment_mode_label'] === '')
                                <span class="payment-pending-text">—</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="Status">
                        <span class="order-status-pill {{ $row['payment_status_css'] }}">{{ $row['payment_status_label'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="orders-empty-state">No current orders.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
