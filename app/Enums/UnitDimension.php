<?php

namespace App\Enums;

enum UnitDimension: string
{
    case Count = 'count';
    case Weight = 'weight';
    case Volume = 'volume';
    case Length = 'length';

    public function label(): string
    {
        return match ($this) {
            self::Count => 'Count',
            self::Weight => 'Weight',
            self::Volume => 'Volume',
            self::Length => 'Length',
        };
    }
}
