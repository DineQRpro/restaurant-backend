@php
    $canShowPaymentSelect = $canShowPaymentSelect ?? false;
    $canViewKot = $canViewKot ?? false;
    $canEditKot = $canEditKot ?? false;
    $canViewBill = $canViewBill ?? false;
    $kotRouteName = $kotRouteName ?? null;
    $editKotRouteName = $editKotRouteName ?? null;
    $billRouteName = $billRouteName ?? null;
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
                <th>Edit KOT</th>
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
                        @if ($canViewKot && $kotRouteName && ($row['has_items'] ?? false))
                            <button
                                type="button"
                                class="order-action-btn"
                                data-kot-url="{{ route($kotRouteName, $row['order_id']) }}"
                                title="View KOT"
                                aria-label="View KOT"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 0 1 2-2zm2 5h6v2H9V9zm0 4h6v2H9v-2z"/></svg>
                            </button>
                        @else
                            <button type="button" class="order-action-btn" disabled title="No items" aria-label="No KOT items">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10a2 2 0 0 1 2 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 0 1 2-2zm2 5h6v2H9V9zm0 4h6v2H9v-2z"/></svg>
                            </button>
                        @endif
                    </td>
                    <td data-label="Edit KOT">
                        @if ($canEditKot && $editKotRouteName && ($row['can_edit_kot'] ?? false))
                            <button
                                type="button"
                                class="order-action-btn"
                                data-edit-kot-url="{{ route($editKotRouteName, $row['order_id']) }}"
                                title="Edit KOT"
                                aria-label="Edit KOT"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                        @else
                            <button type="button" class="order-action-btn" disabled title="Edit KOT unavailable" aria-label="Edit KOT unavailable">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                        @endif
                    </td>
                    <td data-label="View Bill">
                        @if ($canViewBill && $billRouteName && ($row['can_view_bill'] ?? false))
                            <button
                                type="button"
                                class="order-action-btn"
                                data-bill-url="{{ route($billRouteName, $row['order_id']) }}"
                                title="View Bill"
                                aria-label="View Bill"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7-9.5-5.2-9.5-7 4-7 9.5-7zm0 3.5A3.5 3.5 0 1 0 15.5 12 3.5 3.5 0 0 0 12 8.5z"/></svg>
                            </button>
                        @else
                            <button type="button" class="order-action-btn" disabled title="No bill" aria-label="No bill">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c5.5 0 9.5 5.2 9.5 7s-4 7-9.5 7-9.5-5.2-9.5-7 4-7 9.5-7zm0 3.5A3.5 3.5 0 1 0 15.5 12 3.5 3.5 0 0 0 12 8.5z"/></svg>
                            </button>
                        @endif
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
                    <td colspan="10" class="orders-empty-state">No current orders.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
