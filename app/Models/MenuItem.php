<?php

namespace App\Models;

use App\Enums\DietaryType;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'dietary_type',
        'price',
        'is_available',
        'is_chef_special',
        'sort_order',
        'spicy_level',
    ];

    protected function casts(): array
    {
        return [
            'dietary_type' => DietaryType::class,
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_chef_special' => 'boolean',
            'sort_order' => 'integer',
            'spicy_level' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function menuType(): HasOneThrough
    {
        return $this->hasOneThrough(
            MenuType::class,
            MenuCategory::class,
            'id',
            'id',
            'category_id',
            'menu_type_id',
        );
    }
}
