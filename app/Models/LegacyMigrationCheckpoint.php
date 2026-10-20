<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyMigrationCheckpoint extends Model
{
    protected $fillable = [
        'run_id',
        'migrator',
        'source_table',
        'last_source_id',
        'batch_size',
    ];

    protected function casts(): array
    {
        return [
            'last_source_id' => 'integer',
            'batch_size' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(LegacyMigrationRun::class, 'run_id');
    }
}
