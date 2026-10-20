<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Models\LegacyMigrationCheckpoint;
use App\Services\LegacyMigration\LegacySource;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class Migrator
{
    public function __construct(protected readonly LegacySource $source) {}

    abstract public function key(): string;

    abstract public function sourceTable(): string;

    abstract public function primaryKey(): string;

    abstract public function destinationTable(): string;

    public function migrate(MigrationContext $context, ?int $maxBatches = null): void
    {
        $this->prepare($context);
        $checkpoint = $this->checkpoint($context);
        $batches = 0;

        do {
            $rows = $this->fetch((int) $checkpoint->last_source_id, (int) $checkpoint->batch_size);

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $sourceId = (string) $row->{$this->primaryKey()};
                $this->process($context, $row, $sourceId);
                $checkpoint->last_source_id = (int) $sourceId;
                $checkpoint->save();
            }

            $batches++;
        } while ($maxBatches === null || $batches < $maxBatches);
    }

    public function find(string $sourceId): ?object
    {
        return $this->query()->where($this->primaryKey(), $sourceId)->first();
    }

    public function process(MigrationContext $context, object $row, string $sourceId): void
    {
        try {
            if ($context->mapped($this->sourceTable(), $sourceId)) {
                $existing = $context->destinationId($this->sourceTable(), $sourceId);
                $context->memory[$this->sourceTable()][$sourceId] = $existing ?? true;

                throw RowRejected::skipped('Already imported.');
            }

            $import = fn () => $this->import($context, $row, $sourceId);

            if ($context->persist) {
                DB::transaction($import);
            } else {
                $import();
            }

            $context->imported($this->sourceTable());
        } catch (RowRejected $rejected) {
            $context->reject($rejected->outcome, $this->sourceTable(), $sourceId, $rejected->getMessage(), [
                'migrator' => $this->key(),
            ]);
        } catch (Throwable $exception) {
            $context->reject('failed', $this->sourceTable(), $sourceId, $exception->getMessage(), [
                'migrator' => $this->key(),
            ]);
        }
    }

    abstract protected function import(MigrationContext $context, object $row, string $sourceId): void;

    public function boot(MigrationContext $context): void
    {
        $this->prepare($context);
    }

    protected function prepare(MigrationContext $context): void {}

    protected function constrain(Builder $query): void {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function remember(MigrationContext $context, string $sourceId, ?int $destinationId, array $metadata = []): void
    {
        $context->remember($this->sourceTable(), $sourceId, $this->destinationTable(), $destinationId, $metadata);
    }

    /**
     * @return list<object>
     */
    private function fetch(int $afterId, int $limit): array
    {
        return $this->query()
            ->where($this->primaryKey(), '>', $afterId)
            ->orderBy($this->primaryKey())
            ->limit($limit)
            ->get()
            ->all();
    }

    private function query(): Builder
    {
        $query = $this->source->connection()->table($this->sourceTable());
        $this->constrain($query);

        return $query;
    }

    private function checkpoint(MigrationContext $context): LegacyMigrationCheckpoint
    {
        return $context->run->checkpoints()->firstOrCreate(
            ['migrator' => $this->key()],
            [
                'source_table' => $this->sourceTable(),
                'last_source_id' => 0,
                'batch_size' => $context->batchSize,
            ],
        );
    }
}
