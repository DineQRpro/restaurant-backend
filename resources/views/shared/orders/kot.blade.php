@php
    $orderedAt = $order->created_at?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A') ?? '—';
@endphp

<div class="kot-popup">
    <dl class="kot-meta">
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
            <dt>Order Date/Time</dt>
            <dd>{{ $orderedAt }}</dd>
        </div>
        @if ($order->waiter?->name)
            <div>
                <dt>Waiter</dt>
                <dd>{{ $order->waiter->name }}</dd>
            </div>
        @endif
        @if ($order->customer?->name)
            <div>
                <dt>Customer</dt>
                <dd>{{ $order->customer->name }}</dd>
            </div>
        @endif
        @if ($order->pax && $order->dineType?->requiresTable())
            <div>
                <dt>Pax</dt>
                <dd>{{ $order->pax }}</dd>
            </div>
        @endif
    </dl>

    <div class="kot-items-wrap custom-scrollbar">
        <table class="kot-items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Special Instructions</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->items as $item)
                    <tr>
                        <td data-label="Item">{{ $item->menuItem?->name ?? '—' }}</td>
                        <td data-label="Quantity">{{ $item->quantity }}</td>
                        <td data-label="Status">
                            <span class="order-status-pill {{ $item->status->cssClass() }}">{{ $item->status->label() }}</span>
                        </td>
                        <td data-label="Special Instructions">
                            @if (filled($item->special_instructions))
                                {{ $item->special_instructions }}
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Price">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="kot-items-empty">No items on this KOT.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
