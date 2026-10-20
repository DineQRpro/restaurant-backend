<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuType extends Model
{
    public const SLUG_FOOD = 'food';

    public const SLUG_HOT_BEVERAGES = 'hot-beverages';

    protected $fillable = [
        'name',
        'slug',
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

    public function categories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public static function ensureDefaults(): void
    {
        static::query()->updateOrCreate(
            ['slug' => self::SLUG_FOOD],
            ['name' => 'Food Menu', 'sort_order' => 1, 'is_active' => true],
        );

        static::query()->updateOrCreate(
            ['slug' => self::SLUG_HOT_BEVERAGES],
            ['name' => 'Hot Beverages Menu', 'sort_order' => 2, 'is_active' => true],
        );
    }
}
