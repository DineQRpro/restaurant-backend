<?php

namespace App\Models;

use App\Enums\CleaningHoldReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableCleaningHold extends Model
{
    protected $fillable = [
        'table_id',
        'source_order_id',
        'reason',
        'seats',
        'started_at',
        'expires_at',
        'cleaned_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => CleaningHoldReason::class,
            'seats' => 'integer',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'cleaned_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function sourceOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'source_order_id');
    }

    /**
     * @param  Builder<TableCleaningHold>  $query
     * @return Builder<TableCleaningHold>
     */
    public function scopeUnreleased(Builder $query): Builder
    {
        return $query->whereNull('cleaned_at');
    }
}
