<?php

namespace App\Models;

use Database\Factories\DineTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DineType extends Model
{
    /** @use HasFactory<DineTypeFactory> */
    use HasFactory;

    public const SLUG_TAKE_AWAY = 'take-away';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function requiresTable(): bool
    {
        return $this->slug !== self::SLUG_TAKE_AWAY;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
