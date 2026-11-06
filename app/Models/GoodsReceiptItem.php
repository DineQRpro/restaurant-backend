<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'inventory_item_id',
        'item_code_snapshot',
        'item_name_snapshot',
        'category_name_snapshot',
        'unit_name_snapshot',
        'unit_symbol_snapshot',
        'quantity',
        'unit_price',
        'gst_rate',
        'taxable_amount',
        'gst_amount',
        'line_total',
        'batch_number',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class);
    }
}
