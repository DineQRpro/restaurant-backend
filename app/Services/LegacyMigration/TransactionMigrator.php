<?php

namespace App\Services\LegacyMigration;

use App\Enums\BillStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Models\Customer;
use App\Models\DineType;
use App\Models\DocumentSequence;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Imports approved historical customers, orders, bills, payments, and banquet receipts
 * into the same migration run as the Phase 1 masters.
 */
class TransactionMigrator
{
    /** @var list<string> */
    private const SOURCE_TABLES = [
        'tbl_order',
        'tbl_order_items',
        'tbl_bill',
        'tbl_bill_payments',
        'tbl_receipts',
        'tbl_receipt_items',
    ];

    /** @var array<int, string> */
    private const PAYMENT_SLUGS = [
        1 => PaymentMethod::SLUG_CASH,
        2 => PaymentMethod::SLUG_UPI,
        4 => PaymentMethod::SLUG_NON_CHARGEABLE,
        5 => PaymentMethod::SLUG_CARD,
        6 => PaymentMethod::SLUG_CREDIT,
    ];

    /** @var array<int, string> */
    private const KITCHEN_STATUS = [
        0 => OrderItemStatus::Pending->value,
        1 => OrderItemStatus::Pending->value,
        3 => OrderItemStatus::Cancelled->value,
        4 => OrderItemStatus::Preparing->value,
        6 => OrderItemStatus::Served->value,
    ];

    public function __construct(private readonly LegacySource $source) {}

    public function migrate(MigrationContext $context): void
    {
        foreach (self::SOURCE_TABLES as $table) {
            if (! Schema::connection('legacy')->hasTable($table)) {
                $context->reject('failed', $table, null, 'Source table '.$table.' is missing.', [
                    'migrator' => 'transactions',
                ]);

                return;
            }
        }

        $orders = $this->rows('tbl_order', 'order_id');
        $occupations = $this->occupations();
        $bills = $this->rows('tbl_bill', 'bill_id');
        $payments = $this->rows('tbl_bill_payments', 'payment_id');
        $items = $this->rows('tbl_order_items', 'order_items_id');
        $products = $this->source->connection()->table('tbl_products')->get()->keyBy('product_id');

        $seedBillIds = [];
        $seedOrderIds = [];

        foreach ($bills as $bill) {
            if ($this->isSeed($bill)) {
                $seedBillIds[(int) $bill->bill_id] = true;
                $orderId = (int) $bill->bill_order_id;

                if ($orderId > 0) {
                    $seedOrderIds[$orderId] = true;
                }
            }
        }

        $cancelOrderIds = [];

        foreach ($bills as $bill) {
            if (isset($seedBillIds[(int) $bill->bill_id])) {
                continue;
            }

            if ((int) ($bill->bill_pay_method ?? 0) === 7) {
                $cancelOrderIds[(int) $bill->bill_order_id] = true;
            }
        }

        $orderIds = [];

        foreach ($orders as $order) {
            $orderIds[(int) $order->order_id] = true;
        }

        $this->skipUnreferencedOccupations($context, $occupations, $orders);
        $customers = $this->importCustomers($context, $orders, $occupations, $seedOrderIds);
        $importedOrders = $this->importOrders($context, $orders, $occupations, $seedOrderIds, $cancelOrderIds, $customers);
        $importedItems = $this->importItems($context, $items, $products, $orders, $orderIds, $seedOrderIds, $importedOrders);
        $importedBills = $this->importBills($context, $bills, $payments, $seedBillIds, $orderIds, $importedOrders);
        $this->importBillItems($context, $items, $importedItems, $importedBills);
        $this->importPayments($context, $payments, $bills, $seedBillIds, $importedBills);
        $this->importReceipts($context);
        $this->setSequences($context, $orders, $bills);
        $this->summarize($context, $importedBills, $bills, $payments);
    }

    /**
     * @param  array<int, object>  $occupations
     * @param  array<int, object>  $orders
     */
    private function skipUnreferencedOccupations(MigrationContext $context, array $occupations, array $orders): void
    {
        $referenced = [];

        foreach ($orders as $order) {
            $referenced[(int) $order->order_tables_id] = true;
        }

        foreach ($occupations as $id => $occupation) {
            if (isset($referenced[$id])) {
                continue;
            }

            $context->reject('skipped', 'tbl_tables', (string) $id, 'Occupation row has no order.', [
                'migrator' => 'occupations',
            ]);
        }
    }

