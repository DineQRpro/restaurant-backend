<?php

namespace App\Services\Inventory;

final class VendorBillNumber
{
    /**
     * Display value keeps punctuation. The key is the same text uppercased.
     * Leading and trailing whitespace is removed and consecutive whitespace becomes one space.
     *
     * @return array{0: string, 1: string}
     */
    public static function normalize(string $value): array
    {
        $display = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
        $key = mb_strtoupper($display, 'UTF-8');

        return [$display, $key];
    }
}
