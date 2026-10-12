<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptItem extends Model
{
    protected $fillable = [
        'receipt_id',
        'item_name',
        'quantity',
        'rate_price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function formattedQuantity(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2, '.', ''), '0'), '.');
    }
}
