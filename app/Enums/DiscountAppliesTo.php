<?php

namespace App\Enums;

enum DiscountAppliesTo: string
{
    case Product = 'product';
    case Order = 'order';

    public function label(): string
    {
        return match ($this) {
            self::Product => 'Product',
            self::Order => 'Order',
        };
    }
}
