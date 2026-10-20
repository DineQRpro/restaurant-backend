<?php

namespace App\Console\Commands\LegacyMigration;

use App\Models\LegacyMigrationRun;
use Illuminate\Console\Command;

class StatusLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:status';

    protected $description = 'Show legacy migration runs';

    public function handle(): int
    {
        $runs = LegacyMigrationRun::query()->orderByDesc('id')->get();

        if ($runs->isEmpty()) {
            $this->info('No legacy migration runs.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Group', 'Mode', 'Status', 'Imported', 'Skipped', 'Failed', 'Pending'],
            $runs->map(fn (LegacyMigrationRun $run): array => [
                $run->id,
                $run->group,
                $run->mode,
                $run->status,
                $run->imported_count,
                $run->skipped_count,
                $run->failed_count,
                $run->pending_count,
            ])->all(),
        );

        $latest = $runs->first();
        $identity = $latest->summary['source_identity'] ?? null;

        if (is_array($identity)) {
            $this->line('Latest source: '.($identity['database'] ?? 'unknown').' on '.($identity['host'] ?? 'unknown'));
        }

        $verification = $latest->summary['verification'] ?? null;

        if (is_array($verification)) {
            $this->table(
                ['Check', 'Result', 'Detail'],
                collect($verification['checks'] ?? [])->map(fn (array $check): array => [
                    $check['name'],
                    $check['ok'] ? 'passed' : 'failed',
                    $check['detail'],
                ])->all(),
            );
        }

        $reasons = $latest->errors()
            ->selectRaw('status, message, count(*) as aggregate')
            ->groupBy('status', 'message')
            ->orderByDesc('aggregate')
            ->limit(20)
            ->get();

        if ($reasons->isNotEmpty()) {
            $this->table(
                ['Status', 'Count', 'Reason'],
                $reasons->map(fn ($reason): array => [$reason->status, $reason->aggregate, $reason->message])->all(),
            );
        }

        return self::SUCCESS;
    }
}
