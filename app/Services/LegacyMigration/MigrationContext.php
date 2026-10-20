<?php

namespace App\Services\LegacyMigration;

use App\Models\LegacyMigrationRun;

class MigrationContext
{
    /** @var array<string, array<string, int|true>> */
    public array $memory = [];

    /** @var array<string, array{imported: int, skipped: int, failed: int}> */
    public array $tables = [];

    /** @var array<string, true> */
    public array $usernames = [];

    /** @var array<string, true> */
    public array $emails = [];

    /** @var array<string, true> */
    public array $mobiles = [];

    /** @var array<string, true> */
    public array $tableNumbers = [];

    /** @var array<int, array{action: string, role_id?: int, message?: string}> */
    public array $roles = [];

    public ?int $foodMenuTypeId = null;

    public ?int $floorId = null;

    public ?string $floorMessage = null;

    public bool $floorBlocked = false;

    public bool $freshDestination = false;

    public function __construct(
        public readonly LegacyMigrationRun $run,
        public readonly bool $persist,
        public readonly int $batchSize,
    ) {}

    public function imported(string $table): void
    {
        $this->bump($table, 'imported');
    }

    public function reject(string $outcome, string $table, ?string $sourceId, string $message, array $context = []): void
    {
        $this->bump($table, $outcome === 'skipped' ? 'skipped' : 'failed');

        $this->run->errors()->create([
            'source_table' => $table,
            'source_id' => $sourceId,
            'message' => $message,
            'context' => $context,
            'status' => $outcome === 'skipped' ? 'skipped' : 'failed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function remember(string $sourceTable, string $sourceId, string $destinationTable, ?int $destinationId, array $metadata = []): void
    {
        $this->memory[$sourceTable][$sourceId] = $destinationId ?? true;

        if (! $this->persist || $destinationId === null) {
            return;
        }

        $this->run->idMaps()->create([
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'destination_table' => $destinationTable,
            'destination_id' => $destinationId,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    public function mapped(string $sourceTable, string $sourceId): bool
    {
        if (isset($this->memory[$sourceTable][$sourceId])) {
            return true;
        }

        if ($this->freshDestination) {
            return false;
        }

        return $this->run->idMaps()
            ->getRelated()
            ->newQuery()
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->exists();
    }

    public function destinationId(string $sourceTable, string $sourceId): ?int
    {
        $memory = $this->memory[$sourceTable][$sourceId] ?? null;

        if (is_int($memory)) {
            return $memory;
        }

        if ($this->freshDestination) {
            return null;
        }

        $mapped = $this->run->idMaps()
            ->getRelated()
            ->newQuery()
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->value('destination_id');

        return $mapped === null ? null : (int) $mapped;
    }

    public function claim(array &$set, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $key = mb_strtolower($value);

        if (isset($set[$key])) {
            return false;
        }

        $set[$key] = true;

        return true;
    }

    public function release(array &$set, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        unset($set[mb_strtolower($value)]);
    }

    public function totals(): array
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->tables as $counts) {
            $imported += $counts['imported'];
            $skipped += $counts['skipped'];
            $failed += $counts['failed'];
        }

        return [$imported, $skipped, $failed];
    }

    private function bump(string $table, string $outcome): void
    {
        $this->tables[$table] ??= ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        $this->tables[$table][$outcome]++;
    }
}
