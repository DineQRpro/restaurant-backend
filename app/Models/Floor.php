<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
        'layout_version',
        'layout_columns',
        'layout_rows',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'layout_version' => 'integer',
            'layout_columns' => 'integer',
            'layout_rows' => 'integer',
        ];
    }

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }
}
