<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyMigrationMapping extends Model
{
    protected $fillable = [
        'source_table',
        'destination_table',
        'decision_key',
        'state',
        'notes',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }
}
