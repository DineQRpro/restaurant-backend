@php
    use App\Enums\OrderItemStatus;

    $orderedAt = $order->created_at?->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A') ?? '—';
@endphp

<form class="kot-popup" data-edit-kot-form data-action="{{ $updateUrl }}">
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
            <dt>Date/Time</dt>
            <dd>{{ $orderedAt }}</dd>
        </div>
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
                    <th>Cancel</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($order->items as $item)
                    @php
                        $locked = in_array($item->status, [OrderItemStatus::Served, OrderItemStatus::Cancelled], true);
                        $statusOptions = array_values(array_filter(
                            OrderItemStatus::cases(),
                            fn (OrderItemStatus $status): bool => $item->status->canTransitionTo($status),
                        ));
                    @endphp
                    <tr data-edit-kot-item data-item-id="{{ $item->id }}">
                        <td data-label="Item">{{ $item->menuItem?->name ?? '—' }}</td>
                        <td data-label="Quantity">
                            <input
                                type="number"
                                name="items[{{ $item->id }}][quantity]"
                                class="edit-kot-input"
                                min="1"
                                step="1"
                                value="{{ $item->quantity }}"
                                @disabled($locked)
                            >
                        </td>
                        <td data-label="Status">
                            @if ($locked)
                                <input type="hidden" name="items[{{ $item->id }}][status]" value="{{ $item->status->value }}">
                                <span class="order-status-pill {{ $item->status->cssClass() }}">{{ $item->status->label() }}</span>
                            @else
                                <select name="items[{{ $item->id }}][status]" class="edit-kot-input">
                                    @foreach ($statusOptions as $status)
                                        <option value="{{ $status->value }}" @selected($status === $item->status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </td>
                        <td data-label="Special Instructions">
                            <textarea
                                name="items[{{ $item->id }}][special_instructions]"
                                class="edit-kot-input"
                                rows="2"
                                maxlength="500"
                                @disabled($locked)
                            >{{ $item->special_instructions }}</textarea>
                        </td>
                        <td data-label="Cancel">
                            @if ($locked)
                                —
                            @else
                                <button type="button" class="edit-kot-cancel" data-cancel-item>Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="kot-items-empty">No items on this KOT.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="kot-modal-status hidden" data-edit-kot-error></p>

    <div class="edit-kot-actions">
        <button type="submit" class="edit-kot-save">Save KOT</button>
    </div>
</form>
