<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Checkout = 'checkout';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Active',
            self::Checkout => 'Checkout',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Placed => 'is-active',
            self::Checkout => 'is-checkout',
        };
    }
}