    /**
     * @param  array<int, object>  $orders
     * @param  array<int, object>  $occupations
     * @param  array<int, true>  $seedOrderIds
     * @return array<string, int|true>
     */
    private function importCustomers(MigrationContext $context, array $orders, array $occupations, array $seedOrderIds): array
    {
        /** @var array<string, list<object>> $byPhone */
        $byPhone = [];

        foreach ($orders as $order) {
            if (isset($seedOrderIds[(int) $order->order_id])) {
                continue;
            }

            $occupation = $occupations[(int) $order->order_tables_id] ?? null;
            $phone = $this->phone($occupation->tables_mobile_number ?? null);

            if ($phone === null) {
                continue;
            }

            $byPhone[$phone][] = $order;
        }

        ksort($byPhone, SORT_STRING);
        $mapped = [];

        foreach ($byPhone as $phone => $phoneOrders) {
            usort($phoneOrders, function (object $left, object $right): int {
                $created = strcmp((string) $right->order_created_at, (string) $left->order_created_at);

                return $created !== 0 ? $created : ((int) $right->order_id <=> (int) $left->order_id);
            });

            $name = 'Guest';

            foreach ($phoneOrders as $order) {
                $occupation = $occupations[(int) $order->order_tables_id] ?? null;
                $candidate = Values::nullableText($occupation->tables_customer_name ?? null);

                if ($candidate !== null) {
                    $name = mb_substr($candidate, 0, 150);
                    break;
                }
            }

            if (! $context->persist) {
                $context->imported('legacy_customers');
                $context->memory['legacy_customers'][$phone] = true;
                $mapped[$phone] = true;

                continue;
            }

            $customer = new Customer([
                'name' => $name,
                'phone' => $phone,
                'is_active' => true,
            ]);
            $customer->save();
            $context->remember('legacy_customers', $phone, 'customers', $customer->id);
            $context->imported('legacy_customers');
            $mapped[$phone] = $customer->id;
        }

        return $mapped;
    }

