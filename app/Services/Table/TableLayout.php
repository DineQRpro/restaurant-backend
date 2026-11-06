<?php

namespace App\Services\Table;

class TableLayout
{
    public const DEFAULT_COLUMNS = 8;

    public const DEFAULT_ROWS = 6;

    public const MAX_COLUMNS = 24;

    public const MAX_ROWS = 16;

    /**
     * Every positioned table occupies one cell.
     * Capacity does not change the footprint.
     *
     * @return array{width: int, height: int}
     */
    public static function size(int $capacity): array
    {
        unset($capacity);

        return ['width' => 1, 'height' => 1];
    }

    /**
     * Drawing size inside the assigned cell. This is presentation only.
     */
    public static function illustrationClass(int $capacity): string
    {
        return match (true) {
            $capacity <= 2 => 'floor-plan-scale-2',
            $capacity <= 4 => 'floor-plan-scale-4',
            $capacity <= 6 => 'floor-plan-scale-6',
            default => 'floor-plan-scale-8',
        };
    }
}
