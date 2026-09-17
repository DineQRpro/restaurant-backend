<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Served = 'served';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return [
            self::Preparing->value,
            self::Served->value,
            self::Cancelled->value,
        ];
    }

    public function canTransitionTo(self $status): bool
    {
        if ($this === $status) {
            return true;
        }

        if ($this === self::Cancelled || $this === self::Served) {
            return false;
        }

        return in_array($status, [self::Preparing, self::Served, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Preparing => 'Preparing',
            self::Served => 'Served',
            self::Cancelled => 'Cancelled',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Pending => 'is-pending',
            self::Preparing => 'is-preparing',
            self::Served => 'is-served',
            self::Cancelled => 'is-cancelled',
        };
    }
}
