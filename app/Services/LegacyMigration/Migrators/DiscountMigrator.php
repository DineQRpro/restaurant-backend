<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Enums\DiscountAppliesTo;
use App\Enums\DiscountType;
use App\Models\Discount;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use App\Services\LegacyMigration\Values;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DiscountMigrator extends Migrator
{
    /** @var array<string, int> */
    public array $statuses = [];

    public function key(): string
    {
        return 'discounts';
    }

    public function sourceTable(): string
    {
        return 'tbl_discounts';
    }

    public function primaryKey(): string
    {
        return 'discount_id';
    }

    public function destinationTable(): string
    {
        return 'discounts';
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void
    {
        $deleted = (string) ($row->deleted ?? '0');

        if ($deleted === '1') {
            throw RowRejected::skipped('Deleted discounts are excluded.');
        }

        if (! in_array($deleted, ['0', ''], true)) {
            throw RowRejected::failed('Deleted flag is not supported.');
        }

        $name = Values::required($row->discount_name ?? null, 'Discount name', 150);
        $type = (string) ($row->discount_type ?? '');

        if (! in_array($type, ['custom', 'fixed'], true)) {
            throw RowRejected::failed('Discount type must be custom or fixed.');
        }

        $value = Values::nonNegativeDecimal($row->discount_value ?? null, 'Discount value');
        $min = Values::nonNegativeInteger($row->discount_min_level ?? null, 'Minimum level', true);
        $max = Values::nonNegativeInteger($row->discount_max_level ?? null, 'Maximum level', true);

        if ($min > 0 && $max > 0 && $max < $min) {
            throw RowRejected::failed('Maximum level is lower than minimum level.');
        }

        $applies = (string) ($row->applies_to ?? '');

        if (! in_array($applies, ['product', 'order'], true)) {
            throw RowRejected::failed('Discount applies-to is missing or unsupported.');
        }

        $start = $this->date($row->start_date ?? null, 'Start date');
        $end = $this->date($row->end_date ?? null, 'End date');

        if ($start && $end && $end->lt($start)) {
            throw RowRejected::failed('End date is before the start date.');
        }

        $createdAt = Values::timestamp($row->created_at ?? null, 'Created at');
        $updatedAt = Values::timestamp($row->updated_at ?? null, 'Updated at');
        $status = Values::text($row->status ?? '');
        $this->statuses[$status === '' ? 'empty' : $status] = ($this->statuses[$status === '' ? 'empty' : $status] ?? 0) + 1;

        if (! $context->persist) {
            $this->remember($context, $sourceId, null, ['status' => $status]);

            return;
        }

        $discount = new Discount([
            'name' => $name,
            'type' => DiscountType::from($type),
            'value' => $value,
            'min_level' => $min,
            'max_level' => $max,
            'applies_to' => DiscountAppliesTo::from($applies),
            'start_date' => $start,
            'end_date' => $end,
            'is_active' => false,
        ]);
        $discount->created_at = $createdAt;
        $discount->updated_at = $updatedAt;
        $discount->save();

        $this->remember($context, $sourceId, $discount->id, ['status' => $status]);
    }

    private function date(mixed $value, string $label): ?CarbonInterface
    {
        $text = Values::text($value);

        if ($text === '') {
            return null;
        }

        if (str_starts_with($text, '0000-00-00')) {
            throw RowRejected::failed($label.' is not a valid date.');
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable) {
            throw RowRejected::failed($label.' is not a valid date.');
        }
    }
}
