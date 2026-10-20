<?php

namespace App\Jobs\LegacyMigration;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class RunLegacyMigrationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $runId) {}

    public function handle(): void
    {
        throw new RuntimeException(
            'Legacy import is not enabled. Run '.$this->runId.' was not executed.'
        );
    }
}