    /**
     * @param  array<int, object>  $orders
     * @param  array<int, object>  $occupations
     * @param  array<int, true>  $seedOrderIds
     * @param  array<int, true>  $cancelOrderIds
     * @param  array<string, int|true>  $customers
     * @return array<int, int|true>
     */
    private function importOrders(
        MigrationContext $context,
        array $orders,
        array $occupations,
        array $seedOrderIds,
        array $cancelOrderIds,
        array $customers,
    ): array {
        $dineTypes = DineType::query()->pluck('id', 'slug');
        $imported = [];
        $placeholder = null;

        foreach ($orders as $order) {
            $sourceId = (string) $order->order_id;

            if (isset($seedOrderIds[(int) $order->order_id])) {
                $context->reject('skipped', 'tbl_order', $sourceId, 'TEST_SEED order.', [
                    'migrator' => 'orders',
                ]);

                continue;
            }

            $occupation = $occupations[(int) $order->order_tables_id] ?? null;

            if ($occupation === null) {
                $context->reject('failed', 'tbl_order', $sourceId, 'Occupation row is missing.', [
                    'migrator' => 'orders',
                ]);

                continue;
            }

            $userStatus = (int) $order->order_user_status;
            $slug = match ($userStatus) {
                2 => 'dine-in',
                3 => 'take-away',
                5 => 'candle-light-dinner',
                default => null,
            };

            if ($slug === null || ! isset($dineTypes[$slug])) {
                $context->reject('failed', 'tbl_order', $sourceId, 'Order user status is not mapped.', [
                    'migrator' => 'orders',
                ]);

                continue;
            }

            $needsTable = $slug !== 'take-away';
            $tbid = Values::nullableText($occupation->tbl_tbid ?? null);
            $tableId = null;

            if ($needsTable) {
                if ($tbid === null || ! array_key_exists($tbid, $context->physicalTableByTbid)) {
                    $context->reject('failed', 'tbl_order', $sourceId, 'Physical table was not found for the occupation.', [
                        'migrator' => 'orders',
                    ]);

                    continue;
                }

                $resolved = $context->physicalTableByTbid[$tbid];
                $tableId = is_int($resolved) ? $resolved : null;

                if ($context->persist && $tableId === null) {
                    $context->reject('failed', 'tbl_order', $sourceId, 'Physical table id is missing.', [
                        'migrator' => 'orders',
                    ]);

                    continue;
                }
            }

            $status = (int) $order->order_status;

            if (! in_array($status, [1, 2, 6, 7], true)) {
                $context->reject('failed', 'tbl_order', $sourceId, 'Order status is not mapped.', [
                    'migrator' => 'orders',
                ]);

                continue;
            }

            $waiterSource = Values::nullableText($order->order_waiter_id ?? null);
            $waiterId = null;

            if ($waiterSource !== null && $context->mapped('tbl_users', $waiterSource)) {
                $waiterId = $context->destinationId('tbl_users', $waiterSource);
            }

            if ($context->persist && $waiterId === null) {
                $placeholder ??= $this->placeholderWaiter();
                $waiterId = $placeholder;
            }

            $phone = $this->phone($occupation->tables_mobile_number ?? null);
            $customerId = $phone !== null ? ($customers[$phone] ?? null) : null;
            $customerId = is_int($customerId) ? $customerId : null;
            $historical = Values::nullableText($occupation->tables_customer_name ?? null);
            $pax = (int) ($occupation->tables_reserved_chair ?? 0);
            $pax = $pax >= 1 && $pax <= 255 ? $pax : 1;
            $created = Values::timestamp($order->order_created_at, 'Created at');
            $updated = Values::timestamp($order->order_updated_at, 'Updated at');
            $cancelled = $status !== 7 || isset($cancelOrderIds[(int) $order->order_id]);
            $metadata = [
                'order_status' => $status,
                'order_user_status' => $userStatus,
                'raw_phone' => $phone === null ? Values::nullableText($occupation->tables_mobile_number ?? null) : null,
                'waiter_source_id' => $waiterSource,
            ];

            if (! $context->persist) {
                $context->imported('tbl_order');
                $context->memory['tbl_order'][$sourceId] = true;
                $imported[(int) $order->order_id] = true;

                continue;
            }

            $record = new Order([
                'order_number' => $sourceId,
                'customer_id' => $customerId,
                'historical_customer_name' => $historical === null ? null : mb_substr($historical, 0, 150),
                'dine_type_id' => (int) $dineTypes[$slug],
                'table_id' => $tableId,
                'waiter_id' => $waiterId,
                'pax' => $pax,
                'allocated_seats' => $pax,
                'merged_tables' => $this->mergedTables($context, $order->tbls_merged ?? null),
                'status' => OrderStatus::Checkout,
                'started_at' => $created,
                'completed_at' => $status === 7 ? $updated : null,
                'cancelled_at' => $cancelled ? $updated : null,
                'seating_cleaned_at' => $needsTable ? $updated : null,
            ]);
            $record->timestamps = false;
            $record->created_at = $created;
            $record->updated_at = $updated;
            Order::withoutEvents(fn () => $record->save());
            $context->remember('tbl_order', $sourceId, 'orders', $record->id, $metadata);
            $context->imported('tbl_order');
            $imported[(int) $order->order_id] = $record->id;
        }

        return $imported;
    }

