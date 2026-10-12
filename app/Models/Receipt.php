<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    public const GST_RATE = 0.05;

    public const MAX_ITEMS = 10;

    protected $fillable = [
        'receipt_date',
        'customer_name',
        'mobile',
        'program',
        'other_instructions',
        'pax_count',
        'advance_paid',
        'balance',
        'include_gst',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'pax_count' => 'integer',
            'advance_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'include_gst' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class)->orderBy('id');
    }

    public function receiptNumber(): string
    {
        return (string) $this->id;
    }

    public function itemsTotal(): float
    {
        return round((float) $this->items->sum('amount'), 2);
    }

    public function gstAmount(): float
    {
        if (! $this->include_gst) {
            return 0.0;
        }

        return round($this->itemsTotal() * self::GST_RATE, 2);
    }

    public function grandTotal(): float
    {
        return round($this->itemsTotal() + $this->gstAmount(), 2);
    }
}
