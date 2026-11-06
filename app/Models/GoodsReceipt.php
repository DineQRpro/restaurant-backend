<?php

namespace App\Models;

use App\Enums\GoodsReceiptPaymentStatus;
use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'vendor_id',
        'vendor_code_snapshot',
        'vendor_name_snapshot',
        'vendor_bill_number',
        'vendor_bill_key',
        'vendor_bill_date',
        'receipt_date',
        'payment_status',
        'status',
        'subtotal',
        'gst_total',
        'grand_total',
        'paid_amount',
        'outstanding_amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'vendor_bill_date' => 'date',
            'receipt_date' => 'date',
            'payment_status' => GoodsReceiptPaymentStatus::class,
            'status' => GoodsReceiptStatus::class,
            'subtotal' => 'decimal:2',
            'gst_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class);
    }
}
