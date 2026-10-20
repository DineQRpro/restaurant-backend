<?php

namespace App\Console\Commands\LegacyMigration;

use App\Models\LegacyMigrationRun;
use App\Services\LegacyMigration\MasterDataMigrator;
use Illuminate\Console\Command;
use Throwable;

class RetryLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:retry {--run= : Migration run id}';

    protected $description = 'Retry failed rows for an approved local legacy migration run';

    public function handle(MasterDataMigrator $migrator): int
    {
        $id = $this->option('run');

        if (! is_numeric($id)) {
            $this->error('Pass --run=ID.');

            return self::FAILURE;
        }

        $run = LegacyMigrationRun::query()->find((int) $id);

        if (! $run) {
            $this->error('Migration run '.$id.' was not found.');

            return self::FAILURE;
        }

        if (! app()->environment(['local', 'testing'])) {
            $this->error('Legacy import is refused outside the local environment.');

            return self::FAILURE;
        }

        if ($run->mode !== 'apply') {
            $this->error('Run '.$run->id.' is not an apply run.');

            return self::FAILURE;
        }

        try {
            $run = $migrator->retry($run, true);
        } catch (Throwable $exception) {
            $this->error('Failed rows could not be retried.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Run '.$run->id.' imported '.$run->imported_count.', skipped '.$run->skipped_count.', failed '.$run->failed_count.'.');

        return self::SUCCESS;
    }
}
