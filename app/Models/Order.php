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
        'dine_type_id',
        'table_id',
        'waiter_id',
        'pax',
        'status',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'pax' => 'integer',
            'status' => OrderStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function isActive(): bool
    {
        return $this->status === OrderStatus::Placed
            && $this->completed_at === null
            && $this->cancelled_at === null;
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
            ->whereNull('cancelled_at');
    }

    /**
     * Current operational orders for the Admin Track Orders board.
     *
     * Includes placed orders and checkout orders that still have an open bill
     * or a bill settled today. Historical checkout orders settled before today
     * are excluded.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeOperational(Builder $query): Builder
    {
        $todayStart = now()->startOfDay();

        return $query
            ->whereNull('cancelled_at')
            ->where(function (Builder $query) use ($todayStart): void {
                $query->where('status', OrderStatus::Placed)
                    ->orWhere(function (Builder $query) use ($todayStart): void {
                        $query->where('status', OrderStatus::Checkout)
                            ->where(function (Builder $query) use ($todayStart): void {
                                $query->whereDoesntHave('bills')
                                    ->orWhereHas('bills', function (Builder $bills) use ($todayStart): void {
                                        $bills->where('status', BillStatus::Open)
                                            ->orWhere(function (Builder $bills) use ($todayStart): void {
                                                $bills->where('status', BillStatus::Settled)
                                                    ->where('settled_at', '>=', $todayStart);
                                            });
                                    });
                            });
                    });
            });
    }
}
