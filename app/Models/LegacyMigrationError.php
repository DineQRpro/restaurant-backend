<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyMigrationError extends Model
{
    protected $fillable = [
        'run_id',
        'source_table',
        'source_id',
        'message',
        'context',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LegacyMigrationRun::class, 'run_id');
    }
}
