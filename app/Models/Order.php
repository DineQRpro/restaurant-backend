<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'historical_customer_name',
        'dine_type_id',
        'table_id',
        'waiter_id',
        'pax',
        'allocated_seats',
        'partition_label',
        'merged_tables',
        'joined_into_order_id',
        'joined_at',
        'status',
        'started_at',
        'completed_at',
        'cancelled_at',
        'seating_cleaned_at',
        'cleaning_started_at',
    ];

    protected function casts(): array
    {
        return [
            'pax' => 'integer',
            'allocated_seats' => 'integer',
            'merged_tables' => 'array',
            'joined_at' => 'datetime',
            'status' => OrderStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'seating_cleaned_at' => 'datetime',
            'cleaning_started_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function displayCustomerName(): ?string
    {
        $historical = trim((string) $this->historical_customer_name);

        if ($historical !== '') {
            return $historical;
        }

        $name = trim((string) ($this->customer?->name ?? ''));

        return $name === '' ? null : $name;
    }

    public function dineType(): BelongsTo
    {
        return $this->belongsTo(DineType::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function latestBill(): HasOne
    {
        return $this->hasOne(Bill::class)->latestOfMany();
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(OrderTransfer::class, 'source_order_id');
    }

    public function joinedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'joined_into_order_id');
    }

    public function joinedSources(): HasMany
    {
        return $this->hasMany(self::class, 'joined_into_order_id');
    }

    public function isJoined(): bool
    {
        return $this->joined_into_order_id !== null;
    }

    public function isActive(): bool
    {
        return $this->status === OrderStatus::Placed
            && $this->completed_at === null
            && $this->cancelled_at === null
            && ! $this->isJoined();
    }

    public function itemsTotal(): float
    {
        return (float) $this->items->sum(
            fn (OrderItem $item): float => ((float) $item->unit_price * $item->quantity) + (float) $item->packing_fee
        );
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Placed)
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->whereNull('joined_into_order_id');
    }

    /**
     * Table allocations that still consume physical capacity.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeUnreleased(Builder $query): Builder
    {
        $cutoff = now()->subSeconds(max(1, (int) config('restaurant.cleaning_duration_seconds', 300)));

        return $query
            ->whereNotNull('table_id')
            ->whereNull('cancelled_at')
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where(function (Builder $query): void {
                    $query->where('status', OrderStatus::Placed)
                        ->whereNull('completed_at');
                })->orWhere(function (Builder $query) use ($cutoff): void {
                    $query->where('status', OrderStatus::Checkout)
                        ->whereNull('seating_cleaned_at')
                        ->where(function (Builder $query) use ($cutoff): void {
                            $query->where('cleaning_started_at', '>', $cutoff)
                                ->orWhere(function (Builder $query) use ($cutoff): void {
                                    $query->whereNull('cleaning_started_at')
                                        ->where('updated_at', '>', $cutoff);
                                });
                        });
                });
            });
    }

    public function displayTableLabel(): ?string
    {
        $relatedTable = $this->getRelationValue('table');
        $number = $relatedTable?->number;
        if ($number === null || $number === '') {
            return null;
        }

        if (is_string($this->partition_label) && $this->partition_label !== '') {
            return $number.'-'.$this->partition_label;
        }

        return $number;
    }

    public function tableDisplayName(): string
    {
        $snapshots = $this->mergedTableSnapshots();
        $relatedTable = $this->getRelationValue('table');
        $number = $relatedTable?->number;

        if ($snapshots !== [] && is_string($number) && $number !== '') {
            $parts = array_merge([$number], array_column($snapshots, 'number'));

            return implode(' + ', $parts);
        }

        $label = $this->displayTableLabel();
        if ($label !== null) {
            return $label;
        }

        if ($this->dineType && ! $this->dineType->requiresTable()) {
            return 'Take Away';
        }

        if (is_string($number) && $number !== '') {
            return $number;
        }

        return $this->dineType?->name ?: 'Take Away';
    }

    /**
     * Historical satellite snapshots: [{id, number}, ...]. Does not include the main table.
     *
     * @return list<array{id: int, number: string}>
     */
    public function mergedTableSnapshots(): array
    {
        $raw = $this->merged_tables;
        if (! is_array($raw)) {
            return [];
        }

        $snapshots = [];
        foreach ($raw as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['number'])) {
                continue;
            }

            $number = trim((string) $row['number']);
            if ($number === '') {
                continue;
            }

            $snapshots[] = [
                'id' => (int) $row['id'],
                'number' => $number,
            ];
        }

        return $snapshots;
    }

    /**
     * @return list<int>
     */
    public function mergedTableIds(): array
    {
        return array_values(array_map(
            fn (array $snapshot): int => $snapshot['id'],
            $this->mergedTableSnapshots(),
        ));
    }

    public function hasMergedTables(): bool
    {
        return $this->mergedTableSnapshots() !== [];
    }

    /**
     * Minutes a fully paid order stays on the Track Orders board after payment.
     */
    public const PAID_VISIBILITY_MINUTES = 5;

    /**
     * Orders shown on the Counter and Admin Track Orders boards.
     *
     * The order date is COALESCE(started_at, created_at), the same timestamp
     * reports use for the order. It must fall on today's calendar date in
     * config('app.timezone'). Payment date is not the order date.
     *
     * An order stays visible when it is unpaid, or when its bill is settled
     * and bills.settled_at is still inside the last five minutes. At exactly
     * five minutes it leaves the board. Cancelled orders, credit-only bills,
     * non-chargeable-only bills, and historical imported bills are excluded.
     * Nothing is deleted.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeOperational(Builder $query): Builder
    {
        $dayStart = self::businessDayStart();
        $dayEnd = $dayStart->copy()->addDay();
        $paidSince = now()->subMinutes(self::PAID_VISIBILITY_MINUTES);

        return $query
            ->whereNull('cancelled_at')
            ->whereNull('joined_into_order_id')
            ->whereRaw('COALESCE(started_at, created_at) >= ? AND COALESCE(started_at, created_at) < ?', [
                $dayStart->format('Y-m-d H:i:s'),
                $dayEnd->format('Y-m-d H:i:s'),
            ])
            ->where(function (Builder $query) use ($paidSince): void {
                $query->where(function (Builder $query) use ($paidSince): void {
                    $query->where('status', OrderStatus::Placed)
                        ->where(function (Builder $query) use ($paidSince): void {
                            $query->whereDoesntHave('bills')
                                ->orWhereHas('bills', function (Builder $bills) use ($paidSince): void {
                                    self::constrainActiveBill($bills, $paidSince);
                                });
                        });
                })->orWhere(function (Builder $query) use ($paidSince): void {
                    $query->where('status', OrderStatus::Checkout)
                        ->where(function (Builder $query) use ($paidSince): void {
                            $query->whereDoesntHave('bills')
                                ->orWhereHas('bills', function (Builder $bills) use ($paidSince): void {
                                    self::constrainActiveBill($bills, $paidSince);
                                });
                        });
                });
            });
    }

    /**
     * Midnight of the current business date, expressed in the timezone used
     * to store order timestamps.
     */
    private static function businessDayStart(): \Illuminate\Support\Carbon
    {
        $businessTimezone = (string) config('app.timezone');

        return now()
            ->timezone($businessTimezone)
            ->startOfDay()
            ->timezone(date_default_timezone_get());
    }

    /**
     * A live bill is active when it is open and unpaid, or settled inside the
     * five-minute window, and it is not a credit-only or non-chargeable-only bill.
     *
     * @param  Builder<\App\Models\Bill>  $bills
     */
    private static function constrainActiveBill(Builder $bills, \Illuminate\Support\Carbon $paidSince): void
    {
        $bills->whereNull('historical_pay_method')
            ->where(function (Builder $bills): void {
                $bills->whereDoesntHave('payments')
                    ->orWhereHas('payments.paymentMethod', function (Builder $method): void {
                        $method->whereNotIn('slug', [
                            PaymentMethod::SLUG_CREDIT,
                            PaymentMethod::SLUG_NON_CHARGEABLE,
                        ]);
                    });
            })
            ->where(function (Builder $bills) use ($paidSince): void {
                $bills->where('status', BillStatus::Open)
                    ->orWhere(function (Builder $bills) use ($paidSince): void {
                        $bills->where('status', BillStatus::Settled)
                            ->where('settled_at', '>', $paidSince);
                    });
            });
    }
}
