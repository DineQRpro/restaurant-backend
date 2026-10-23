<?php

namespace App\Services\LegacyMigration;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class Values
{
    public static function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    public static function nullableText(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === '' ? null : $text;
    }

    public static function required(mixed $value, string $label, int $max): string
    {
        $text = self::text($value);

        if ($text === '') {
            throw RowRejected::failed($label.' is required.');
        }

        if (mb_strlen($text) > $max) {
            throw RowRejected::failed($label.' is longer than '.$max.' characters.');
        }

        return $text;
    }

    public static function limited(?string $value, string $label, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw RowRejected::failed($label.' is longer than '.$max.' characters.');
        }

        return $value;
    }

    public static function timestamp(mixed $value, string $label): CarbonInterface
    {
        $text = self::text($value);

        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            throw RowRejected::failed($label.' is not a valid timestamp.');
        }

        try {
            return Carbon::parse($text);
        } catch (\Throwable) {
            throw RowRejected::failed($label.' is not a valid timestamp.');
        }
    }

    public static function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    public static function cents(mixed $value): int
    {
        return (int) round(((float) self::money($value)) * 100);
    }

    public static function phone(mixed $value): ?string
    {
        $text = self::text($value);

        if ($text === '') {
            return null;
        }

        if (! preg_match('/^\d{10}$/', $text)) {
            throw RowRejected::failed('Mobile must be empty or exactly 10 digits.');
        }

        return $text;
    }

    public static function email(mixed $value): ?string
    {
        $text = self::nullableText($value);

        if ($text === null) {
            return null;
        }

        if (filter_var($text, FILTER_VALIDATE_EMAIL) === false) {
            throw RowRejected::failed('Email format is invalid.');
        }

        return $text;
    }

    public static function nonNegativeDecimal(mixed $value, string $label): string
    {
        if ($value === null || $value === '' || ! is_numeric($value) || (float) $value < 0) {
            throw RowRejected::failed($label.' must be a number greater than or equal to 0.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    public static function nonNegativeInteger(mixed $value, string $label, bool $nullBecomesZero = false): int
    {
        if ($value === null || $value === '') {
            if ($nullBecomesZero) {
                return 0;
            }

            throw RowRejected::failed($label.' is required.');
        }

        if (! preg_match('/^\d+$/', trim((string) $value))) {
            throw RowRejected::failed($label.' must be an integer greater than or equal to 0.');
        }

        return (int) $value;
    }
}
