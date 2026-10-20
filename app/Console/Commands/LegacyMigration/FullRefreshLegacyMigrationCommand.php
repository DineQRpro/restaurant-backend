<?php

namespace App\Console\Commands\LegacyMigration;

use App\Models\LegacyMigrationRun;
use App\Services\LegacyMigration\FullRefresh;
use Illuminate\Console\Command;
use Throwable;

class FullRefreshLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:full-refresh
        {--dry-run : Validate the Phase 1 dataset without clearing or importing}
        {--approve-reset : Clear destination business data and import a fresh dataset}
        {--approve-production : Required in addition to --approve-reset when APP_ENV=production}';

    protected $description = 'Reset Laravel business data and import a fresh Phase 1 dataset';

    public function handle(FullRefresh $refresh): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $approveReset = (bool) $this->option('approve-reset');

        if ($dryRun === $approveReset) {
            $this->error('Choose either --dry-run or --approve-reset.');

            return self::FAILURE;
        }

        if ($approveReset && app()->environment('production') && ! $this->option('approve-production')) {
            $this->error('Production reset requires --approve-production as well as --approve-reset.');

            return self::FAILURE;
        }

        try {
            $run = $dryRun
                ? $refresh->dryRun()
                : $refresh->execute((bool) $this->option('approve-production'));
        } catch (Throwable $exception) {
            $this->error($dryRun
                ? 'Dry run stopped before any business data was changed.'
                : 'Full refresh did not complete.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($run, $dryRun);

        return self::SUCCESS;
    }

    private function report(LegacyMigrationRun $run, bool $dryRun): void
    {
        $rows = [];

        foreach ($run->summary['tables'] ?? [] as $table => $counts) {
            $rows[] = [$table, $counts['imported'], $counts['skipped'], $counts['failed']];
        }

        $this->table([$dryRun ? 'Source' : 'Source', $dryRun ? 'Valid' : 'Imported', 'Skipped', 'Rejected'], $rows);
        $identity = $run->summary['source_identity'] ?? [];
        $this->line('Source: '.($identity['database'] ?? 'unknown').' on '.($identity['host'] ?? 'unknown'));

        if ($dryRun) {
            $this->info('Dry run '.$run->id.' did not clear or import business data.');

            return;
        }

        $this->info('Run '.$run->id.' imported '.$run->imported_count.', skipped '.$run->skipped_count.', failed '.$run->failed_count.'.');
        $verification = $run->summary['verification']['ok'] ?? false;
        $this->line('Verification: '.($verification ? 'passed' : 'failed'));
    }
}
