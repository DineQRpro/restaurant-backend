<?php

namespace App\Enums;

enum GoodsReceiptPaymentStatus: string
{
    case Paid = 'paid';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Credit => 'Credit',
        };
    }
}
