<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Models\LegacyMigrationMapping;
use App\Models\PaymentMethod;
use App\Services\LegacyMigration\MigrationContext;

class PaymentMethodMigrator extends Migrator
{
    public function key(): string
    {
        return 'payment-methods';
    }

    public function sourceTable(): string
    {
        return 'bill_payment_code';
    }

    public function primaryKey(): string
    {
        return 'code';
    }

    public function destinationTable(): string
    {
        return 'payment_methods';
    }

    public function migrate(MigrationContext $context, ?int $maxBatches = null): void
    {
        $codes = [
            '1' => PaymentMethod::SLUG_CASH,
            '2' => PaymentMethod::SLUG_UPI,
            '3' => PaymentMethod::SLUG_CARD,
            '4' => PaymentMethod::SLUG_SPLIT,
            '5' => PaymentMethod::SLUG_NON_CHARGEABLE,
        ];

        foreach ($codes as $code => $slug) {
            $method = PaymentMethod::query()->where('slug', $slug)->first();

            if (! $method) {
                $context->reject(
                    'failed',
                    $this->sourceTable(),
                    $code,
                    'Payment method '.$slug.' does not exist.',
                    ['migrator' => $this->key()],
                );
                $this->record($code, 'rejected', 'Destination slug '.$slug.' is missing.');

                continue;
            }

            $context->imported($this->sourceTable());
            $this->record($code, 'approved', 'Legacy code '.$code.' reuses '.$slug.' ('.$method->id.').');
        }
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void {}

    private function record(string $code, string $state, string $notes): void
    {
        LegacyMigrationMapping::query()->updateOrCreate(
            [
                'source_table' => 'bill_payment_code',
                'destination_table' => 'payment_methods',
                'decision_key' => $code,
            ],
            [
                'state' => $state,
                'notes' => $notes,
                'approved_at' => $state === 'approved' ? now() : null,
            ],
        );
    }
}