    /**
     * @param  array<int, object>  $items
     * @param  \Illuminate\Support\Collection<int|string, object>  $products
     * @param  array<int, true>  $orderIds
     * @param  array<int, true>  $seedOrderIds
     * @param  array<int, int|true>  $importedOrders
     * @return array<int, array{id: int|true, status: string, name: string, quantity: int, price: string, packing: string}>
     */
    private function importItems(
        MigrationContext $context,
        array $items,
        $products,
        array $orders,
        array $orderIds,
        array $seedOrderIds,
        array $importedOrders,
    ): array {
        $imported = [];

        foreach ($items as $item) {
            $sourceId = (string) $item->order_items_id;
            $orderId = (int) $item->order_items_main_id;

            if (! isset($orderIds[$orderId])) {
                $context->reject('skipped', 'tbl_order_items', $sourceId, 'Order item has no order.', [
                    'migrator' => 'order-items',
                ]);

                continue;
            }

            if (isset($seedOrderIds[$orderId])) {
                $context->reject('skipped', 'tbl_order_items', $sourceId, 'TEST_SEED order item.', [
                    'migrator' => 'order-items',
                ]);

                continue;
            }

            if (! isset($importedOrders[$orderId])) {
                $context->reject('skipped', 'tbl_order_items', $sourceId, 'Parent order was not imported.', [
                    'migrator' => 'order-items',
                ]);

                continue;
            }

            $productId = (string) $item->order_product_id;

            if (! $context->mapped('tbl_products', $productId)) {
                $context->reject('failed', 'tbl_order_items', $sourceId, 'Menu item was not imported.', [
                    'migrator' => 'order-items',
                ]);

                continue;
            }

            $kitchen = (int) $item->kitchen_status;

            if (! isset(self::KITCHEN_STATUS[$kitchen])) {
                $context->reject('failed', 'tbl_order_items', $sourceId, 'Kitchen status is not mapped.', [
                    'migrator' => 'order-items',
                ]);

                continue;
            }

            $quantity = (int) $item->order_items_qty;

            if ($quantity < 1) {
                $context->reject('failed', 'tbl_order_items', $sourceId, 'Quantity must be greater than zero.', [
                    'migrator' => 'order-items',
                ]);

                continue;
            }

            $status = self::KITCHEN_STATUS[$kitchen];
            $product = $products->get($item->order_product_id);
            $name = Values::text($product->product_title ?? 'Item');
            $menuId = $context->destinationId('tbl_products', $productId);

            if ($context->persist && $menuId !== null) {
                $current = MenuItem::query()->whereKey($menuId)->value('name');

                if (is_string($current) && $current !== '') {
                    $name = $current;
                }
            }

            $instruction = Values::nullableText($item->order_items_spinstruction ?? null)
                ?? Values::nullableText($orders[$orderId]->order_spinstruction ?? null);
            $price = Values::money($item->order_items_amnt ?? 0);
            $packing = Values::money($item->item_packing_charge ?? 0);

            if (! $context->persist) {
                $context->imported('tbl_order_items');
                $imported[(int) $item->order_items_id] = [
                    'id' => true,
                    'status' => $status,
                    'name' => $name,
                    'quantity' => $quantity,
                    'price' => $price,
                    'packing' => $packing,
                    'order_id' => $orderId,
                ];

                continue;
            }

            $record = new OrderItem([
                'order_id' => $importedOrders[$orderId],
                'menu_item_id' => $menuId,
                'quantity' => $quantity,
                'unit_price' => $price,
                'packing_fee' => $packing,
                'special_instructions' => $instruction,
                'status' => $status,
            ]);
            $record->timestamps = false;
            $record->created_at = Values::timestamp($item->order_items_created_at, 'Created at');
            $record->updated_at = Values::timestamp($item->order_items_updated_at, 'Updated at');
            OrderItem::withoutEvents(fn () => $record->save());
            $context->remember('tbl_order_items', $sourceId, 'order_items', $record->id, [
                'kitchen_status' => $kitchen,
            ]);
            $context->imported('tbl_order_items');
            $imported[(int) $item->order_items_id] = [
                'id' => $record->id,
                'status' => $status,
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price,
                'packing' => $packing,
                'order_id' => $orderId,
            ];
        }

        return $imported;
    }

