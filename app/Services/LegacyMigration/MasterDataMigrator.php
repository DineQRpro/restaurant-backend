<?php

namespace App\Services\LegacyMigration;

use App\Models\LegacyMigrationError;
use App\Models\LegacyMigrationRun;
use App\Services\LegacyMigration\Migrators\DiscountMigrator;
use App\Services\LegacyMigration\Migrators\MenuCategoryMigrator;
use App\Services\LegacyMigration\Migrators\MenuItemMigrator;
use App\Services\LegacyMigration\Migrators\Migrator;
use App\Services\LegacyMigration\Migrators\PaymentMethodMigrator;
use App\Services\LegacyMigration\Migrators\RoleMigrator;
use App\Services\LegacyMigration\Migrators\TableMigrator;
use App\Services\LegacyMigration\Migrators\UserMigrator;
use RuntimeException;
use Throwable;

class MasterDataMigrator
{
    public function __construct(
        private readonly RoleMigrator $roles,
        private readonly UserMigrator $users,
        private readonly MenuCategoryMigrator $categories,
        private readonly MenuItemMigrator $items,
        private readonly TableMigrator $tables,
        private readonly DiscountMigrator $discounts,
        private readonly PaymentMethodMigrator $payments,
        private readonly TransactionMigrator $transactions,
        private readonly SourceAnalyzer $analyzer,
    ) {}

    public function dryRun(): LegacyMigrationRun
    {
        return $this->execute(false);
    }

    public function execute(bool $persist, ?int $maxBatches = null, bool $freshDestination = false, bool $productionApproved = false): LegacyMigrationRun
    {
        $this->guardPersist($persist, $productionApproved);

        $run = LegacyMigrationRun::query()->create([
            'group' => 'masters',
            'mode' => $persist ? 'apply' : 'dry-run',
            'status' => 'running',
            'approved_at' => $persist ? now() : null,
            'started_at' => now(),
        ]);

        $context = new MigrationContext(
            $run,
            $persist,
            max(1, (int) config('legacy-migration.batch_size')),
        );
        $context->freshDestination = $freshDestination;

        $source = $this->safeSource();
        $this->roles->migrate($context);

        foreach ($this->rowMigrators() as $migrator) {
            try {
                $migrator->migrate($context, $maxBatches);
            } catch (RuntimeException $exception) {
                $context->reject('failed', $migrator->sourceTable(), null, $exception->getMessage(), [
                    'migrator' => $migrator->key(),
                ]);
            }
        }

        $this->payments->migrate($context);
        $this->transactions->migrate($context);

        return $this->finish($run, $context, $source, $persist);
    }

    /**
     * @return list<string>
     */
    public function activationLines(): array
    {
        return $this->users->activationLines;
    }

    public function resume(LegacyMigrationRun $run, bool $persist, ?int $maxBatches = null): LegacyMigrationRun
    {
        $this->guardPersist($persist);

        $context = new MigrationContext(
            $run,
            $persist,
            max(1, (int) config('legacy-migration.batch_size')),
        );
        $this->roles->assign($context);

        foreach ($this->rowMigrators() as $migrator) {
            try {
                $migrator->migrate($context, $maxBatches);
            } catch (RuntimeException $exception) {
                $context->reject('failed', $migrator->sourceTable(), null, $exception->getMessage(), [
                    'migrator' => $migrator->key(),
                ]);
            }
        }

        [$imported, $skipped, $failed] = $this->businessTotals($context);
        $run->imported_count += $imported;
        $run->skipped_count += $skipped;
        $run->failed_count += $failed;
        $run->status = $persist ? 'completed' : 'reported';
        $run->finished_at = now();
        $run->save();

        return $run->refresh();
    }

