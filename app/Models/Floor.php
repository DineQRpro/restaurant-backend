<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }
}
