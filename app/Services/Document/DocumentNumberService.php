<?php

namespace App\Services\Document;

use App\Models\Bill;
use App\Models\DocumentSequence;
use App\Models\Order;
use InvalidArgumentException;

class DocumentNumberService
{
    public function allocate(string $name): string
    {
        $this->assertSupported($name);

        $row = DocumentSequence::query()
            ->where('name', $name)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            $current = $this->initialNextNumber($name);
            DocumentSequence::query()->create([
                'name' => $name,
                'next_number' => $current + 1,
            ]);

            return (string) $current;
        }

        $current = (int) $row->next_number;
        $row->next_number = $current + 1;
        $row->save();

        return (string) $current;
    }

    public function initialNextNumber(string $name): int
    {
        $this->assertSupported($name);

        $max = 0;
        foreach ($this->existingBusinessNumbers($name) as $value) {
            if ($this->isPureNumericBusinessNumber($value)) {
                $max = max($max, (int) $value);
            }
        }

        return $max + 1;
    }

    public function isPureNumericBusinessNumber(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        return (bool) preg_match('/^[1-9]\d*$/', (string) $value);
    }

    /**
     * @return list<mixed>
     */
    private function existingBusinessNumbers(string $name): array
    {
        return match ($name) {
            DocumentSequence::ORDER => Order::query()->pluck('order_number')->all(),
            DocumentSequence::BILL => Bill::query()->pluck('bill_number')->all(),
            default => [],
        };
    }

    private function assertSupported(string $name): void
    {
        if (! in_array($name, [DocumentSequence::ORDER, DocumentSequence::BILL], true)) {
            throw new InvalidArgumentException("Unsupported document sequence [{$name}].");
        }
    }
}