    /**
     * @param  array<int, object>  $bills
     * @param  array<int, object>  $payments
     * @param  array<int, true>  $seedBillIds
     * @param  array<int, true>  $orderIds
     * @param  array<int, int|true>  $importedOrders
     * @return array<int, array{id: int|true, order_id: int, grand: string, method: int, status: string}>
     */
    private function importBills(
        MigrationContext $context,
        array $bills,
        array $payments,
        array $seedBillIds,
        array $orderIds,
        array $importedOrders,
    ): array {
        $paymentsByBill = [];

        foreach ($payments as $payment) {
            $paymentsByBill[(int) $payment->bill_id][] = $payment;
        }

        $imported = [];

        foreach ($bills as $bill) {
            $sourceId = (string) $bill->bill_id;
            $orderId = (int) $bill->bill_order_id;

            if (isset($seedBillIds[(int) $bill->bill_id])) {
                $context->reject('skipped', 'tbl_bill', $sourceId, 'TEST_SEED bill.', [
                    'migrator' => 'bills',
                ]);

                continue;
            }

            if (! isset($orderIds[$orderId])) {
                $context->reject('skipped', 'tbl_bill', $sourceId, 'Bill has no order.', [
                    'migrator' => 'bills',
                ]);

                continue;
            }

            if (! isset($importedOrders[$orderId])) {
                $context->reject('skipped', 'tbl_bill', $sourceId, 'Parent order was not imported.', [
                    'migrator' => 'bills',
                ]);

                continue;
            }

            $method = (int) ($bill->bill_pay_method ?? 0);
            $grand = Values::money($bill->bill_gd_total ?? 0);
            $paid = 0;
            $latestPaidAt = null;

            foreach ($paymentsByBill[(int) $bill->bill_id] ?? [] as $payment) {
                $code = (int) ($payment->payment_method ?? 0);

                if (! isset(self::PAYMENT_SLUGS[$code])) {
                    continue;
                }

                if ($payment->payment_amount === null || (float) $payment->payment_amount < 0) {
                    continue;
                }

                $paid += Values::cents($payment->payment_amount);
                $stamp = trim((string) ($payment->paid_at ?? ''));

                if ($stamp !== '' && ($latestPaidAt === null || $stamp > $latestPaidAt)) {
                    $latestPaidAt = $stamp;
                }
            }

            $cancelled = $method === 7;
            $settled = ! $cancelled && $paid === Values::cents($grand);
            $discountSource = (int) ($bill->discount_id ?? 0);
            $discountId = null;

            if ($discountSource > 0) {
                $discountId = $context->destinationId('tbl_discounts', (string) $discountSource);

                if ($context->persist && $discountId === null) {
                    $context->reject('failed', 'tbl_bill', $sourceId, 'Discount was not imported.', [
                        'migrator' => 'bills',
                    ]);

                    continue;
                }
            }

            $issued = Values::timestamp($bill->bill_created_at, 'Issued at');
            $updated = Values::timestamp($bill->bill_updated_at, 'Updated at');
            $status = $cancelled ? BillStatus::Cancelled : ($settled ? BillStatus::Settled : BillStatus::Open);

            if (! $context->persist) {
                $context->imported('tbl_bill');
                $imported[(int) $bill->bill_id] = [
                    'id' => true,
                    'order_id' => $orderId,
                    'grand' => $grand,
                    'method' => $method,
                    'status' => $status->value,
                ];

                continue;
            }

            $record = new Bill([
                'bill_number' => $sourceId,
                'order_id' => $importedOrders[$orderId],
                'subtotal' => Values::money($bill->bill_total_amount ?? 0),
                'discount_id' => $discountId,
                'other_discount_percentage' => Values::money($bill->other_discount_value ?? 0),
                'other_discount_comment' => Values::limited(Values::nullableText($bill->other_discount_comments ?? null), 'Discount comment', 255),
                'discount_amount' => Values::money($bill->discount_amount ?? 0),
                'taxable_amount' => Values::money($bill->taxable_total ?? 0),
                'tax_amount' => Values::money(((float) ($bill->bill_sgst ?? 0)) + ((float) ($bill->bill_cgst ?? 0)) + ((float) ($bill->bill_vat ?? 0))),
                'additional_charges' => Values::money($bill->additional_charges ?? 0),
                'round_off' => Values::money($bill->round_off ?? 0),
                'grand_total' => $grand,
                'status' => $status,
                'issued_at' => $issued,
                'settled_at' => $settled ? Values::timestamp($latestPaidAt ?? $bill->bill_updated_at, 'Settled at') : null,
            ]);
            $record->timestamps = false;
            $record->created_at = $issued;
            $record->updated_at = $updated;
            Bill::withoutEvents(fn () => $record->save());
            $context->remember('tbl_bill', $sourceId, 'bills', $record->id, [
                'bill_pay_method' => $method,
                'bill_gd_total' => $grand,
            ]);
            $context->imported('tbl_bill');
            $imported[(int) $bill->bill_id] = [
                'id' => $record->id,
                'order_id' => $orderId,
                'grand' => $grand,
                'method' => $method,
                'status' => $status->value,
            ];
        }

        return $imported;
    }

