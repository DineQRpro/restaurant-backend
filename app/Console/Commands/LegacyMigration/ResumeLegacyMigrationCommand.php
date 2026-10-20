<?php

namespace App\Console\Commands\LegacyMigration;

use App\Models\LegacyMigrationRun;
use App\Services\LegacyMigration\MasterDataMigrator;
use Illuminate\Console\Command;
use Throwable;

class ResumeLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:resume {--run= : Migration run id}';

    protected $description = 'Resume an approved local legacy migration run';

    public function handle(MasterDataMigrator $migrator): int
    {
        $run = $this->findRun();

        if (! $run) {
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
            $run = $migrator->resume($run, true);
        } catch (Throwable $exception) {
            $this->error('Run could not be resumed.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Run '.$run->id.' imported '.$run->imported_count.', skipped '.$run->skipped_count.', failed '.$run->failed_count.'.');

        return self::SUCCESS;
    }

    private function findRun(): ?LegacyMigrationRun
    {
        $id = $this->option('run');

        if (! is_numeric($id)) {
            $this->error('Pass --run=ID.');

            return null;
        }

        $run = LegacyMigrationRun::query()->find((int) $id);

        if (! $run) {
            $this->error('Migration run '.$id.' was not found.');

            return null;
        }

        return $run;
    }
}
