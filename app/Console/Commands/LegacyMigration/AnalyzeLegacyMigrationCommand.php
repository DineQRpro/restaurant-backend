<?php

namespace App\Console\Commands\LegacyMigration;

use App\Services\LegacyMigration\MigrationInfrastructure;
use App\Services\LegacyMigration\MigrationPreflight;
use App\Services\LegacyMigration\SourceAnalyzer;
use Illuminate\Console\Command;
use Throwable;

class AnalyzeLegacyMigrationCommand extends Command
{
    protected $signature = 'legacy:migration:analyze';

    protected $description = 'Read legacy source counts and mapping checks without importing';

    public function handle(MigrationInfrastructure $infrastructure, SourceAnalyzer $analyzer, MigrationPreflight $preflight): int
    {
        if (! $infrastructure->legacyConfigured()) {
            $this->error('Legacy database is not configured. Set LEGACY_DB_DATABASE and LEGACY_DB_USERNAME.');

            return self::FAILURE;
        }

        try {
            $preflight->assertSeparated();
            $missing = $preflight->missingTables();
            $report = $analyzer->collect();
            $identity = $preflight->identity();
        } catch (Throwable $exception) {
            $this->error('Legacy database could not be read.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        if ($missing !== []) {
            $this->error('The source database is missing: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->line('Source identity: '.$identity['database'].' on '.$identity['host']);
        $privileges = $report['privileges'];
        $this->info($privileges['summary']);

        if (! $privileges['select_only']) {
            $this->error('Source access is not limited to SELECT. No rows were imported.');

            return self::FAILURE;
        }

        $this->table(['Table', 'Count'], collect($report['counts'])->map(
            fn (int $count, string $table): array => [$table, $count],
        )->values()->all());

        $this->table(['Role code', 'Users'], collect($report['roles'])->map(
            fn (array $role): array => [$role['role'], $role['count']],
        )->all());

        $this->table(['Check', 'Count'], collect($report['validation'])->map(
            fn (int $count, string $check): array => [$check, $count],
        )->values()->all());

        $destination = $report['destination'];
        $groundFloor = $destination['ground_floor'];
        $this->line('Destination roles: '.implode(', ', $destination['roles']));
        $this->line('Destination payment methods: '.implode(', ', $destination['payment_methods']));
        $this->line('Destination menu types: '.implode(', ', $destination['menu_types']));
        $this->line('Ground floor: '.($groundFloor === null
            ? 'id 1 is empty'
            : 'id '.$groundFloor['id'].' '.$groundFloor['name'].' ('.$groundFloor['code'].')'));
        $this->line('Hot Beverages categories: '.$destination['hot_beverage_categories']);
        $this->info('No legacy records were imported.');

        return self::SUCCESS;
    }
}
