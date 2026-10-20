<?php

namespace App\Console\Commands\LegacyMigration;

use App\Services\LegacyMigration\MasterDataMigrator;
use App\Services\LegacyMigration\MigrationInfrastructure;
use Illuminate\Console\Command;
use Throwable;

class DryRunLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:dry-run {--group=masters : Migration group}';

    protected $description = 'Preview the Phase 1 master-data migration without writing business rows';

    public function handle(MigrationInfrastructure $infrastructure, MasterDataMigrator $migrator): int
    {
        $group = (string) $this->option('group');

        if ($group !== 'masters') {
            $this->error('Only the masters group is available.');

            return self::FAILURE;
        }

        if (! $infrastructure->legacyConfigured()) {
            $run = $infrastructure->recordBlocked($group, 'dry-run', 'Legacy database is not configured.');
            $this->error('Dry run '.$run->id.' was recorded as blocked. No business tables were changed.');

            return self::FAILURE;
        }

        try {
            $run = $migrator->dryRun();
        } catch (Throwable $exception) {
            $this->error('Dry run failed before import. No business rows were written.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $summary = $run->summary ?? [];
        $sourceCounts = $summary['source']['counts'] ?? [];

        if ($sourceCounts !== []) {
            $this->table(['Source table', 'Count'], collect($sourceCounts)->map(
                fn (int $count, string $table): array => [$table, $count],
            )->values()->all());
        }

        $rows = [];

        foreach ($summary['tables'] ?? [] as $table => $counts) {
            $rows[] = [$table, $counts['imported'], $counts['skipped'], $counts['failed']];
        }

        $this->table(['Source', 'Importable or mapped', 'Skipped', 'Failed'], $rows);
        $this->line('Floor: '.($summary['floor'] ?? 'not checked'));
        $this->line('Payment methods mapped: '.($summary['payment_methods_mapped'] ?? 0));
        $this->line('Discount source statuses among importable rows: '.json_encode($summary['discount_statuses'] ?? []));

        $unresolved = $summary['unresolved_decisions'] ?? [];
        $this->line('Unresolved mapping decisions: '.($unresolved === [] ? 'none' : implode('; ', $unresolved)));
        $this->info('Dry run '.$run->id.' imported '.$run->imported_count.', skipped '.$run->skipped_count.', failed '.$run->failed_count.'. No business rows were written.');

        return self::SUCCESS;
    }
}
