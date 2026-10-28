<?php

namespace App\Services\Admin;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Table;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Services\Table\TableOccupancyService;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly TableOccupancyService $occupancy,
    ) {}

    /**
     * @return array{
     *     date: string,
     *     summary: list<array{label: string, value: int|string|null, placeholder: string|null}>,
     *     highlights: list<array{label: string, value: int|null, placeholder: string|null}>,
     *     unavailable_items: Collection<int, MenuItem>,
     *     category_count: int,
     *     menu_item_count: int,
     *     tables: Collection<int, Table>,
     *     table_counts: array{total: int, available: int, occupied: int, cleaning: int},
     *     table_statuses: list<array{label: string, css_class: string}>,
     *     track_orders: Collection<int, array<string, mixed>>,
     *     payment_methods: Collection<int, PaymentMethod>,
     *     splittable_payment_methods: Collection<int, PaymentMethod>
     * }
     */
    public function summary(): array
    {
        $unavailableItems = MenuItem::query()
            ->with('category')
            ->where('is_available', false)
            ->orderBy('name')
            ->limit(5)
            ->get();

        $tables = Table::query()
            ->active()
            ->with(['unreleasedOrders'])
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get([
                'id',
                'number',
                'description',
                'capacity',
                'code',
                'sort_order',
                'status',
            ]);

        $orderCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $occupancyService = $this->occupancy;
        $tableCounts = [
            'total' => $tables->count(),
            'available' => 0,
            'occupied' => 0,
            'cleaning' => 0,
        ];
        foreach ($tables as $table) {
            $status = $occupancyService->snapshot($table)->status;
            if ($status === TableOperationalStatus::Available) {
                $tableCounts['available']++;
            } elseif ($status === TableOperationalStatus::Occupied) {
                $tableCounts['occupied']++;
            } elseif ($status === TableOperationalStatus::Cleaning) {
                $tableCounts['cleaning']++;
            }
        }

        $amountReceived = $this->paymentService->amountReceivedToday();
        $paymentMethods = $this->activePaymentMethods();
        $trackOrders = $this->trackOrders();

        return [
            'date' => now()->timezone(config('app.timezone'))->format('D d, Y'),
            'summary' => [
                [
                    'label' => 'Total Amount Received',
                    'value' => 'Rs. '.number_format($amountReceived, 2),
                    'placeholder' => null,
                ],
                [
                    'label' => 'Total Orders',
                    'value' => (int) $orderCounts->sum(),
                    'placeholder' => null,
                ],
                [
                    'label' => 'Total Pending Orders',
                    'value' => $trackOrders->count(),
                    'placeholder' => null,
                ],
                [
                    'label' => 'Total Users',
                    'value' => User::query()->count(),
                    'placeholder' => null,
                ],
            ],
            'highlights' => [
                [
                    'label' => 'Highly Ordered',
                    'value' => null,
                    'placeholder' => 'Available when Orders are implemented',
                ],
                [
                    'label' => 'Less Ordered',
                    'value' => null,
                    'placeholder' => 'Available when Orders are implemented',
                ],
                [
                    'label' => 'Sold Out Dishes',
                    'value' => MenuItem::query()->where('is_available', false)->count(),
                    'placeholder' => null,
                ],
            ],
            'unavailable_items' => $unavailableItems,
            'category_count' => MenuCategory::query()->count(),
            'menu_item_count' => MenuItem::query()->count(),
            'tables' => $tables,
            'table_counts' => $tableCounts,
            'table_statuses' => array_map(
                fn (TableOperationalStatus $status): array => [
                    'label' => $status->label(),
                    'css_class' => $status->cssClass(),
                ],
                TableOperationalStatus::cases(),
            ),
            'track_orders' => $trackOrders,
            'payment_methods' => $paymentMethods,
            'splittable_payment_methods' => $paymentMethods
                ->filter(fn (PaymentMethod $method): bool => $method->isSplittable())
                ->values(),
        ];
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function activePaymentMethods(): Collection
    {
        return PaymentMethod::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function trackOrders(): Collection
    {
        $orders = Order::query()
            ->operational()
            ->with([
                'table:id,number',
                'dineType:id,name,slug',
                'latestBill.payments.paymentMethod:id,name,is_active',
            ])
            ->withCount('items')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        return $orders->map(function (Order $order): array {
            $bill = $order->latestBill;
            $paidAmount = 0.0;
            $balance = 0.0;
            $isSettled = false;
            $paymentMethodNames = collect();

            if ($bill) {
                $paidAmount = round((float) $bill->payments->sum('amount'), 2);
                $balance = round(max(0, (float) $bill->grand_total - $paidAmount), 2);
                $isSettled = $bill->status === BillStatus::Settled || $balance <= 0;
                $paymentMethodNames = $bill->payments
                    ->map(fn ($payment) => $payment->paymentMethod?->name)
                    ->filter()
                    ->unique()
                    ->values();
            }

            $paymentStatus = $this->paymentStatus($bill !== null, $paidAmount, $isSettled, $balance);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'has_items' => $order->items_count > 0,
                'can_edit_kot' => $order->isActive() && $order->items_count > 0 && $bill === null,
                'can_view_bill' => $bill !== null,
                'table_display' => $this->tableDisplay($order),
                'dine_type_name' => $order->dineType?->name ?? '—',
                'display_date' => $order->created_at
                    ->timezone(config('app.timezone'))
                    ->format('M j, Y \a\t g:i A'),
                'order_status' => $order->status->value,
                'order_status_label' => $order->status->label(),
                'order_status_css' => $order->status->cssClass(),
                'bill_id' => $bill?->id,
                'bill_number' => $bill?->bill_number,
                'grand_total' => $bill !== null ? (float) $bill->grand_total : null,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'can_pay' => $bill !== null && ! $isSettled && $balance > 0,
                'is_settled' => $isSettled,
                'payment_mode_label' => $paymentMethodNames->implode(' + '),
                'payment_status' => $paymentStatus['value'],
                'payment_status_label' => $paymentStatus['label'],
                'payment_status_css' => $paymentStatus['css'],
                'hide_at' => $bill?->status === BillStatus::Settled && $bill->settled_at
                    ? $bill->settled_at->copy()->addMinutes(Order::PAID_VISIBILITY_MINUTES)->toIso8601String()
                    : null,
            ];
        });
    }

    private function tableDisplay(Order $order): string
    {
        return $order->tableDisplayName();
    }

    /**
     * @return array{value: string, label: string, css: string}
     */
    private function paymentStatus(bool $hasBill, float $paidAmount, bool $isSettled, float $balance): array
    {
        if (! $hasBill) {
            return ['value' => 'pending', 'label' => 'Pending', 'css' => 'is-pending'];
        }

        if ($isSettled || $balance <= 0) {
            return ['value' => 'paid', 'label' => 'Paid', 'css' => 'is-paid'];
        }

        if ($paidAmount > 0) {
            return ['value' => 'partial', 'label' => 'Partially Paid', 'css' => 'is-partial'];
        }

        return ['value' => 'unpaid', 'label' => 'Unpaid', 'css' => 'is-unpaid'];
    }
}
