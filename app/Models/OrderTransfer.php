<?php

namespace App\Models;

use App\Enums\TransferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTransfer extends Model
{
    protected $fillable = [
        'source_order_id',
        'target_order_id',
        'from_table_id',
        'to_table_id',
        'type',
        'source_pax',
        'source_allocated_seats',
        'performed_by',
        'transferred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransferType::class,
            'source_pax' => 'integer',
            'source_allocated_seats' => 'integer',
            'transferred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    public function targetOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'target_order_id');
    }

    public function fromTable(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'from_table_id');
    }

    public function toTable(): BelongsTo
    {
        return $this->belongsTo(Table::class, 'to_table_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
