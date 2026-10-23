<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    protected $fillable = [
        'bill_number',
        'order_id',
        'subtotal',
        'discount_id',
        'other_discount_percentage',
        'other_discount_comment',
        'discount_amount',
        'taxable_amount',
        'tax_amount',
        'additional_charges',
        'round_off',
        'grand_total',
        'status',
        'issued_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'other_discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'round_off' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'status' => BillStatus::class,
            'issued_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function isPayable(): bool
    {
        return $this->status === BillStatus::Open;
    }

    public function isCancelled(): bool
    {
        return $this->status === BillStatus::Cancelled;
    }
}
