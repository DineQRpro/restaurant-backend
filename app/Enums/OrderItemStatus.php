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
}
