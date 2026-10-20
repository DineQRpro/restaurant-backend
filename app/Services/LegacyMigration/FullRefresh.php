<?php

namespace App\Services\LegacyMigration;

use App\Models\LegacyMigrationRun;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FullRefresh
{
    public function __construct(
        private readonly MigrationInfrastructure $infrastructure,
        private readonly MigrationPreflight $preflight,
        private readonly SourceAnalyzer $analyzer,
        private readonly MasterDataMigrator $migrator,
        private readonly BusinessDataReset $reset,
        private readonly DestinationBackup $backup,
        private readonly DestinationRestore $restore,
        private readonly ImportVerifier $verifier,
        private readonly MaintenanceMode $maintenance,
    ) {}

    public function dryRun(): LegacyMigrationRun
    {
        $this->prepare();

        $run = $this->migrator->execute(false, null, true);
        $summary = $run->summary ?? [];
        $summary['source_identity'] = $this->preflight->identity();
        $summary['destination_before'] = $this->reset->counts();
        $summary['persist'] = false;
        $run->summary = $summary;
        $run->mode = 'full-refresh-dry-run';
        $run->save();

        return $run->refresh();
    }

    public function execute(bool $productionApproved): LegacyMigrationRun
    {
        $this->prepare();
        $this->migrator->execute(false, null, true);

        $backupPath = null;
        $cleared = false;

        try {
            $backupPath = $this->backup->backup();
            $this->maintenance->activate([
                'except' => [],
                'retry' => 60,
                'refresh' => 15,
                'secret' => Str::random(32),
            ]);
            $this->reset->clear();
            $cleared = true;

            $run = $this->migrator->execute(true, null, true, $productionApproved);

            if (config('legacy-migration.simulate_failure_after_import') === true) {
                throw new RuntimeException('Simulated migration failure after destination reset.');
            }

            $verification = $this->verifier->verify($run);
            $summary = $run->summary ?? [];
            $summary['source_identity'] = $this->preflight->identity();
            $summary['backup'] = basename($backupPath);
            $summary['verification'] = $verification;
            $summary['activation_file'] = $this->writeActivations($run);
            $run->summary = $summary;
            $run->mode = 'full-refresh';
            $run->status = $verification['ok'] ? 'completed' : 'verification-failed';
            $run->save();

            if (! $verification['ok']) {
                throw new RuntimeException('Import finished but verification failed. See legacy:migration:status.');
            }

            $this->maintenance->deactivate();

            return $run->refresh();
        } catch (Throwable $exception) {
            $this->recover($cleared, $backupPath, $exception);
        }
    }

    private function recover(bool $cleared, ?string $backupPath, Throwable $exception): never
    {
        if (! $cleared || $backupPath === null) {
            if ($this->maintenance->active()) {
                $this->maintenance->deactivate();
            }

            throw $exception;
        }

        try {
            $this->restore->restore($backupPath);
            $this->maintenance->deactivate();
        } catch (Throwable $restoreError) {
            throw new RuntimeException(
                'The import failed after the destination was cleared, and the backup could not be restored. Maintenance mode remains active. Backup file: '.basename($backupPath).'.',
                0,
                $exception,
            );
        }

        throw new RuntimeException(
            'The import failed after the destination was cleared. The backup was restored and maintenance mode was turned off. '.$exception->getMessage(),
            0,
            $exception,
        );
    }

    private function prepare(): void
    {
        if (! $this->infrastructure->legacyConfigured()) {
            throw new RuntimeException('Legacy database is not configured.');
        }

        $this->preflight->assertSeparated();
        $privileges = $this->analyzer->collect()['privileges'] ?? [];

        if (! ($privileges['select_only'] ?? false)) {
            throw new RuntimeException('The legacy connection is not limited to SELECT.');
        }

        $missing = $this->preflight->missingTables();

        if ($missing !== []) {
            throw new RuntimeException('The source database is missing: '.implode(', ', $missing));
        }
    }

    private function writeActivations(LegacyMigrationRun $run): ?string
    {
        $lines = $this->migrator->activationLines();

        if ($lines === []) {
            return null;
        }

        $directory = storage_path('app/legacy-migration');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/run-'.$run->id.'-activations.txt';
        file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
        chmod($path, 0600);

        return basename($path);
    }
}
