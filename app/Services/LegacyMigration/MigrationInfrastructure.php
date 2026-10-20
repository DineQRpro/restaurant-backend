<?php

namespace App\Services\LegacyMigration;

use App\Models\LegacyMigrationRun;
use Illuminate\Support\Facades\DB;

class MigrationInfrastructure
{
    public function legacyConfigured(): bool
    {
        $connection = config('database.connections.legacy');

        return is_array($connection)
            && filled($connection['database'] ?? null)
            && filled($connection['username'] ?? null);
    }

    public function recordBlocked(string $group, string $mode, string $message): LegacyMigrationRun
    {
        return DB::transaction(function () use ($group, $mode, $message): LegacyMigrationRun {
            $run = LegacyMigrationRun::query()->create([
                'group' => $group,
                'mode' => $mode,
                'status' => 'blocked',
                'imported_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
                'pending_count' => 0,
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            $run->errors()->create([
                'source_table' => 'legacy',
                'source_id' => null,
                'message' => $message,
                'status' => 'pending',
            ]);

            return $run;
        });
    }
}
