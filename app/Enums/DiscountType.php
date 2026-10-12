<?php

namespace App\Enums;

enum DiscountType: string
{
    case Custom = 'custom';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Custom => 'Custom',
            self::Fixed => 'Fixed',
        };
    }
}
