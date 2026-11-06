<?php

namespace App\Models;

use App\Enums\UnitDimension;
use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends Model
{
    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'name',
        'symbol',
        'dimension',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'dimension' => UnitDimension::class,
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
