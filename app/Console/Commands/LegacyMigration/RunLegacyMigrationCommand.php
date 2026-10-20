<?php

namespace App\Console\Commands\LegacyMigration;

use App\Models\LegacyMigrationRun;
use App\Services\LegacyMigration\MasterDataMigrator;
use App\Services\LegacyMigration\MigrationInfrastructure;
use Illuminate\Console\Command;
use Throwable;

class RunLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:run
        {--group=masters : Migration group}
        {--approve : Confirm that mapping decisions are approved}';

    protected $description = 'Import approved Phase 1 master data from the legacy database';

    public function handle(MigrationInfrastructure $infrastructure, MasterDataMigrator $migrator): int
    {
        $group = (string) $this->option('group');

        if (! $this->option('approve')) {
            $this->error('Refusing to run. Pass --approve after the mapping decisions are approved.');

            return self::FAILURE;
        }

        if ($group !== 'masters') {
            $this->error('Only the masters group can be imported.');

            return self::FAILURE;
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->error('Legacy import is refused outside the local environment.');

            return self::FAILURE;
        }

        if (! $infrastructure->legacyConfigured()) {
            $run = $infrastructure->recordBlocked($group, 'run', 'Legacy database is not configured.');
            $this->error('Run '.$run->id.' was recorded as blocked. No business tables were changed.');

            return self::FAILURE;
        }

        try {
            $run = $migrator->execute(true);
        } catch (Throwable $exception) {
            $this->error('Import stopped before it could finish.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($run);

        return self::SUCCESS;
    }

    private function report(LegacyMigrationRun $run): void
    {
        $rows = [];

        foreach ($run->summary['tables'] ?? [] as $table => $counts) {
            $rows[] = [$table, $counts['imported'], $counts['skipped'], $counts['failed']];
        }

        $this->table(['Source', 'Imported or mapped', 'Skipped', 'Failed'], $rows);
        $this->line('Floor: '.($run->summary['floor'] ?? 'not checked'));
        $this->info('Run '.$run->id.' imported '.$run->imported_count.', skipped '.$run->skipped_count.', failed '.$run->failed_count.'.');
    }
}
