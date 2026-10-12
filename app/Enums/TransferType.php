<?php

namespace App\Enums;

enum TransferType: string
{
    case Move = 'move';
    case Share = 'share';
    case Join = 'join';

    public function label(): string
    {
        return match ($this) {
            self::Move => 'Move Table',
            self::Share => 'Share Table',
            self::Join => 'Join Party',
        };
    }

    public function isImplemented(): bool
    {
        return $this !== self::Join;
    }
}
