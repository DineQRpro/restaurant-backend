<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Reversal => 'Reversal',
        };
    }
}
