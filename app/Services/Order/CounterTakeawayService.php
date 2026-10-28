<?php

namespace App\Services\Order;

use App\Enums\OrderItemStatus;
use App\Exceptions\BusinessConflictException;
use App\Models\DineType;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuType;
use App\Models\Order;
use App\Models\User;
use App\Services\Billing\BillCalculationService;
use App\Services\Billing\BillingService;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CounterTakeawayService
{
    public const SESSION_KEY = 'counter_takeaway';

    public const PACKING_STEP = 10;

    public function __construct(
        private readonly OrderService $orders,
        private readonly CustomerService $customers,
        private readonly BillingService $billing,
        private readonly BillCalculationService $calculator,
    ) {}

    /**
     * @return array{customer_name: string, mobile: string, items: array<int, array{quantity: int, packing_fee: int}>, packing: array<int, int>}
     */
    public function draft(): array
    {
        $draft = Session::get(self::SESSION_KEY, []);

        return [
            'customer_name' => (string) ($draft['customer_name'] ?? ''),
            'mobile' => (string) ($draft['mobile'] ?? ''),
            'started' => (bool) ($draft['started'] ?? false),
            'items' => is_array($draft['items'] ?? null) ? $draft['items'] : [],
            'packing' => is_array($draft['packing'] ?? null) ? $draft['packing'] : [],
        ];
    }

    public function hasDraft(): bool
    {
        return $this->draft()['started'];
    }

    public function start(string $customerName, string $mobile): void
    {
        Session::put(self::SESSION_KEY, [
            'customer_name' => trim($customerName),
            'mobile' => trim($mobile),
            'started' => true,
            'items' => [],
            'packing' => [],
        ]);
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function menuItems(): Collection
    {
        return MenuItem::query()
            ->with('category')
            ->where('is_available', true)
            ->whereHas('category.menuType', fn ($query) => $query->where('slug', MenuType::SLUG_FOOD))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    public function categories(): Collection
    {
        return MenuCategory::query()
            ->whereHas('menuType', fn ($query) => $query->where('slug', MenuType::SLUG_FOOD))
            ->orderBy('name')
            ->get();
    }

    public function addItem(int $menuItemId): void
    {
        $this->requireDraft();
        $this->availableItem($menuItemId);

        $draft = $this->draft();
        if (isset($draft['items'][$menuItemId])) {
            return;
        }

        $draft['items'][$menuItemId] = [
            'quantity' => 1,
            'packing_fee' => max(0, (int) ($draft['packing'][$menuItemId] ?? 0)),
        ];

        $this->store($draft);
    }

    public function changeQuantity(int $menuItemId, int $delta): void
    {
        $this->requireDraft();
        $draft = $this->draft();

        if (! isset($draft['items'][$menuItemId])) {
            throw new BusinessConflictException('That item is not in the order.');
        }

        $quantity = (int) $draft['items'][$menuItemId]['quantity'] + $delta;
        if ($quantity < 1) {
            $quantity = 1;
        }

        $draft['items'][$menuItemId]['quantity'] = $quantity;
        $this->store($draft);
    }

    public function changePacking(int $menuItemId, int $direction): void
    {
        $this->requireDraft();
        $draft = $this->draft();

        if (! isset($draft['items'][$menuItemId])) {
            throw new BusinessConflictException('That item is not in the order.');
        }

        $packing = (int) $draft['items'][$menuItemId]['packing_fee'] + ($direction * self::PACKING_STEP);
        $packing = max(0, $packing);
        $draft['items'][$menuItemId]['packing_fee'] = $packing;
        $draft['packing'][$menuItemId] = $packing;
        $this->store($draft);
    }

    /**
     * Legacy menu checkbox sets a flat ₹10 packing charge. It does not multiply by quantity.
     */
    public function setPacking(int $menuItemId, bool $enabled): void
    {
        $this->requireDraft();
        $this->availableItem($menuItemId);

        $draft = $this->draft();
        $packing = $enabled ? self::PACKING_STEP : 0;
        $draft['packing'][$menuItemId] = $packing;

        if (isset($draft['items'][$menuItemId])) {
            $draft['items'][$menuItemId]['packing_fee'] = $packing;
        }

        $this->store($draft);
    }

    public function removeItem(int $menuItemId): void
    {
        $this->requireDraft();
        $draft = $this->draft();
        unset($draft['items'][$menuItemId], $draft['packing'][$menuItemId]);
        $this->store($draft);
    }

    /**
     * @return list<array{
     *     menu_item: MenuItem,
     *     quantity: int,
     *     packing_fee: int,
     *     unit_price: float,
     *     amount: float
     * }>
     */
    public function lines(): array
    {
        $draft = $this->draft();
        $ids = array_map('intval', array_keys($draft['items']));
        if ($ids === []) {
            return [];
        }

        $items = MenuItem::query()->whereIn('id', $ids)->get()->keyBy('id');
        $lines = [];

        foreach ($draft['items'] as $id => $row) {
            $menuItem = $items->get((int) $id);
            if (! $menuItem) {
                continue;
            }

            $quantity = max(1, (int) $row['quantity']);
            $packing = max(0, (int) ($row['packing_fee'] ?? 0));
            $unitPrice = (float) $menuItem->price;

            $lines[] = [
                'menu_item' => $menuItem,
                'quantity' => $quantity,
                'packing_fee' => $packing,
                'unit_price' => $unitPrice,
                'amount' => $this->calculator->lineAmount($unitPrice, $quantity, $packing),
            ];
        }

        return $lines;
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(fn (array $line): int => $line['quantity'], $this->lines()));
    }

    public function totalAmount(): float
    {
        return array_sum(array_map(fn (array $line): float => $line['amount'], $this->lines()));
    }

    /**
     * Legacy Place Order for counter takeaway is checkout: create order + open unpaid bill.
     */
    public function place(User $counterUser): Order
    {
        $this->requireDraft();
        $draft = $this->draft();
        $lines = $this->lines();

        if ($lines === []) {
            throw new BusinessConflictException('Add at least one menu item before placing the order.');
        }

        $dineType = DineType::query()->where('slug', DineType::SLUG_TAKE_AWAY)->where('is_active', true)->first();
        if (! $dineType) {
            throw new BusinessConflictException('Take Away is not available.');
        }

        $customer = $this->customerForDraft($draft);

        $order = DB::transaction(function () use ($customer, $dineType, $lines, $counterUser, $draft): Order {
            $order = $this->orders->place([
                'customer_id' => $customer?->id,
                'historical_customer_name' => $draft['customer_name'] !== '' ? $draft['customer_name'] : null,
                'dine_type_id' => $dineType->id,
                'pax' => 1,
                'items' => array_map(fn (array $line): array => [
                    'menu_item_id' => $line['menu_item']->id,
                    'quantity' => $line['quantity'],
                    'packing_fee' => $line['packing_fee'],
                ], $lines),
            ], $counterUser);

            // Counter takeaway has no kitchen gate before billing (legacy creates the bill immediately).
            $order->items()
                ->where('status', '!=', OrderItemStatus::Cancelled->value)
                ->update(['status' => OrderItemStatus::Served->value]);

            $order = $order->fresh(['items.menuItem', 'table', 'customer']);
            $this->billing->checkout($order);

            return $order->fresh(['items', 'bills', 'customer']);
        });

        $this->forget();

        return $order;
    }

    /**
     * A phone matches one customer. A name alone is stored on the order and does not create a customer.
     *
     * @param  array{customer_name: string, mobile: string, started: bool, items: array<int, array{quantity: int, packing_fee: int}>, packing: array<int, int>}  $draft
     */
    private function customerForDraft(array $draft): ?Customer
    {
        if ($draft['mobile'] === '') {
            return null;
        }

        $existing = $this->customers->findByPhone($draft['mobile']);
        if ($existing) {
            if (! $existing->is_active) {
                throw new BusinessConflictException('This customer is inactive and cannot be used for a new order.');
            }

            return $existing;
        }

        return $this->customers->register([
            'name' => $draft['customer_name'] !== '' ? $draft['customer_name'] : 'Guest',
            'phone' => $draft['mobile'],
        ]);
    }

    /**
     * @param  array{customer_name: string, mobile: string, started: bool, items: array<int, array{quantity: int, packing_fee: int}>, packing: array<int, int>}  $draft
     */
    private function store(array $draft): void
    {
        Session::put(self::SESSION_KEY, $draft);
    }

    private function requireDraft(): void
    {
        if (! $this->hasDraft()) {
            throw new BusinessConflictException('Enter the customer details before adding items.');
        }
    }

    private function availableItem(int $menuItemId): MenuItem
    {
        $item = MenuItem::query()
            ->whereKey($menuItemId)
            ->where('is_available', true)
            ->whereHas('category.menuType', fn ($query) => $query->where('slug', MenuType::SLUG_FOOD))
            ->first();

        if (! $item) {
            throw new BusinessConflictException('That menu item is not available.');
        }

        return $item;
    }
}
