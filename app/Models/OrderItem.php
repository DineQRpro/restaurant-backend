<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected static function booted(): void
    {
        static::creating(function (OrderItem $item): void {
            if ($item->origin_order_id === null && $item->order_id !== null) {
                $item->origin_order_id = $item->order_id;
            }
        });
    }

    protected $fillable = [
        'order_id',
        'origin_order_id',
        'menu_item_id',
        'quantity',
        'unit_price',
        'packing_fee',
        'special_instructions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'packing_fee' => 'decimal:2',
            'status' => OrderItemStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function originOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'origin_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