    /**
     * @param  array<int, object>  $items
     * @param  array<int, array{id: int|true, status: string, name: string, quantity: int, price: string, packing: string, order_id: int}>  $importedItems
     * @param  array<int, array{id: int|true, order_id: int, grand: string, method: int, status: string}>  $importedBills
     */
    private function importBillItems(MigrationContext $context, array $items, array $importedItems, array $importedBills): void
    {
        $billByOrder = [];

        foreach ($importedBills as $bill) {
            $billByOrder[$bill['order_id']] ??= $bill;
        }

        $used = [];

        foreach ($items as $item) {
            $imported = $importedItems[(int) $item->order_items_id] ?? null;

            if ($imported === null || $imported['status'] === OrderItemStatus::Cancelled->value) {
                continue;
            }

            $bill = $billByOrder[$imported['order_id']] ?? null;

            if ($bill === null || isset($used[$imported['order_id']][(int) $item->order_items_id])) {
                continue;
            }

            $used[$imported['order_id']][(int) $item->order_items_id] = true;

            if (! $context->persist || ! is_int($bill['id']) || ! is_int($imported['id'])) {
                $context->imported('bill_items');

                continue;
            }

            $line = new BillItem([
                'bill_id' => $bill['id'],
                'order_item_id' => $imported['id'],
                'item_name' => mb_substr($imported['name'], 0, 255),
                'quantity' => $imported['quantity'],
                'unit_price' => $imported['price'],
                'packing_fee' => $imported['packing'],
            ]);
            $line->save();
            $context->imported('bill_items');
        }
    }

    /**
     * @param  array<int, object>  $payments
     * @param  array<int, object>  $bills
     * @param  array<int, true>  $seedBillIds
     * @param  array<int, array{id: int|true, order_id: int, grand: string, method: int, status: string}>  $importedBills
     */
    private function importPayments(MigrationContext $context, array $payments, array $bills, array $seedBillIds, array $importedBills): void
    {
        $methodIds = PaymentMethod::query()->pluck('id', 'slug');
        $billsById = [];

        foreach ($bills as $bill) {
            $billsById[(int) $bill->bill_id] = $bill;
        }

        foreach ($payments as $payment) {
            $sourceId = (string) $payment->payment_id;
            $billId = (int) $payment->bill_id;
            $code = (int) ($payment->payment_method ?? 0);

            if (isset($seedBillIds[$billId])) {
                $context->reject('skipped', 'tbl_bill_payments', $sourceId, 'TEST_SEED payment.', [
                    'migrator' => 'payments',
                ]);

                continue;
            }

            if (! isset($importedBills[$billId])) {
                $context->reject('skipped', 'tbl_bill_payments', $sourceId, 'Payment bill was not imported.', [
                    'migrator' => 'payments',
                ]);

                continue;
            }

            if ($code === 3 || $code === 7 || (int) ($billsById[$billId]->bill_pay_method ?? 0) === 7) {
                $context->reject('skipped', 'tbl_bill_payments', $sourceId, $code === 3 ? 'Split is not a tender.' : 'Cancel is not a collected payment.', [
                    'migrator' => 'payments',
                ]);

                continue;
            }

            $slug = self::PAYMENT_SLUGS[$code] ?? null;

            if ($slug === null) {
                $context->reject('failed', 'tbl_bill_payments', $sourceId, 'Payment code is not mapped.', [
                    'migrator' => 'payments',
                ]);

                continue;
            }

            if ($payment->payment_amount === null || (float) $payment->payment_amount < 0) {
                $context->reject('failed', 'tbl_bill_payments', $sourceId, 'Payment amount is missing or negative.', [
                    'migrator' => 'payments',
                ]);

                continue;
            }

            if (! $context->persist || ! is_int($importedBills[$billId]['id'])) {
                $context->imported('tbl_bill_payments');

                continue;
            }

            $paidAt = Values::timestamp($payment->paid_at ?? $billsById[$billId]->bill_created_at, 'Paid at');
            $record = new BillPayment([
                'bill_id' => $importedBills[$billId]['id'],
                'payment_method_id' => (int) $methodIds[$slug],
                'amount' => Values::money($payment->payment_amount),
                'reference_number' => Values::limited(Values::nullableText($payment->transaction_id ?? null), 'Reference', 150),
                'notes' => Values::nullableText($payment->payment_gateway ?? null),
                'paid_at' => $paidAt,
                'captain_id' => null,
            ]);
            $record->timestamps = false;
            $record->created_at = $paidAt;
            $record->updated_at = $paidAt;
            $record->save();
            $context->remember('tbl_bill_payments', $sourceId, 'bill_payments', $record->id, [
                'payment_method' => $code,
            ]);
            $context->imported('tbl_bill_payments');
        }
    }