    public function retry(LegacyMigrationRun $run, bool $persist): LegacyMigrationRun
    {
        $this->guardPersist($persist);

        $context = new MigrationContext(
            $run,
            $persist,
            max(1, (int) config('legacy-migration.batch_size')),
        );
        $this->roles->assign($context);

        foreach ($this->rowMigrators() as $migrator) {
            $migrator->boot($context);
        }

        $errors = $run->errors()->where('status', 'failed')->orderBy('id')->get();

        foreach ($errors as $error) {
            $this->retryError($context, $error);
        }

        $run->status = $persist ? 'completed' : 'reported';
        $run->finished_at = now();
        $run->save();

        return $run->refresh();
    }

    /**
     * @return list<Migrator>
     */
    private function rowMigrators(): array
    {
        return [
            $this->users,
            $this->categories,
            $this->items,
            $this->tables,
            $this->discounts,
        ];
    }

    private function retryError(MigrationContext $context, LegacyMigrationError $error): void
    {
        $migrator = $this->migratorFor((string) ($error->context['migrator'] ?? ''));

        if (! $migrator instanceof Migrator || $error->source_id === null) {
            return;
        }

        $row = $migrator->find((string) $error->source_id);

        if ($row === null) {
            $error->update(['message' => 'Source row is no longer eligible.']);

            return;
        }

        $beforeImported = $context->tables[$migrator->sourceTable()]['imported'] ?? 0;

        $migrator->process($context, $row, (string) $error->source_id);

        $afterImported = $context->tables[$migrator->sourceTable()]['imported'] ?? 0;

        if ($afterImported > $beforeImported) {
            $error->update(['status' => 'resolved']);
            $context->run->failed_count = max(0, $context->run->failed_count - 1);
            $context->run->imported_count++;
            $context->run->save();

            return;
        }

        $latest = $context->run->errors()->where('source_id', $error->source_id)->orderByDesc('id')->first();

        if ($latest && $latest->id !== $error->id) {
            $error->update([
                'message' => $latest->message,
                'status' => $latest->status,
            ]);
            $latest->delete();

            if ($error->status === 'skipped') {
                $context->run->failed_count = max(0, $context->run->failed_count - 1);
                $context->run->skipped_count++;
                $context->run->save();
            }
        }
    }

    private function migratorFor(string $key): ?Migrator
    {
        foreach ($this->rowMigrators() as $migrator) {
            if ($migrator->key() === $key) {
                return $migrator;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $source
     */
    private function finish(LegacyMigrationRun $run, MigrationContext $context, ?array $source, bool $persist): LegacyMigrationRun
    {
        [$imported, $skipped, $failed] = $this->businessTotals($context);
        $paymentMapped = $context->tables['bill_payment_code']['imported'] ?? 0;
        $paymentFailed = $context->tables['bill_payment_code']['failed'] ?? 0;

        $run->fill([
            'status' => $persist ? 'completed' : 'reported',
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'failed_count' => $failed + $paymentFailed,
            'pending_count' => 0,
            'finished_at' => now(),
            'summary' => [
                'persist' => $persist,
                'tables' => $context->tables,
                'source' => $source,
                'floor' => $context->floorMessage,
                'discount_statuses' => $this->discounts->statuses,
                'payment_methods_mapped' => $paymentMapped,
                'transactions' => $context->transactionSummary,
                'unresolved_decisions' => $context->floorBlocked && $context->floorMessage
                    ? [$context->floorMessage]
                    : [],
            ],
        ]);
        $run->save();

        return $run->refresh();
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function businessTotals(MigrationContext $context): array
    {
        [$imported, $skipped, $failed] = $context->totals();
        $payment = $context->tables['bill_payment_code'] ?? ['imported' => 0, 'skipped' => 0, 'failed' => 0];

        return [
            $imported - $payment['imported'],
            $skipped - $payment['skipped'],
            $failed - $payment['failed'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeSource(): ?array
    {
        try {
            return $this->analyzer->collect();
        } catch (Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    private function guardPersist(bool $persist, bool $productionApproved = false): void
    {
        if (! $persist) {
            return;
        }

        if (app()->environment('production') && ! $productionApproved) {
            throw new RuntimeException('Production import requires explicit approval.');
        }

        if (! app()->environment(['local', 'testing', 'production'])) {
            throw new RuntimeException('Legacy import is refused in this environment.');
        }
    }
}
