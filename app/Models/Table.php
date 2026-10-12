<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\TableOperationalStatus;
use Database\Factories\TableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Table extends Model
{
    /** @use HasFactory<TableFactory> */
    use HasFactory;

    protected $fillable = [
        'floor_id',
        'number',
        'description',
        'capacity',
        'status',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'floor_id' => 'integer',
            'capacity' => 'integer',
            'status' => TableOperationalStatus::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function currentOrder(): HasOne
    {
        return $this->hasOne(Order::class)->ofMany(
            ['id' => 'max'],
            function ($query): void {
                $query->where('status', OrderStatus::Placed)
                    ->whereNull('completed_at')
                    ->whereNull('cancelled_at');
            },
        );
    }

    public function unreleasedOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->unreleased()
            ->orderBy('id');
    }

    public function cleaningHolds(): HasMany
    {
        return $this->hasMany(TableCleaningHold::class)
            ->unreleased()
            ->orderBy('id');
    }

    /**
     * @param  Builder<Table>  $query
     * @return Builder<Table>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