    private function importReceipts(MigrationContext $context): void
    {
        $receipts = $this->rows('tbl_receipts', 'receipt_id');
        $items = $this->source->connection()->table('tbl_receipt_items')->orderBy('receipt_item_id')->get();

        foreach ($receipts as $receipt) {
            $sourceId = (string) $receipt->receipt_id;
            $name = Values::nullableText($receipt->customer_name ?? null);
            $program = Values::nullableText($receipt->program ?? null);

            if ($name === null || $program === null) {
                $context->reject('failed', 'tbl_receipts', $sourceId, 'Receipt name and program are required.', [
                    'migrator' => 'receipts',
                ]);

                continue;
            }

            if (! $context->persist) {
                $context->memory['tbl_receipts'][$sourceId] = true;
                $context->imported('tbl_receipts');

                continue;
            }

            $record = new Receipt([
                'receipt_date' => (string) $receipt->receipt_date,
                'customer_name' => mb_substr($name, 0, 150),
                'mobile' => mb_substr(Values::text($receipt->mobile ?? ''), 0, 20),
                'program' => $program,
                'other_instructions' => Values::nullableText($receipt->other_instructions ?? null),
                'pax_count' => (int) ($receipt->pax_count ?? 0),
                'advance_paid' => Values::money($receipt->advance_paid ?? 0),
                'balance' => Values::money($receipt->balance ?? 0),
                'include_gst' => (int) ($receipt->include_gst ?? 0) === 1,
            ]);
            $record->id = (int) $receipt->receipt_id;
            $record->timestamps = false;
            $record->created_at = Values::timestamp($receipt->created_at, 'Created at');
            $record->updated_at = Values::timestamp($receipt->updated_at ?? $receipt->created_at, 'Updated at');
            $record->save();
            $context->remember('tbl_receipts', $sourceId, 'receipts', $record->id);
            $context->imported('tbl_receipts');
        }

        foreach ($items as $item) {
            $receiptId = (int) $item->receipt_id;

            if (! $context->mapped('tbl_receipts', (string) $receiptId)) {
                $context->reject('skipped', 'tbl_receipt_items', (string) $item->receipt_item_id, 'Receipt was not imported.', [
                    'migrator' => 'receipt-items',
                ]);

                continue;
            }

            if (! $context->persist) {
                $context->imported('tbl_receipt_items');

                continue;
            }

            $line = new ReceiptItem([
                'receipt_id' => $receiptId,
                'item_name' => mb_substr(Values::text($item->item_name), 0, 255),
                'quantity' => Values::money($item->quantity ?? 0),
                'rate_price' => Values::money($item->rate_price ?? 0),
                'amount' => Values::money($item->amount ?? 0),
            ]);
            $line->save();
            $context->imported('tbl_receipt_items');
        }
    }

    /**
     * @param  array<int, object>  $orders
     * @param  array<int, object>  $bills
     */
    private function setSequences(MigrationContext $context, array $orders, array $bills): void
    {
        $maxOrder = 0;
        $maxBill = 0;

        foreach ($orders as $order) {
            $maxOrder = max($maxOrder, (int) $order->order_id);
        }

        foreach ($bills as $bill) {
            $maxBill = max($maxBill, (int) $bill->bill_id);
        }

        $context->transactionSummary['next_order_number'] = $maxOrder + 1;
        $context->transactionSummary['next_bill_number'] = $maxBill + 1;

        if (! $context->persist || $maxOrder === 0) {
            return;
        }

        DocumentSequence::query()->updateOrCreate(
            ['name' => DocumentSequence::ORDER],
            ['next_number' => $maxOrder + 1],
        );
        DocumentSequence::query()->updateOrCreate(
            ['name' => DocumentSequence::BILL],
            ['next_number' => $maxBill + 1],
        );
    }

