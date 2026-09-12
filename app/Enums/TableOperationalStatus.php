<?php

namespace App\Enums;

enum TableOperationalStatus: int
{
    case Available = 0;
    case Occupied = 1;
    case Cleaning = 3;

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Cleaning => 'Cleaning',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Available => 'admin-table-status-available',
            self::Occupied => 'admin-table-status-occupied',
            self::Cleaning => 'admin-table-status-cleaning',
        };
    }
}
