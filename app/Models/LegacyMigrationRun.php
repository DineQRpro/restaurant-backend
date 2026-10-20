<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyMigrationRun extends Model
{
    protected $fillable = [
        'group',
        'mode',
        'status',
        'imported_count',
        'skipped_count',
        'failed_count',
        'pending_count',
        'summary',
        'approved_at',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'pending_count' => 'integer',
            'summary' => 'array',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function errors(): HasMany
    {
        return $this->hasMany(LegacyMigrationError::class, 'run_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(LegacyMigrationCheckpoint::class, 'run_id');
    }

    public function idMaps(): HasMany
    {
        return $this->hasMany(LegacyMigrationIdMap::class, 'run_id');
    }
}