    /**
     * @param  array<int, array{id: int|true, order_id: int, grand: string, method: int, status: string}>  $bills
     */
    /**
     * @param  array<int, array{id: int|true, order_id: int, grand: string, method: int, status: string}>  $importedBills
     * @param  array<int, object>  $sourceBills
     * @param  array<int, object>  $sourcePayments
     */
    private function summarize(MigrationContext $context, array $importedBills, array $sourceBills, array $sourcePayments): void
    {
        $preserved = 0;
        $net = 0;

        foreach ($importedBills as $bill) {
            $cents = Values::cents($bill['grand']);
            $preserved += $cents;

            if (! in_array($bill['method'], [4, 7], true)) {
                $net += $cents;
            }
        }

        $paidByBill = [];

        foreach ($sourcePayments as $payment) {
            $billId = (int) $payment->bill_id;
            $paidByBill[$billId] = ($paidByBill[$billId] ?? 0) + Values::cents($payment->payment_amount ?? 0);
        }

        $gaps = 0;

        foreach ($sourceBills as $bill) {
            if (($bill->payment_gateway ?? null) === 'TEST_SEED' || ($bill->gateway_name ?? null) === 'TEST_SEED') {
                continue;
            }

            if (abs(Values::cents($bill->bill_gd_total ?? 0) - ($paidByBill[(int) $bill->bill_id] ?? 0)) > 100) {
                $gaps++;
            }
        }

        if ($context->persist) {
            $creditPayments = BillPayment::query()
                ->join('payment_methods', 'payment_methods.id', '=', 'bill_payments.payment_method_id')
                ->where('payment_methods.slug', PaymentMethod::SLUG_CREDIT)
                ->get(['bill_payments.amount']);
            $creditCents = 0;

            foreach ($creditPayments as $payment) {
                $creditCents += Values::cents($payment->amount);
            }

            $context->transactionSummary['credit_rows'] = $creditPayments->count();
            $context->transactionSummary['credit_total'] = Values::money($creditCents / 100);
        }

        $context->transactionSummary['preserved_bill_total'] = Values::money($preserved / 100);
        $context->transactionSummary['legacy_net_sales'] = Values::money($net / 100);
        $context->transactionSummary['payment_gaps_over_one_rupee'] = $gaps;
    }

    private function placeholderWaiter(): int
    {
        $existing = User::query()->where('username', 'legacy-unassigned')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $user = new User([
            'name' => 'Legacy unassigned',
            'username' => 'legacy-unassigned',
            'email' => null,
            'mobile' => null,
            'password' => Str::random(40),
            'role_id' => (int) Role::query()->where('slug', 'waiter')->value('id'),
            'is_active' => false,
            'must_change_password' => false,
        ]);
        $user->save();

        return $user->id;
    }

    private function mergedTables(MigrationContext $context, mixed $raw): ?array
    {
        $text = Values::nullableText($raw);

        if ($text === null) {
            return null;
        }

        $snapshots = [];

        foreach (preg_split('/\s*,\s*/', $text) ?: [] as $token) {
            if ($token === '' || ! array_key_exists($token, $context->physicalTableByTbid)) {
                continue;
            }

            $id = $context->physicalTableByTbid[$token];

            if (! is_int($id)) {
                continue;
            }

            $snapshots[] = [
                'id' => $id,
                'number' => $context->physicalTableNumberByTbid[$token] ?? $token,
            ];
        }

        return $snapshots === [] ? null : $snapshots;
    }

    private function phone(mixed $value): ?string
    {
        $text = Values::text($value);

        return preg_match('/^[0-9]{10}$/', $text) === 1 ? $text : null;
    }

    private function isSeed(object $bill): bool
    {
        return Values::text($bill->payment_gateway ?? '') === 'TEST_SEED'
            || Values::text($bill->gateway_name ?? '') === 'TEST_SEED';
    }

    /**
     * @return array<int, object>
     */
    private function rows(string $table, string $key): array
    {
        $rows = [];

        foreach ($this->source->connection()->table($table)->orderBy($key)->get() as $row) {
            $rows[(int) $row->{$key}] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, object>
     */
    private function occupations(): array
    {
        $rows = [];
        $query = $this->source->connection()->table('tbl_tables')->orderBy('tables_id')->get();

        foreach ($query as $row) {
            if ($row->tables_session_id === null || trim((string) $row->tables_session_id) === '') {
                continue;
            }

            $rows[(int) $row->tables_id] = $row;
        }

        return $rows;
    }
}
