<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyMigrationIdMap extends Model
{
    protected $fillable = [
        'run_id',
        'source_table',
        'source_id',
        'destination_table',
        'destination_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'destination_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LegacyMigrationRun::class, 'run_id');
    }
}
