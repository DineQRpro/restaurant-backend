<?php

namespace App\Enums;

enum BillStatus: string
{
    case Open = 'open';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Settled => 'Settled',
            self::Cancelled => 'Cancelled',
        };
    }
}
